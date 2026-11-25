<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class AddApplicationSettings extends BaseMigration
{
    /** @return void */
    public function change(): void
    {
        $this->table('application_settings')
            ->addColumn('setting_key', 'string', ['limit' => 100])
            ->addColumn('setting_value', 'text', ['null' => true])
            ->addColumn('is_secret', 'boolean', ['default' => false])
            ->addIndex(['setting_key'], ['unique' => true])
            ->create();

        $this->execute(
            "INSERT INTO application_settings (setting_key, setting_value, is_secret) VALUES
             ('site_name', 'SJHWiki', 0),
             ('site_tagline', 'Knowledge for everyone', 0),
             ('footer_text', 'Created by Steven Houldey', 0),
             ('registration_enabled', '1', 0),
             ('require_verified_email', '1', 0)",
        );
    }
}
