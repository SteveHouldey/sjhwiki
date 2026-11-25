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

        if (($values['smtp_host'] ?? '') !== '') {
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
            TransportFactory::drop('default');
            TransportFactory::setConfig('default', $transport);
            Mailer::drop('default');
            Mailer::setConfig('default', [
                'from' => $values['mail_from'] ?? Configure::read('Email.default.from'),
            ]);
        } elseif (($values['mail_from'] ?? '') !== '') {
            Mailer::drop('default');
            Mailer::setConfig('default', ['from' => $values['mail_from']]);
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
