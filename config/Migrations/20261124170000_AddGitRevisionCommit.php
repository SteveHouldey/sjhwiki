<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class AddGitRevisionCommit extends BaseMigration
{
    /**
     * Store the local commit for safe retry after a failed push.
     *
     * @return void
     */
    public function change(): void
    {
        $this->table('wiki_page_revisions')
            ->addColumn('git_commit', 'string', ['limit' => 64, 'null' => true])
            ->update();
    }
}
