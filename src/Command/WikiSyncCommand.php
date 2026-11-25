<?php
declare(strict_types=1);

namespace App\Command;

use App\Service\GitSync;
use App\Service\GitSyncException;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Datasource\ConnectionManager;

class WikiSyncCommand extends Command
{
    /**
     * Push queued revisions from the shared Git repository.
     *
     * @param \Cake\Console\Arguments $args Arguments.
     * @param \Cake\Console\ConsoleIo $io Console output.
     * @return int
     */
    public function execute(Arguments $args, ConsoleIo $io): int
    {
        $db = ConnectionManager::get('default');
        try {
            $count = (new GitSync($db))->synchronize(100);
        } catch (GitSyncException $exception) {
            $io->error('Git synchronization failed: ' . $exception->getMessage());

            return static::CODE_ERROR;
        }
        $remaining = (int)$db->execute(
            'SELECT COUNT(*) FROM wiki_git_queue',
        )->fetchColumn(0);
        $io->out($count . ' items synchronized; ' . $remaining . ' remain queued.');

        return $remaining ? static::CODE_ERROR : static::CODE_SUCCESS;
    }
}
