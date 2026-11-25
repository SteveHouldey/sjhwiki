<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class AddWikiApplicationState extends BaseMigration
{
    /**
     * Add application state needed by setup, revisions, and synchronization.
     *
     * @return void
     */
    public function change(): void
    {
        $this->table('users')
            ->addColumn('active', 'boolean', ['default' => true])
            ->addColumn('auth_version', 'integer', ['default' => 1])
            ->update();

        $this->table('wiki_pages')
            ->addColumn('deleted', 'boolean', ['default' => false])
            ->addColumn('version', 'integer', ['default' => 1])
            ->update();

        $this->table('wiki_page_revisions')
            ->addColumn('operation', 'string', ['limit' => 16, 'default' => 'edit'])
            ->addColumn('git_synced', 'boolean', ['default' => false])
            ->addColumn('git_error', 'string', ['limit' => 255, 'null' => true])
            ->addIndex(['git_synced', 'id'])
            ->update();

        $this->table('wiki_installation')
            ->addColumn('created', 'datetime')
            ->create();

        $this->table('login_attempts')
            ->addColumn('client_key', 'string', ['limit' => 64])
            ->addColumn('created', 'datetime')
            ->addIndex(['client_key', 'created'])
            ->create();
    }
}
