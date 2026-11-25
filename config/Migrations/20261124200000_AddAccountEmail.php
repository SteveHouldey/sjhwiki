<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class AddAccountEmail extends BaseMigration
{
    /** @return void */
    public function change(): void
    {
        $this->table('users')
            ->addColumn('email', 'string', ['limit' => 254, 'null' => true])
            ->addColumn('email_verified', 'datetime', ['null' => true])
            ->addColumn('verification_required', 'boolean', ['default' => false])
            ->addIndex(['email'], ['unique' => true])
            ->update();

        $this->table('account_email_tokens')
            ->addColumn('user_id', 'integer')
            ->addColumn('purpose', 'string', ['limit' => 16])
            ->addColumn('token_hash', 'string', ['limit' => 64])
            ->addColumn('expires', 'datetime')
            ->addIndex(['token_hash'], ['unique' => true])
            ->addIndex(['user_id', 'purpose'])
            ->addIndex(['expires'])
            ->addForeignKey('user_id', 'users', 'id', ['delete' => 'CASCADE'])
            ->create();
    }
}
