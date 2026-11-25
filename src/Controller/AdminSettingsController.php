<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\ApplicationSettings;
use App\Service\RichText;
use Cake\Http\Response;
use Cake\Mailer\Mailer;
use Cake\Mailer\TransportFactory;
use Throwable;
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
                $landingEyebrow = $this->text($this->request->getData('landing_eyebrow'), 'Landing eyebrow', 100);
                $landingTitle = $this->text($this->request->getData('landing_title'), 'Landing title', 180);
                $landingMessage = $this->text($this->request->getData('landing_message'), 'Landing message', 500);
                $landingPrimaryLabel = $this->text($this->request->getData('landing_primary_label'), 'Primary button label', 50);
                $landingPrimaryUrl = $this->url($this->request->getData('landing_primary_url'), 'Primary button URL');
                $landingSecondaryLabel = $this->text($this->request->getData('landing_secondary_label'), 'Secondary button label', 50);
                $landingSecondaryUrl = $this->url($this->request->getData('landing_secondary_url'), 'Secondary button URL');
                $featureOneTitle = $this->text($this->request->getData('feature_one_title'), 'Feature one title', 100);
                $featureOneMessage = $this->text($this->request->getData('feature_one_message'), 'Feature one message', 300);
                $featureTwoTitle = $this->text($this->request->getData('feature_two_title'), 'Feature two title', 100);
                $featureTwoMessage = $this->text($this->request->getData('feature_two_message'), 'Feature two message', 300);
                $featureThreeTitle = $this->text($this->request->getData('feature_three_title'), 'Feature three title', 100);
                $featureThreeMessage = $this->text($this->request->getData('feature_three_message'), 'Feature three message', 300);
                $aboutEyebrow = $this->text($this->request->getData('about_eyebrow'), 'About eyebrow', 100);
                $aboutTitle = $this->text($this->request->getData('about_title'), 'About title', 180);
                $aboutMessage = $this->text($this->request->getData('about_message'), 'About message', 500);
                $aboutContent = $this->request->getData('about_content');
                if (!is_string($aboutContent) || trim($aboutContent) === '') {
                    throw new InvalidArgumentException('About page content is required.');
                }
                $aboutContent = RichText::sanitize($aboutContent);
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
                    'landing_eyebrow' => $landingEyebrow,
                    'landing_title' => $landingTitle,
                    'landing_message' => $landingMessage,
                    'landing_primary_label' => $landingPrimaryLabel,
                    'landing_primary_url' => $landingPrimaryUrl,
                    'landing_secondary_label' => $landingSecondaryLabel,
                    'landing_secondary_url' => $landingSecondaryUrl,
                    'feature_one_title' => $featureOneTitle,
                    'feature_one_message' => $featureOneMessage,
                    'feature_two_title' => $featureTwoTitle,
                    'feature_two_message' => $featureTwoMessage,
                    'feature_three_title' => $featureThreeTitle,
                    'feature_three_message' => $featureThreeMessage,
                    'about_eyebrow' => $aboutEyebrow,
                    'about_title' => $aboutTitle,
                    'about_message' => $aboutMessage,
                    'about_content' => $aboutContent,
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

    /** @return \Cake\Http\Response */
    public function testEmail(): Response
    {
        $this->request->allowMethod(['post']);
        $this->requireAdmin();

        $address = $this->request->getData('test_email_address');
        $address = is_string($address) ? trim($address) : '';
        if ($address === '' || filter_var($address, FILTER_VALIDATE_EMAIL) === false) {
            $this->Flash->error('Enter a valid test email address.');

            if ($this->isAjaxTestEmailRequest()) {
                return $this->response
                    ->withStatus(422)
                    ->withType('application/json')
                    ->withStringBody(json_encode([
                        'success' => false,
                        'message' => 'Enter a valid test email recipient.',
                    ], JSON_THROW_ON_ERROR));
            }
            return $this->redirect('/admin/settings');
        }

        $settings = new ApplicationSettings($this->db());
        $settings->save(['test_email_address' => $address]);
        $transportConfig = in_array('default', TransportFactory::configured(), true)
            ? TransportFactory::getConfig('default')
            : null;
        $isTestTransport = is_array($transportConfig)
            && str_contains((string)($transportConfig['className'] ?? ''), 'TestEmailTransport');
        if (!$isTestTransport) {
            $settings->applyRuntime();
        }

        try {
            $values = $settings->all();
            $sender = (string)($values['mail_from'] ?? '');
            if ($sender === '') {
                throw new \RuntimeException('Configure a sender email address before sending a test email.');
            }
            $mailer = new Mailer('default');
            $mailer->setTo($address)->setSubject('SJHWiki test email')->setEmailFormat('both');
            $subject = 'SJHWiki test email';
            $message = 'This test email confirms that your SJHWiki SMTP configuration is working.';
            $action = 'Open SJHWiki';
            $expiry = 'This message does not expire';
            $link = (string)($values['full_base_url'] ?? \Cake\Core\Configure::read('App.fullBaseUrl'));
            $mailer->setViewVars(compact('subject', 'message', 'action', 'expiry', 'link'));
            $mailer->viewBuilder()->setTemplate('account');
            $mailer->deliver();
            $this->Flash->success('Test email sent to ' . $address . '.');
            if ($this->isAjaxTestEmailRequest()) {
                return $this->response
                    ->withStatus(200)
                    ->withType('application/json')
                    ->withStringBody(json_encode([
                        'success' => true,
                        'message' => 'Test email sent to ' . $address . '.',
                    ], JSON_THROW_ON_ERROR));
            }
        } catch (Throwable $exception) {
            $this->Flash->error('Test email could not be sent. Check SMTP settings and server logs.');
            if ($this->isAjaxTestEmailRequest()) {
                return $this->response
                    ->withStatus(422)
                    ->withType('application/json')
                    ->withStringBody(json_encode([
                        'success' => false,
                        'message' => 'Test email could not be sent. Check SMTP settings and server logs.',
                    ], JSON_THROW_ON_ERROR));
            }
        }

        return $this->redirect('/admin/settings');
    }

    /** @return bool */
    private function isAjaxTestEmailRequest(): bool
    {
        return $this->request->getHeaderLine('X-Requested-With') === 'XMLHttpRequest' ||
            str_contains($this->request->getHeaderLine('Accept'), 'application/json');
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

    /** @return string */
    private function url(mixed $value, string $label): string
    {
        if (!is_string($value)) {
            throw new InvalidArgumentException($label . ' is required.');
        }
        $value = trim($value);
        if ($value === '') {
            throw new InvalidArgumentException($label . ' is required.');
        }
        if (str_starts_with($value, '/') || str_starts_with($value, '#')) {
            if (preg_match('/[\r\n]/', $value)) {
                throw new InvalidArgumentException($label . ' is invalid.');
            }
            return $value;
        }
        $url = parse_url($value);
        if (
            !$url || !isset($url['scheme'], $url['host']) ||
            !in_array($url['scheme'], ['http', 'https'], true) ||
            isset($url['user']) || isset($url['pass']) ||
            isset($url['query']) || isset($url['fragment'])
        ) {
            throw new InvalidArgumentException($label . ' must be a local path or valid HTTP/HTTPS URL.');
        }

        return rtrim($value, '/');
    }
}
