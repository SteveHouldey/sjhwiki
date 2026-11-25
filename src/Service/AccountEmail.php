<?php
declare(strict_types=1);

namespace App\Service;

use Cake\Core\Configure;
use Cake\Database\Connection;
use Cake\Mailer\Mailer;
use Cake\Utility\Security;
use RuntimeException;

class AccountEmail
{
    /** @param \Cake\Database\Connection $db Database connection. */
    public function __construct(private Connection $db)
    {
    }

    /** @return bool */
    public function allowRequest(string $purpose, string $address, string $ip): bool
    {
        $now = time();
        $this->db->execute('DELETE FROM login_attempts WHERE created < ?', [gmdate('Y-m-d H:i:s', $now - 86400)]);
        $addressKey = hash_hmac('sha256', "email-$purpose\0$address", Security::getSalt());
        $ipKey = hash_hmac('sha256', "email-$purpose-ip\0$ip", Security::getSalt());
        $counts = $this->db->execute(
            'SELECT client_key, COUNT(*) AS attempts FROM login_attempts
             WHERE client_key IN (?, ?) AND created >= ? GROUP BY client_key',
            [$addressKey, $ipKey, gmdate('Y-m-d H:i:s', $now - 3600)],
        )->fetchAll('assoc');
        foreach ($counts as $row) {
            if ((int)$row['attempts'] >= ($row['client_key'] === $ipKey ? 30 : 5)) {
                return false;
            }
        }
        foreach ([$addressKey, $ipKey] as $key) {
            $this->db->insert('login_attempts', ['client_key' => $key, 'created' => gmdate('Y-m-d H:i:s', $now)]);
        }

        return true;
    }

    /** @return void */
    public function send(int $userId, string $purpose): void
    {
        if (!in_array($purpose, ['verify', 'reset'], true)) {
            throw new RuntimeException('Invalid email token purpose.');
        }
        $base = Configure::read('App.fullBaseUrl');
        $parts = is_string($base) ? parse_url($base) : false;
        if (
            !$parts || !isset($parts['scheme'], $parts['host']) ||
            !in_array($parts['scheme'], ['http', 'https'], true) ||
            isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment']) ||
            (!Configure::read('debug') && $parts['scheme'] !== 'https' &&
                !(Configure::read('App.allowInsecureLocalEmail') &&
                    in_array($parts['host'], ['localhost', '127.0.0.1', '[::1]'], true)))
        ) {
            throw new RuntimeException('Configure App.fullBaseUrl for account email links.');
        }

        $token = bin2hex(random_bytes(32));
        $email = $this->db->transactional(function () use ($userId, $purpose, $token): string {
            $this->db->execute('UPDATE users SET modified = modified WHERE id = ?', [$userId]);
            $user = $this->db->execute(
                'SELECT email, email_verified, active FROM users WHERE id = ?',
                [$userId],
            )->fetch('assoc');
            if (
                !$user || !$user['active'] || !$user['email'] ||
                ($purpose === 'reset' && !$user['email_verified']) ||
                ($purpose === 'verify' && $user['email_verified'])
            ) {
                throw new RuntimeException('Account is not eligible for this email.');
            }
            $this->db->delete('account_email_tokens', ['user_id' => $userId, 'purpose' => $purpose]);
            $this->db->execute('DELETE FROM account_email_tokens WHERE expires < ?', [gmdate('Y-m-d H:i:s')]);
            $this->db->insert('account_email_tokens', [
                'user_id' => $userId,
                'purpose' => $purpose,
                'token_hash' => hash('sha256', $token),
                'expires' => gmdate('Y-m-d H:i:s', time() + ($purpose === 'verify' ? 86400 : 3600)),
            ]);

            return $user['email'];
        });

        $path = $purpose === 'verify' ? 'verify-email' : 'reset-password';
        $link = rtrim($base, '/') . '/' . $path . '/' . $token;
        $subject = $purpose === 'verify' ? 'Verify your wiki email' : 'Reset your wiki password';
        $message = $purpose === 'verify'
            ? 'Confirm your email address to finish setting up your account.'
            : 'Use the link below to choose a new password for your account.';
        $action = $purpose === 'verify' ? 'Verify email' : 'Reset password';
        $expiry = $purpose === 'verify' ? '24 hours' : 'one hour';
        $mailer = new Mailer('default');
        $mailer->setTo($email)->setSubject($subject)->setEmailFormat('both');
        $mailer->setViewVars(compact('subject', 'message', 'action', 'expiry', 'link'));
        $mailer->viewBuilder()->setTemplate('account');
        $mailer->deliver();
    }

    /** @return bool */
    public function valid(string $token, string $purpose): bool
    {
        if (!preg_match('/\A[a-f0-9]{64}\z/D', $token) || !in_array($purpose, ['verify', 'reset'], true)) {
            return false;
        }
        $verification = $purpose === 'verify' ? 'u.email_verified IS NULL' : 'u.email_verified IS NOT NULL';

        return (bool)$this->db->execute(
            'SELECT t.id FROM account_email_tokens t INNER JOIN users u ON u.id = t.user_id
             WHERE t.token_hash = ? AND t.purpose = ? AND t.expires > ? AND u.active = 1
             AND u.email IS NOT NULL AND ' . $verification,
            [hash('sha256', $token), $purpose, gmdate('Y-m-d H:i:s')],
        )->fetch('assoc');
    }

    /** @return bool */
    public function complete(string $token, string $purpose, ?string $passwordHash = null): bool
    {
        if (!$this->valid($token, $purpose) || ($purpose === 'reset' && $passwordHash === null)) {
            return false;
        }

        return $this->db->transactional(function () use ($token, $purpose, $passwordHash): bool {
            $hash = hash('sha256', $token);
            $row = $this->db->execute(
                'SELECT user_id FROM account_email_tokens WHERE token_hash = ? AND purpose = ?',
                [$hash, $purpose],
            )->fetch('assoc');
            if (!$row) {
                return false;
            }
            $userId = (int)$row['user_id'];
            $this->db->execute('UPDATE users SET modified = modified WHERE id = ?', [$userId]);
            if (!$this->valid($token, $purpose)) {
                return false;
            }
            $deleted = $this->db->execute(
                'DELETE FROM account_email_tokens WHERE token_hash = ? AND purpose = ? AND expires > ?',
                [$hash, $purpose, gmdate('Y-m-d H:i:s')],
            )->rowCount();
            if ($deleted !== 1) {
                return false;
            }
            $this->db->delete('account_email_tokens', ['user_id' => $userId, 'purpose' => $purpose]);
            if ($purpose === 'verify') {
                $this->db->update('users', ['email_verified' => gmdate('Y-m-d H:i:s')], ['id' => $userId]);
            } else {
                $this->db->execute(
                    'UPDATE users SET password = ?, auth_version = auth_version + 1, modified = ? WHERE id = ?',
                    [$passwordHash, gmdate('Y-m-d H:i:s'), $userId],
                );
            }

            return true;
        });
    }
}
