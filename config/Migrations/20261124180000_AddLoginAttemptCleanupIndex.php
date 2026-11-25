<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class AddLoginAttemptCleanupIndex extends BaseMigration
{
    /**
     * Index old sign-in attempts for cleanup.
     *
     * @return void
     */
    public function change(): void
    {
        $this->table('login_attempts')->addIndex(['created'])->update();
    }
}
