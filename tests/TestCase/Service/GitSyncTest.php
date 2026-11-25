<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\GitSync;
use App\Service\GitSyncException;
use App\Service\MediaStorage;
use App\Service\PageWriter;
use Cake\Datasource\ConnectionManager;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;
use Laminas\Diactoros\UploadedFile;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Process\Process;

class GitSyncTest extends TestCase
{
    use IntegrationTestTrait;

    private string $directory;
    private string $remote;
    private string $repository;
    private string|false $previousDirectory;
    private string|false $previousMediaDirectory;

    protected function setUp(): void
    {
        parent::setUp();
        ConnectionManager::get('test')->begin();
        $this->directory = TMP . 'tests' . DS . 'wiki-git-' . bin2hex(random_bytes(8));
        mkdir($this->directory, 0700, true);
        $this->remote = $this->directory . DS . 'remote.git';
        $this->repository = $this->directory . DS . 'app.git';
        $this->command(['git', 'init', '--bare', '--initial-branch=main', $this->remote]);
        $this->command(['git', 'clone', '--bare', $this->remote, $this->repository]);
        $this->command(['git', '--git-dir=' . $this->repository, 'config', 'user.name', 'Wiki Test']);
        $this->command(['git', '--git-dir=' . $this->repository, 'config', 'user.email', 'wiki-test@example.invalid']);
        $this->previousDirectory = getenv('WIKI_GIT_DIR');
        $this->previousMediaDirectory = getenv('WIKI_MEDIA_DIR');
        putenv('WIKI_GIT_DIR=' . $this->repository);
        putenv('WIKI_MEDIA_DIR=' . $this->directory);
    }

    protected function tearDown(): void
    {
        ConnectionManager::get('test')->rollback();
        if ($this->previousDirectory === false) {
            putenv('WIKI_GIT_DIR');
        } else {
            putenv('WIKI_GIT_DIR=' . $this->previousDirectory);
        }
        if ($this->previousMediaDirectory === false) {
            putenv('WIKI_MEDIA_DIR');
        } else {
            putenv('WIKI_MEDIA_DIR=' . $this->previousMediaDirectory);
        }
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->directory, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $item) {
            if ($item->isDir() && !$item->isLink()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }
        rmdir($this->directory);
        parent::tearDown();
    }

    public function testPushRetryAndRecoveryDoNotDuplicateCommits(): void
    {
        $db = ConnectionManager::get('test');
        $now = gmdate('Y-m-d H:i:s');
        $wiki = $db->insert('wikis', [
            'name' => 'Private',
            'slug' => 'private',
            'is_public' => 0,
            'created' => $now,
            'modified' => $now,
        ])->lastInsertId('wikis');
        $user = $db->insert('users', [
            'username' => 'sync-user',
            'password' => 'unused',
            'created' => $now,
            'modified' => $now,
        ])->lastInsertId('users');
        $writer = new PageWriter($db);
        $pageId = $writer->create((int)$wiki, (int)$user, 'guides/start', 'Start', 'First version');
        $sync = new GitSync($db);
        $lock = fopen($this->repository . DS . 'wiki-sync.lock', 'c');
        flock($lock, LOCK_EX);
        try {
            $sync->synchronize();
            $this->fail('A concurrent synchronization must be reported.');
        } catch (GitSyncException $exception) {
            $this->assertStringContainsString('already running', $exception->getMessage());
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
        $this->assertSame(1, $sync->synchronize());
        $file = $this->command(['git', '--git-dir=' . $this->remote, 'show', 'main:wikis/private/guides/start.md']);
        $this->assertStringContainsString('First version', $file);

        $page = $db->execute('SELECT * FROM wiki_pages WHERE id = ?', [$pageId])->fetch('assoc');
        $writer->change($page, (int)$user, 'Updated', 'Second version', 'edit', 1);
        $this->command(['git', '--git-dir=' . $this->repository, 'remote', 'set-url', 'origin', $this->directory . '/missing']);
        try {
            $sync->synchronize();
            $this->fail('A failed push must be reported.');
        } catch (GitSyncException $exception) {
            $this->assertStringContainsString('push', $exception->getMessage());
        }
        $pending = $db->execute(
            'SELECT * FROM wiki_page_revisions WHERE git_synced = 0 AND wiki_page_id = ?',
            [$pageId],
        )->fetch('assoc');
        $this->assertNotEmpty($pending['git_commit']);
        $this->assertNotEmpty($pending['git_error']);
        $this->command(['git', '--git-dir=' . $this->repository, 'remote', 'set-url', 'origin', $this->remote]);
        $this->assertSame(1, $sync->synchronize());
        $this->assertSame("2\n", $this->command(['git', '--git-dir=' . $this->remote, 'rev-list', '--count', 'main']));

        $db->update('wiki_page_revisions', ['git_synced' => 0, 'git_commit' => null], ['id' => $pending['id']]);
        $db->insert('wiki_git_queue', ['kind' => 'revision', 'entity_id' => $pending['id']]);
        $this->assertSame(1, $sync->synchronize());
        $this->assertSame("2\n", $this->command(['git', '--git-dir=' . $this->remote, 'rev-list', '--count', 'main']));
        $file = $this->command(['git', '--git-dir=' . $this->remote, 'show', 'main:wikis/private/guides/start.md']);
        $this->assertStringContainsString('Second version', $file);

        $page = $db->execute('SELECT * FROM wiki_pages WHERE id = ?', [$pageId])->fetch('assoc');
        $writer->change($page, (int)$user, $page['title'], $page['body'], 'delete', 2);
        $this->assertSame(1, $sync->synchronize());
        $deleted = new Process(['git', '--git-dir=' . $this->remote, 'cat-file', '-e', 'main:wikis/private/guides/start.md']);
        $deleted->run();
        $this->assertFalse($deleted->isSuccessful());
        $lastRevision = $db->execute('SELECT MAX(id) FROM wiki_page_revisions WHERE wiki_page_id = ?', [$pageId])
            ->fetchColumn(0);
        $db->update('wiki_page_revisions', ['git_synced' => 0, 'git_commit' => null], ['id' => $lastRevision]);
        $db->insert('wiki_git_queue', ['kind' => 'revision', 'entity_id' => $lastRevision]);
        $this->assertSame(1, $sync->synchronize());
        $this->assertSame("3\n", $this->command(['git', '--git-dir=' . $this->remote, 'rev-list', '--count', 'main']));

        $page = $db->execute('SELECT * FROM wiki_pages WHERE id = ?', [$pageId])->fetch('assoc');
        $writer->change($page, (int)$user, 'Start', 'First version', 'restore', 3);
        $this->assertSame(1, $sync->synchronize());
        $file = $this->command(['git', '--git-dir=' . $this->remote, 'show', 'main:wikis/private/guides/start.md']);
        $this->assertStringContainsString('First version', $file);
    }

    public function testSavingThroughHttpPushesAndPreservesEditsOnFailure(): void
    {
        $db = ConnectionManager::get('test');
        $now = gmdate('Y-m-d H:i:s');
        $wikiId = (int)$db->insert('wikis', [
            'name' => 'HTTP Wiki',
            'slug' => 'http-wiki',
            'is_public' => 0,
            'created' => $now,
            'modified' => $now,
        ])->lastInsertId('wikis');
        $userId = (int)$db->insert('users', [
            'username' => 'http-admin',
            'password' => 'unused',
            'is_admin' => 1,
            'created' => $now,
            'modified' => $now,
        ])->lastInsertId('users');
        $this->enableCsrfToken();
        $this->session(['Auth' => $userId, 'AuthVersion' => 1]);
        $this->post('/wikis/' . $wikiId . '/pages/add', [
            'path' => 'guides/http',
            'title' => 'HTTP page',
            'body' => '<p>Saved through HTTP</p>' .
                '<table><tbody><tr><th colwidth="120">Header</th></tr></tbody>' .
                '<tfoot><tr><td colwidth="120">Footer</td></tr></tfoot></table>' .
                '<pre><code class="language-javascript">const n = 1;</code></pre>',
        ]);
        $page = $db->execute('SELECT * FROM wiki_pages WHERE wiki_id = ?', [$wikiId])->fetch('assoc');
        $this->assertRedirect('/wikis/' . $wikiId . '/pages/' . $page['id']);
        $remoteFile = $this->command(['git', '--git-dir=' . $this->remote, 'show', 'main:wikis/http-wiki/guides/http.md']);
        $this->assertStringContainsString('Saved through HTTP', $remoteFile);
        $this->assertStringContainsString('<tfoot>', $remoteFile);
        $this->assertStringContainsString('colwidth="120"', $remoteFile);
        $this->assertStringContainsString('class="language-javascript"', $remoteFile);

        $this->command(['git', '--git-dir=' . $this->repository, 'remote', 'set-url', 'origin', $this->directory . '/missing']);
        $this->post('/wikis/' . $wikiId . '/pages/' . $page['id'] . '/edit', [
            'title' => 'Updated',
            'body' => 'Saved despite Git failure',
            'version' => 1,
        ]);
        $this->assertRedirect();
        $page = $db->execute('SELECT * FROM wiki_pages WHERE id = ?', [$page['id']])->fetch('assoc');
        $this->assertSame('Saved despite Git failure', $page['body']);
        $pending = $db->execute(
            'SELECT git_error FROM wiki_page_revisions WHERE wiki_page_id = ? AND git_synced = 0',
            [$page['id']],
        )->fetch('assoc');
        $this->assertStringContainsString('push', $pending['git_error']);
    }

    public function testMediaAndPagesKeepQueueOrderAcrossPushFailureAndRecovery(): void
    {
        $db = ConnectionManager::get('test');
        $now = gmdate('Y-m-d H:i:s');
        $wikiId = (int)$db->insert('wikis', [
            'name' => 'Media', 'slug' => 'media', 'is_public' => 0, 'created' => $now, 'modified' => $now,
        ])->lastInsertId('wikis');
        $userId = (int)$db->insert('users', [
            'username' => 'media-sync', 'password' => 'unused', 'created' => $now, 'modified' => $now,
        ])->lastInsertId('users');
        $writer = new PageWriter($db);
        $pageId = $writer->create($wikiId, $userId, 'guides/start', 'Start', '<p>First</p>');
        $sync = new GitSync($db);
        $this->command(['git', '--git-dir=' . $this->repository, 'remote', 'set-url', 'origin', $this->directory . '/missing']);
        try {
            $sync->synchronize(1);
            $this->fail('A failed push must remain queued.');
        } catch (GitSyncException $exception) {
            $this->assertStringContainsString('push', $exception->getMessage());
        }
        $gif = str_pad(
            base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7', true),
            MediaStorage::MAX_BYTES,
            "\0",
        );
        $stream = fopen('php://temp', 'w+b');
        fwrite($stream, $gif);
        rewind($stream);
        $media = (new MediaStorage($db))->store(
            $wikiId,
            $userId,
            new UploadedFile($stream, strlen($gif), UPLOAD_ERR_OK, 'test.gif', 'image/gif'),
        );
        $page = $db->execute('SELECT * FROM wiki_pages WHERE id = ?', [$pageId])->fetch('assoc');
        $writer->change(
            $page,
            $userId,
            'Start',
            '<p><img src="/wikis/' . $wikiId . '/media/' . $media['id'] . '" alt="test"></p>',
            'edit',
            1,
        );
        $this->command(['git', '--git-dir=' . $this->repository, 'remote', 'set-url', 'origin', $this->remote]);
        $this->assertSame(1, $sync->synchronize(1));
        $this->command(['git', '--git-dir=' . $this->repository, 'remote', 'set-url', 'origin', $this->directory . '/missing']);
        try {
            $sync->synchronize(1);
            $this->fail('An image push failure must remain queued.');
        } catch (GitSyncException $exception) {
            $this->assertStringContainsString('push', $exception->getMessage());
        }
        $db->update('wiki_git_queue', ['git_commit' => null], ['kind' => 'media', 'entity_id' => $media['id']]);
        $db->update('wiki_media', ['git_commit' => null], ['id' => $media['id']]);
        $this->command(['git', '--git-dir=' . $this->repository, 'remote', 'set-url', 'origin', $this->remote]);
        $this->assertSame(2, $sync->synchronize());
        $this->assertSame("3\n", $this->command(['git', '--git-dir=' . $this->remote, 'rev-list', '--count', 'main']));
        $imagePath = 'wikis/media/_media/' . $media['sha256'] . '.gif';
        $exportedImage = $this->command(['git', '--git-dir=' . $this->remote, 'show', 'main:' . $imagePath]);
        $this->assertSame(MediaStorage::MAX_BYTES, strlen($exportedImage));
        $this->assertSame(hash('sha256', $gif), hash('sha256', $exportedImage));
        $pageContent = $this->command(['git', '--git-dir=' . $this->remote, 'show', 'main:wikis/media/guides/start.md']);
        $this->assertStringContainsString('src="../_media/' . $media['sha256'] . '.gif"', $pageContent);
    }

    private function command(array $arguments): string
    {
        $process = new Process($arguments);
        $process->mustRun();

        return $process->getOutput();
    }
}
