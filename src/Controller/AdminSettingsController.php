<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\ApplicationSettings;
use Cake\Http\Response;
use Cake\Mailer\Mailer;
use InvalidArgumentException;

class AdminSettingsController extends AppController
{
    /** @return \Cake\Http\Response|null */
    public function index(): ?Response
    {
        $this->requireAdmin();
        $settings = new ApplicationSettings($this->db());
        $values = $settings->all();
        unset($values['smtp_password']);

        if ($this->request->is(['post', 'put'])) {
            try {
                $siteName = $this->text($this->request->getData('site_name'), 'Site name', 100);
                $tagline = $this->text($this->request->getData('site_tagline'), 'Site tagline', 180, true);
                $footerText = $this->text($this->request->getData('footer_text'), 'Footer text', 180, true);
                $baseUrl = $this->request->getData('full_base_url');
                $baseUrl = is_string($baseUrl) ? trim($baseUrl) : '';
                if ($baseUrl !== '') {
                    $url = parse_url($baseUrl);
                    if (!$url || !isset($url['scheme'], $url['host']) ||
                        !in_array($url['scheme'], ['http', 'https'], true) ||
                        isset($url['user']) || isset($url['pass']) || isset($url['query']) || isset($url['fragment'])
                    ) {
                        throw new InvalidArgumentException('Enter a valid HTTP or HTTPS public URL.');
                    }
                    $baseUrl = rtrim($baseUrl, '/');
                }

                $mailFrom = $this->request->getData('mail_from');
                $mailFrom = is_string($mailFrom) ? trim($mailFrom) : '';
                if ($mailFrom !== '' && filter_var($mailFrom, FILTER_VALIDATE_EMAIL) === false) {
                    throw new InvalidArgumentException('Enter a valid sender email address.');
                }
                $smtpHost = $this->request->getData('smtp_host');
                $smtpHost = is_string($smtpHost) ? trim($smtpHost) : '';
                if ($smtpHost !== '' && preg_match('/[\s\/:]/', $smtpHost)) {
                    throw new InvalidArgumentException('Enter an SMTP host without a scheme, port or whitespace.');
                }
                $smtpPort = $this->request->getData('smtp_port');
                if ($smtpHost !== '' && (!is_string($smtpPort) || !ctype_digit($smtpPort) ||
                    (int)$smtpPort < 1 || (int)$smtpPort > 65535)) {
                    throw new InvalidArgumentException('SMTP port must be between 1 and 65535.');
                }
                $smtpEncryption = $this->request->getData('smtp_encryption');
                if (!in_array($smtpEncryption, ['tls', 'ssl', 'none'], true)) {
                    throw new InvalidArgumentException('Select a valid SMTP encryption method.');
                }
                $smtpUser = $this->request->getData('smtp_username');
                $smtpUser = is_string($smtpUser) ? trim($smtpUser) : '';
                $smtpPassword = $this->request->getData('smtp_password');

                $save = [
                    'site_name' => $siteName,
                    'site_tagline' => $tagline,
                    'footer_text' => $footerText,
                    'registration_enabled' => $this->request->getData('registration_enabled') === '1' ? '1' : '0',
                    'require_verified_email' => $this->request->getData('require_verified_email') === '1' ? '1' : '0',
                    'full_base_url' => $baseUrl,
                    'mail_from' => $mailFrom,
                    'smtp_host' => $smtpHost,
                    'smtp_port' => (string)((int)($smtpPort ?: 587)),
                    'smtp_encryption' => (string)$smtpEncryption,
                    'smtp_username' => $smtpUser,
                ];
                if (is_string($smtpPassword) && $smtpPassword !== '') {
                    $save['smtp_password'] = $smtpPassword;
                }
                $settings->save($save);
                if ($baseUrl !== '') {
                    \Cake\Core\Configure::write('App.fullBaseUrl', $baseUrl);
                }
                $settings->applyRuntime();
                $this->Flash->success('Site settings saved.');

                return $this->redirect('/admin/settings');
            } catch (InvalidArgumentException $exception) {
                $this->Flash->error($exception->getMessage());
            }
        }

        $this->set('settings', $values);
        $this->set('smtpPasswordSet', ($settings->all()['smtp_password'] ?? '') !== '');

        return null;
    }

    /** @return string */
    private function text(mixed $value, string $label, int $max, bool $allowEmpty = false): string
    {
        if (!is_string($value)) {
            throw new InvalidArgumentException($label . ' is required.');
        }
        $value = trim($value);
        if (!$allowEmpty && $value === '') {
            throw new InvalidArgumentException($label . ' is required.');
        }
        if (mb_strlen($value) > $max) {
            throw new InvalidArgumentException($label . ' must be at most ' . $max . ' characters.');
        }

        return $value;
    }
}
