<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class CreateWikiFoundation extends BaseMigration
{
    /**
     * Create the initial wiki tables.
     *
     * @return void
     */
    public function change(): void
    {
        $this->table('users')
            ->addColumn('username', 'string', ['limit' => 100])
            ->addColumn('password', 'string', ['limit' => 255])
            ->addColumn('is_admin', 'boolean', ['default' => false])
            ->addColumn('created', 'datetime')
            ->addColumn('modified', 'datetime')
            ->addIndex(['username'], ['unique' => true])
            ->create();

        $this->table('wikis')
            ->addColumn('name', 'string', ['limit' => 150])
            ->addColumn('slug', 'string', ['limit' => 100])
            ->addColumn('is_public', 'boolean', ['default' => false])
            ->addColumn('created', 'datetime')
            ->addColumn('modified', 'datetime')
            ->addIndex(['slug'], ['unique' => true])
            ->create();

        $this->table('wiki_roles')
            ->addColumn('wiki_id', 'integer')
            ->addColumn('name', 'string', ['limit' => 100])
            ->addIndex(['wiki_id', 'name'], ['unique' => true])
            ->addForeignKey('wiki_id', 'wikis', 'id', ['delete' => 'CASCADE'])
            ->create();

        $this->table('wiki_role_permissions')
            ->addColumn('wiki_role_id', 'integer')
            ->addColumn('permission', 'string', ['limit' => 100])
            ->addIndex(['wiki_role_id', 'permission'], ['unique' => true])
            ->addForeignKey('wiki_role_id', 'wiki_roles', 'id', ['delete' => 'CASCADE'])
            ->create();

        $this->table('wiki_role_members')
            ->addColumn('wiki_role_id', 'integer')
            ->addColumn('user_id', 'integer')
            ->addIndex(['wiki_role_id', 'user_id'], ['unique' => true])
            ->addForeignKey('wiki_role_id', 'wiki_roles', 'id', ['delete' => 'CASCADE'])
            ->addForeignKey('user_id', 'users', 'id', ['delete' => 'CASCADE'])
            ->create();

        $this->table('wiki_pages')
            ->addColumn('wiki_id', 'integer')
            ->addColumn('path', 'string', ['limit' => 190])
            ->addColumn('title', 'string', ['limit' => 255])
            ->addColumn('body', 'text')
            ->addColumn('created', 'datetime')
            ->addColumn('modified', 'datetime')
            ->addIndex(['wiki_id', 'path'], ['unique' => true])
            ->addForeignKey('wiki_id', 'wikis', 'id', ['delete' => 'CASCADE'])
            ->create();

        $this->table('wiki_page_revisions')
            ->addColumn('wiki_page_id', 'integer')
            ->addColumn('user_id', 'integer', ['null' => true])
            ->addColumn('path', 'string', ['limit' => 190])
            ->addColumn('title', 'string', ['limit' => 255])
            ->addColumn('body', 'text')
            ->addColumn('created', 'datetime')
            ->addIndex(['wiki_page_id', 'created'])
            ->addForeignKey('wiki_page_id', 'wiki_pages', 'id', ['delete' => 'CASCADE'])
            ->addForeignKey('user_id', 'users', 'id', ['delete' => 'SET_NULL'])
            ->create();
    }
}
