<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use Cake\TestSuite\TestCase;
use PDO;
use Symfony\Component\Process\Process;

class LegacyUpgradeTest extends TestCase
{
    public function testMigrationCommandRunsWithoutLock(): void
    {
        $path = tempnam(TMP, 'wiki-migrations-');
        $this->assertNotFalse($path);
        $environment = ['DATABASE_URL' => 'sqlite://localhost/' . $path];
        try {
            $process = new Process(
                [PHP_BINARY, ROOT . '/bin/cake.php', 'migrations', 'migrate', '--no-lock', '--quiet'],
                ROOT,
                $environment,
            );
            $process->mustRun();
            $db = new PDO('sqlite:' . $path);
            $this->assertGreaterThan(0, (int)$db->query('SELECT COUNT(*) FROM sqlite_master')->fetchColumn());
        } finally {
            unlink($path);
        }
    }

    public function testUpgradePreservesMarkdownHistoryAndPendingQueue(): void
    {
        $path = tempnam(TMP, 'wiki-upgrade-');
        $this->assertNotFalse($path);
        $environment = ['DATABASE_URL' => 'sqlite://localhost/' . $path];
        try {
            $this->cake(['migrations', 'migrate', '--target', '20261124180000', '--no-lock'], $environment);
            $db = new PDO('sqlite:' . $path);
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $db->exec(
                "INSERT INTO wikis (id, name, slug, is_public, created, modified)
                 VALUES (1, 'Legacy', 'legacy', 0, '2025-01-01', '2025-01-01')",
            );
            $db->exec(
                "INSERT INTO wiki_pages (id, wiki_id, path, title, body, created, modified, deleted, version)
                 VALUES (1, 1, 'start', 'Start', '# Existing', '2025-01-01', '2025-01-01', 0, 1),
                        (2, 1, 'removed', 'Removed', '**Old**', '2025-01-01', '2025-01-01', 1, 1)",
            );
            $db->exec(
                "INSERT INTO wiki_page_revisions (id, wiki_page_id, path, title, body, created, operation, git_synced, git_commit)
                 VALUES (1, 1, 'start', 'Start', '# Existing', '2025-01-01', 'create', 0, 'abc123'),
                        (2, 2, 'removed', 'Removed', '**Old**', '2025-01-01', 'delete', 1, NULL)",
            );
            $this->cake(['migrations', 'migrate', '--no-lock'], $environment);
            $this->cake(['wiki_migrate_content'], $environment);
            $revisions = $db->query(
                'SELECT id, content_format, body, operation FROM wiki_page_revisions ORDER BY id',
            )->fetchAll(PDO::FETCH_ASSOC);
            $this->assertCount(4, $revisions);
            $this->assertSame('markdown', $revisions[0]['content_format']);
            $this->assertSame('# Existing', $revisions[0]['body']);
            $this->assertSame('markdown', $revisions[1]['content_format']);
            $this->assertSame('**Old**', $revisions[1]['body']);
            $this->assertSame('html', $revisions[2]['content_format']);
            $this->assertStringContainsString('<h1>Existing</h1>', $revisions[2]['body']);
            $this->assertSame('delete', $revisions[3]['operation']);
            $queue = $db->query('SELECT entity_id, git_commit FROM wiki_git_queue ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
            $this->assertSame([1, 3, 4], array_map('intval', array_column($queue, 'entity_id')));
            $this->assertSame('abc123', $queue[0]['git_commit']);
            $this->assertSame(1, (int)$db->query('SELECT deleted FROM wiki_pages WHERE id = 2')->fetchColumn());
        } finally {
            unlink($path);
        }
    }

    private function cake(array $arguments, array $environment): void
    {
        $process = new Process(array_merge([PHP_BINARY, ROOT . '/bin/cake.php'], $arguments), ROOT, $environment);
        $process->mustRun();
    }
}
