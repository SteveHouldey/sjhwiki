<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class AddLandingAndAboutSettings extends BaseMigration
{
    /** @return void */
    public function change(): void
    {
        $settings = [
            'landing_eyebrow' => 'YOUR KNOWLEDGE, CONNECTED',
            'landing_title' => 'A better home for everything you know.',
            'landing_message' => 'Bring ideas, documentation and shared knowledge together in a wiki built for your team.',
            'landing_primary_label' => 'Sign in to your workspace',
            'landing_primary_url' => '/login',
            'landing_secondary_label' => 'Explore public wikis',
            'landing_secondary_url' => '#public-wikis',
            'feature_one_title' => 'Write naturally',
            'feature_one_message' => 'Create rich pages with formatting, tables and images.',
            'feature_two_title' => 'Share securely',
            'feature_two_message' => 'Keep private knowledge protected with per-wiki permissions.',
            'feature_three_title' => 'Keep your history',
            'feature_three_message' => 'Review earlier versions and synchronize your work with Git.',
            'about_eyebrow' => 'ABOUT THIS WIKI',
            'about_title' => 'Knowledge is better when it is shared.',
            'about_message' => 'Bring documentation, ideas and practical guides together in one accessible place.',
            'about_content' => '<p>Write about this wiki, its purpose, policies and community using the Admin Center editor.</p>',
        ];
        foreach ($settings as $key => $value) {
            $escaped = str_replace("'", "''", $value);
            $this->execute(
                "INSERT INTO application_settings (setting_key, setting_value, is_secret)
                 SELECT '" . $key . "', '" . $escaped . "', 0
                 WHERE NOT EXISTS
                 (SELECT 1 FROM application_settings WHERE setting_key = '" . $key . "')",
            );
        }
    }
}
