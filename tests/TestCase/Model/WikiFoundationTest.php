<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model;

use Cake\Database\Exception\QueryException;
use Cake\Datasource\ConnectionManager;
use Cake\TestSuite\TestCase;

class WikiFoundationTest extends TestCase
{
    public function testPagePathsAreUniqueWithinEachWiki(): void
    {
        $connection = ConnectionManager::get('test');
        $connection->begin();

        try {
            $now = '2026-01-01 00:00:00';
            $suffix = bin2hex(random_bytes(8));
            $firstWiki = $connection->insert('wikis', [
                'name' => 'First',
                'slug' => 'first-' . $suffix,
                'created' => $now,
                'modified' => $now,
            ]);
            $firstWikiId = $firstWiki->lastInsertId('wikis');
            $secondWiki = $connection->insert('wikis', [
                'name' => 'Second',
                'slug' => 'second-' . $suffix,
                'created' => $now,
                'modified' => $now,
            ]);
            $secondWikiId = $secondWiki->lastInsertId('wikis');
            $page = [
                'wiki_id' => $firstWikiId,
                'path' => 'guide/start',
                'title' => 'Start',
                'body' => 'Content',
                'created' => $now,
                'modified' => $now,
            ];
            $connection->insert('wiki_pages', $page);
            $page['wiki_id'] = $secondWikiId;
            $connection->insert('wiki_pages', $page);

            $page['wiki_id'] = $firstWikiId;
            $this->expectException(QueryException::class);
            $connection->insert('wiki_pages', $page);
        } finally {
            $connection->rollback();
        }
    }
}
