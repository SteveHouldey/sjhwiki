<?php
declare(strict_types=1);

namespace App\Command;

use App\Service\PageWriter;
use App\Service\RichText;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Datasource\ConnectionManager;
use Cake\Http\Exception\ConflictException;
use InvalidArgumentException;

class WikiMigrateContentCommand extends Command
{
    /**
     * Convert current Markdown pages while retaining their original revisions.
     *
     * @param \Cake\Console\Arguments $args Arguments.
     * @param \Cake\Console\ConsoleIo $io Console output.
     * @return int
     */
    public function execute(Arguments $args, ConsoleIo $io): int
    {
        $db = ConnectionManager::get('default');
        $writer = new PageWriter($db);
        $converted = 0;
        $failed = 0;
        $lastId = 0;
        do {
            $pages = $db->execute(
                "SELECT * FROM wiki_pages WHERE content_format = 'markdown' AND id > ? ORDER BY id LIMIT 100",
                [$lastId],
            )->fetchAll('assoc');
            $pageCount = count($pages);
            foreach ($pages as $page) {
                $lastId = (int)$page['id'];
                try {
                    $html = PageWriter::body(RichText::markdown($page['body']));
                    $writer->change(
                        $page,
                        null,
                        $page['title'],
                        $html,
                        $page['deleted'] ? 'delete' : 'edit',
                        (int)$page['version'],
                    );
                    $converted++;
                } catch (InvalidArgumentException | ConflictException $exception) {
                    $io->error('Page ' . $page['id'] . ' was not converted: ' . $exception->getMessage());
                    $failed++;
                }
            }
        } while ($pageCount === 100);
        $io->out($converted . ' pages converted; ' . $failed . ' require attention. Original revisions were retained.');

        return $failed ? static::CODE_ERROR : static::CODE_SUCCESS;
    }
}
