<?php
declare(strict_types=1);

namespace App\Service;

use Cake\Core\Configure;
use Cake\Database\Connection;
use Cake\Mailer\Mailer;
use Cake\Mailer\TransportFactory;
use Cake\Mailer\Transport\SmtpTransport;
use Cake\Utility\Security;
use InvalidArgumentException;

class ApplicationSettings
{
    public const DEFAULTS = [
        'site_name' => 'SJHWiki',
        'site_tagline' => 'Knowledge for everyone',
        'footer_text' => 'Created by Steven Houldey',
        'registration_enabled' => '1',
        'require_verified_email' => '1',
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
        'test_email_address' => '',
    ];

    /** @param \Cake\Database\Connection $db */
    public function __construct(private Connection $db)
    {
    }

    /** @return array<string, string> */
    public function all(): array
    {
        $values = self::DEFAULTS;
        if (!file_exists(CONFIG . 'app_local.php')) {
            return $values;
        }
        if (!in_array('application_settings', $this->db->getSchemaCollection()->listTables(), true)) {
            return $values;
        }
        $rows = $this->db->execute(
            'SELECT setting_key, setting_value, is_secret FROM application_settings',
        )->fetchAll('assoc');
        foreach ($rows as $row) {
            $key = (string)$row['setting_key'];
            $value = (string)($row['setting_value'] ?? '');
            if ((bool)$row['is_secret']) {
                $value = $this->decrypt($value);
            }
            $values[$key] = $value;
        }

        return $values;
    }

    /** @param array<string, string|int|bool|null> $values */
    public function save(array $values): void
    {
        $this->db->transactional(function () use ($values): void {
            foreach ($values as $key => $value) {
                if (!preg_match('/\A[a-z][a-z0-9_]{0,99}\z/D', $key)) {
                    throw new InvalidArgumentException('Invalid setting name.');
                }
                $normalized = $value === null ? null : (string)$value;
                $secret = str_ends_with($key, '_password');
                $stored = $secret && $normalized !== null && $normalized !== ''
                    ? $this->encrypt($normalized)
                    : $normalized;
                $existing = $this->db->execute(
                    'SELECT setting_key FROM application_settings WHERE setting_key = ?',
                    [$key],
                )->fetch('assoc');
                if ($existing) {
                    $this->db->update('application_settings', [
                        'setting_value' => $stored,
                        'is_secret' => (int)$secret,
                    ], ['setting_key' => $key]);
                } else {
                    $this->db->insert('application_settings', [
                        'setting_key' => $key,
                        'setting_value' => $stored,
                        'is_secret' => (int)$secret,
                    ]);
                }
            }
        });
    }

    public function applyRuntime(): void
    {
        $values = $this->all();
        Configure::write('App.siteName', $values['site_name']);
        Configure::write('App.siteTagline', $values['site_tagline']);
        Configure::write('App.footerText', $values['footer_text']);
        if (($values['full_base_url'] ?? '') !== '') {
            Configure::write('App.fullBaseUrl', $values['full_base_url']);
        }
        Configure::write('Wiki.registrationEnabled', $values['registration_enabled'] === '1');
        Configure::write('Wiki.requireVerifiedEmail', $values['require_verified_email'] === '1');
        foreach ([
            'landing_eyebrow', 'landing_title', 'landing_message',
            'landing_primary_label', 'landing_primary_url',
            'landing_secondary_label', 'landing_secondary_url',
            'feature_one_title', 'feature_one_message',
            'feature_two_title', 'feature_two_message',
            'feature_three_title', 'feature_three_message',
            'about_eyebrow', 'about_title', 'about_message', 'about_content',
        ] as $key) {
            $prefix = explode('_', $key)[0];
            $configuredKey = match ($prefix) {
                'landing' => 'Landing.' . str_replace(
                    ['primary_label', 'primary_url', 'secondary_label', 'secondary_url'],
                    ['primary.label', 'primary.url', 'secondary.label', 'secondary.url'],
                    substr($key, 8),
                ),
                'feature' => 'Features.' . str_replace([
                    'feature_one_', 'feature_two_', 'feature_three_',
                ], [
                    'one.', 'two.', 'three.',
                ], $key),
                'about' => 'About.' . substr($key, 6),
                default => 'App.' . $key,
            };
            Configure::write($configuredKey, $values[$key]);
        }

        if (($values['smtp_host'] ?? '') !== '') {
            $configuredTransport = in_array('default', TransportFactory::configured(), true)
                ? TransportFactory::getConfig('default')
                : null;
            $testTransport = is_array($configuredTransport)
                && str_contains((string)($configuredTransport['className'] ?? ''), 'TestEmailTransport');
            $transport = [
                'className' => SmtpTransport::class,
                'host' => ($values['smtp_encryption'] ?? '') === 'ssl'
                    ? 'ssl://' . $values['smtp_host']
                    : $values['smtp_host'],
                'port' => (int)($values['smtp_port'] ?? 587),
                'tls' => ($values['smtp_encryption'] ?? '') === 'tls',
                'username' => ($values['smtp_username'] ?? '') === '' ? null : $values['smtp_username'],
                'password' => ($values['smtp_password'] ?? '') === '' ? null : $values['smtp_password'],
                'url' => null,
            ];
            if (!$testTransport) {
                TransportFactory::drop('default');
                TransportFactory::setConfig('default', $transport);
            }
            Mailer::drop('default');
            Mailer::setConfig('default', [
                'from' => $values['mail_from'] ?? Configure::read('Email.default.from'),
                'transport' => 'default',
            ]);
        } elseif (($values['mail_from'] ?? '') !== '') {
            Mailer::drop('default');
            Mailer::setConfig('default', [
                'from' => $values['mail_from'],
                'transport' => 'default',
            ]);
        }
    }

    /** @return string */
    private function encrypt(string $plain): string
    {
        return base64_encode(Security::encrypt($plain, hash('sha256', Security::getSalt(), true)));
    }

    /** @return string */
    private function decrypt(string $ciphertext): string
    {
        if ($ciphertext === '') {
            return '';
        }
        try {
            $decoded = base64_decode($ciphertext, true);
            if ($decoded === false) {
                return '';
            }
            $plain = Security::decrypt($decoded, hash('sha256', Security::getSalt(), true));

            return $plain === null ? '' : $plain;
        } catch (\Throwable) {
            return '';
        }
    }
}
