<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\AccountEmail;
use App\Service\AccountInput;
use App\Service\ApplicationSettings;
use Authentication\PasswordHasher\DefaultPasswordHasher;
use Cake\Http\Exception\NotFoundException;
use Cake\Http\Response;
use InvalidArgumentException;
use PDOException;

class AccountEmailsController extends AppController
{
    /** @return \Cake\Http\Response|null */
    public function register(): ?Response
    {
        $db = $this->db();
        $settings = new ApplicationSettings($db);
        $settings->applyRuntime();
        if (!\Cake\Core\Configure::read('Wiki.registrationEnabled', true)) {
            throw new NotFoundException();
        }
        if (!$db->execute('SELECT id FROM wiki_installation WHERE id = 1')->fetch('assoc')) {
            throw new NotFoundException();
        }
        if (!$this->request->is('post')) {
            return null;
        }
        try {
            $username = AccountInput::username($this->request->getData('username'));
            $email = AccountInput::email($this->request->getData('email'));
            $password = AccountInput::password($this->request->getData('password'));
            if ($password !== $this->request->getData('password_confirm')) {
                throw new InvalidArgumentException('Passwords do not match.');
            }
            if (!(new AccountEmail($db))->allowRequest('register', $email, (string)$this->request->clientIp())) {
                throw new InvalidArgumentException('Too many registration attempts. Try again later.');
            }
            if (
                $db->execute(
                    'SELECT id FROM users WHERE username = ? OR email = ?',
                    [$username, $email],
                )->fetch('assoc')
            ) {
                throw new InvalidArgumentException('Username or email is already in use.');
            }
        } catch (InvalidArgumentException $exception) {
            $this->Flash->error($exception->getMessage());

            return null;
        }
        $now = gmdate('Y-m-d H:i:s');
        try {
            $requireVerifiedEmail = \Cake\Core\Configure::read('Wiki.requireVerifiedEmail', true);
            $result = $db->insert('users', [
                'username' => $username,
                'email' => $email,
                'password' => (new DefaultPasswordHasher())->hash($password),
                'verification_required' => (int)$requireVerifiedEmail,
                'active' => 1,
                'is_admin' => 0,
                'created' => $now,
                'modified' => $now,
            ]);
        } catch (PDOException $exception) {
            if (
                !$db->execute(
                    'SELECT id FROM users WHERE username = ? OR email = ?',
                    [$username, $email],
                )->fetch('assoc')
            ) {
                throw $exception;
            }
            $this->Flash->error('Username or email is already in use.');

            return null;
        }
        if (!$this->sendAccountEmail((int)$result->lastInsertId('users'), 'verify')) {
            $this->Flash->error(
                'Account created, but the verification email could not be sent. Please request it again.',
            );

            return $this->redirect('/resend-verification');
        }
        $this->Flash->success('Account created. Check your email to verify it before signing in.');

        return $this->redirect('/login');
    }

    /** @return \Cake\Http\Response|null */
    public function resend(): ?Response
    {
        return $this->requestEmail('verify');
    }

    /** @return \Cake\Http\Response|null */
    public function forgot(): ?Response
    {
        return $this->requestEmail('reset');
    }

    /** @return \Cake\Http\Response|null */
    private function requestEmail(string $purpose): ?Response
    {
        if (!$this->request->is('post')) {
            return null;
        }
        try {
            $email = AccountInput::email($this->request->getData('email'));
            $service = new AccountEmail($this->db());
            if ($service->allowRequest($purpose, $email, (string)$this->request->clientIp())) {
                $verified = $purpose === 'verify' ? 'email_verified IS NULL' : 'email_verified IS NOT NULL';
                $user = $this->db()->execute(
                    'SELECT id FROM users WHERE email = ? AND active = 1 AND ' . $verified,
                    [$email],
                )->fetch('assoc');
                if ($user) {
                    $this->sendAccountEmail((int)$user['id'], $purpose);
                }
            }
        } catch (InvalidArgumentException) {
            // Use the same response for invalid and unknown addresses.
        }
        $this->Flash->success('If an eligible account exists, an email has been sent or can be requested later.');

        return $this->redirect($purpose === 'verify' ? '/resend-verification' : '/forgot-password');
    }

    /** @return \Cake\Http\Response|null */
    public function verify(string $token): ?Response
    {
        $service = new AccountEmail($this->db());
        $valid = $service->valid($token, 'verify');
        if ($this->request->is('post') && $valid) {
            if ($service->complete($token, 'verify')) {
                $this->Flash->success('Email verified. You can now sign in.');

                return $this->redirect('/login');
            }
            $valid = false;
        }
        $this->set(compact('valid'));

        return null;
    }

    /** @return \Cake\Http\Response|null */
    public function reset(string $token): ?Response
    {
        $service = new AccountEmail($this->db());
        $valid = $service->valid($token, 'reset');
        if ($this->request->is('post') && $valid) {
            try {
                $password = AccountInput::password($this->request->getData('password'));
                if ($password !== $this->request->getData('password_confirm')) {
                    throw new InvalidArgumentException('Passwords do not match.');
                }
                if ($service->complete($token, 'reset', (new DefaultPasswordHasher())->hash($password))) {
                    $this->Flash->success('Password changed. Sign in with your new password.');

                    return $this->redirect('/login');
                }
                $valid = false;
            } catch (InvalidArgumentException $exception) {
                $this->Flash->error($exception->getMessage());
            }
        }
        $this->set(compact('valid'));

        return null;
    }
}
