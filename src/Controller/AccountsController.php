<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\AccountInput;
use App\Service\ApplicationSettings;
use App\Service\WebInstaller;
use Authentication\PasswordHasher\DefaultPasswordHasher;
use Cake\Http\Exception\ForbiddenException;
use Cake\Http\Exception\NotFoundException;
use Cake\Http\Response;
use Cake\Log\Log;
use Cake\Utility\Security;
use InvalidArgumentException;
use Throwable;

class AccountsController extends AppController
{
    /** @return \Cake\Http\Response|null */
    public function setup(): ?Response
    {
        $installer = new WebInstaller(CONFIG, WWW_ROOT);
        if ($installer->enabled()) {
            return $this->webSetup($installer);
        }
        $db = $this->db();
        $installed = $db->execute('SELECT id FROM wiki_installation WHERE id = 1')->fetch('assoc');
        $existingUser = $db->execute('SELECT id FROM users LIMIT 1')->fetch('assoc');
        if ($installed || $existingUser) {
            throw new NotFoundException();
        }
        $configuredToken = getenv('WIKI_SETUP_TOKEN');
        $setupEnabled = is_string($configuredToken) && strlen($configuredToken) >= 32;
        $this->set('setupEnabled', $setupEnabled);

        if (!$this->request->is('post')) {
            return null;
        }
        if (!$setupEnabled) {
            throw new ForbiddenException('First-run setup is not enabled.');
        }
        $token = $this->request->getData('token');
        if (!is_string($token) || !hash_equals($configuredToken, $token)) {
            $this->Flash->error('Invalid setup token.');

            return null;
        }

        try {
            $username = AccountInput::username($this->request->getData('username'));
            $email = $this->request->getData('email');
            $email = $email === '' || $email === null ? null : AccountInput::email($email);
            $password = AccountInput::password($this->request->getData('password'));
            if ($password !== $this->request->getData('password_confirm')) {
                throw new InvalidArgumentException('Passwords do not match.');
            }
        } catch (InvalidArgumentException $exception) {
            $this->Flash->error($exception->getMessage());

            return null;
        }

        $hash = (new DefaultPasswordHasher())->hash($password);
        $now = gmdate('Y-m-d H:i:s');
        $userId = $db->transactional(function () use ($db, $username, $email, $hash, $now): int {
            $db->insert('wiki_installation', ['id' => 1, 'created' => $now]);
            if ($db->execute('SELECT id FROM users LIMIT 1')->fetch('assoc')) {
                throw new ForbiddenException('Setup is already complete.');
            }
            $result = $db->insert('users', [
                'username' => $username,
                'email' => $email,
                'password' => $hash,
                'is_admin' => 1,
                'active' => 1,
                'created' => $now,
                'modified' => $now,
            ]);

            return (int)$result->lastInsertId('users');
        });

        if ($email && !$this->sendAccountEmail($userId, 'verify')) {
            $this->Flash->error(
                'Administrator created, but the verification email could not be sent. Please request it again.',
            );

            return $this->redirect('/login');
        }

        $this->Flash->success('Administrator created. Sign in to continue.');

        return $this->redirect('/login');
    }

    /** @return \Cake\Http\Response|null */
    private function webSetup(WebInstaller $installer): ?Response
    {
        $host = strtolower($this->request->getUri()->getHost());
        $local = in_array($host, ['localhost', '127.0.0.1', '[::1]'], true) &&
            in_array($this->request->clientIp(), ['127.0.0.1', '::1'], true);
        if ($this->request->getUri()->getScheme() !== 'https' && !$local) {
            throw new ForbiddenException('Use HTTPS to run setup.');
        }
        $this->set('webSetup', true);
        $this->set('hideSidebar', true);
        $problems = [];
        $documentRoot = $this->request->getServerParams()['DOCUMENT_ROOT'] ?? null;
        if (is_string($documentRoot) && $documentRoot !== '' && realpath($documentRoot) !== realpath(WWW_ROOT)) {
            $problems[] = 'Point the website document root at the application webroot directory.';
        }
        foreach (['dom', 'fileinfo', 'intl', 'mbstring', 'openssl', 'pdo'] as $extension) {
            if (!extension_loaded($extension)) {
                $problems[] = 'Enable the PHP ' . $extension . ' extension.';
            }
        }
        foreach ([CONFIG, TMP, CACHE, CACHE . 'models', CACHE . 'persistent', LOGS, RESOURCES] as $directory) {
            if (!is_dir($directory) || !is_writable($directory)) {
                $problems[] = 'Make this directory writable by PHP: ' . $directory;
            }
        }
        foreach (['css/bootstrap.min.css', 'js/wiki-editor.js', 'js/wiki-highlight.js'] as $asset) {
            if (!is_file(WWW_ROOT . $asset)) {
                $problems[] = 'Upload the built asset: webroot/' . $asset;
            }
        }
        if (!is_file(RESOURCES . 'highlight-languages.json')) {
            $problems[] = 'Upload the built asset: resources/highlight-languages.json';
        }
        $this->set('setupProblems', $problems);
        if (!$this->request->is('post')) {
            return null;
        }
        if ($problems) {
            $this->Flash->error('Resolve the server requirements before installing.');

            return null;
        }
        if (!$installer->authorize($this->request->getData('token'))) {
            $tokenFile = CONFIG . 'setup-token.txt';
            $token = is_file($tokenFile) ? trim((string)file_get_contents($tokenFile)) : '';
            if (!preg_match('/\A[a-f0-9]{64}\z/D', $token)) {
                $this->Flash->error(
                    'Invalid setup token file. Replace config/setup-token.txt with exactly 64 lowercase hexadecimal characters.',
                );
            } else {
                $this->Flash->error('Invalid setup token.');
            }

            return null;
        }

        $data = $this->request->getData();
        $base = is_string($data['base_url'] ?? null) ? parse_url($data['base_url']) : false;
        $uri = $this->request->getUri();
        $scheme = $uri->getScheme();
        $port = $uri->getPort() ?? ($scheme === 'https' ? 443 : 80);
        $basePort = $base['port'] ?? (($base['scheme'] ?? '') === 'https' ? 443 : 80);
        if (
            !$base || strtolower($base['host'] ?? '') !== $host || ($base['scheme'] ?? '') !== $scheme ||
            $basePort !== $port ||
            rtrim($base['path'] ?? '', '/') !== rtrim((string)$this->request->getAttribute('base', ''), '/')
        ) {
            $this->Flash->error('The public URL must match the address and application path of this setup page.');

            return null;
        }
        try {
            $removed = $installer->install($data);
        } catch (InvalidArgumentException $exception) {
            $this->Flash->error($exception->getMessage());

            return null;
        } catch (Throwable $exception) {
            Log::error('Web installation failed (' . $exception::class . ').');
            $this->Flash->error(
                'Setup failed. Check the server logs and database settings. ' .
                'If tables were created, use a new empty database before retrying.',
            );

            return null;
        }

        return $this->redirect('/login?setup=' . ($removed ? 'complete' : 'remove-token'));
    }

    /** @return \Cake\Http\Response|null */
    public function login(): ?Response
    {
        if (!$this->request->is('post')) {
            return null;
        }
        $username = $this->request->getData('username');
        $password = $this->request->getData('password');
        if (!is_string($username) || !is_string($password) || strlen($username) > 100 || strlen($password) > 72) {
            $this->Flash->error('Invalid username or password.');

            return null;
        }

        $db = $this->db();
        $ip = (string)$this->request->clientIp();
        $accountKey = hash_hmac('sha256', "account\0" . strtolower($username), Security::getSalt());
        $pairKey = hash_hmac('sha256', "pair\0" . $ip . "\0" . strtolower($username), Security::getSalt());
        $ipKey = hash_hmac('sha256', "ip\0" . $ip, Security::getSalt());
        $cutoff = gmdate('Y-m-d H:i:s', time() - 900);
        $db->execute('DELETE FROM login_attempts WHERE created < ?', [gmdate('Y-m-d H:i:s', time() - 86400)]);
        $count = $db->execute(
            'SELECT client_key, COUNT(*) AS attempts FROM login_attempts
            WHERE client_key IN (?, ?, ?) AND created >= ? GROUP BY client_key',
            [$accountKey, $pairKey, $ipKey, $cutoff],
        )->fetchAll('assoc');
        foreach ($count as $row) {
            $limit = match ($row['client_key']) {
                $ipKey => 40,
                $accountKey => 20,
                default => 5,
            };
            if ((int)$row['attempts'] >= $limit) {
                $this->Flash->error('Too many sign-in attempts. Try again later.');

                return null;
            }
        }

        $user = $db->execute('SELECT * FROM users WHERE username = ?', [$username])->fetch('assoc');
        $hash = $user['password'] ?? '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
        $valid = (new DefaultPasswordHasher())->check($password, $hash);
        if (!$user || !$user['active'] || ($user['verification_required'] && !$user['email_verified']) || !$valid) {
            $now = gmdate('Y-m-d H:i:s');
            $db->insert('login_attempts', ['client_key' => $accountKey, 'created' => $now]);
            $db->insert('login_attempts', ['client_key' => $pairKey, 'created' => $now]);
            $db->insert('login_attempts', ['client_key' => $ipKey, 'created' => $now]);
            $this->Flash->error('Invalid username or password.');

            return null;
        }

        $db->delete('login_attempts', ['client_key' => $accountKey]);
        $db->delete('login_attempts', ['client_key' => $pairKey]);
        $this->Authentication->setIdentity($user);

        return $this->redirect('/');
    }

    /** @return \Cake\Http\Response */
    public function logout(): Response
    {
        $this->request->allowMethod(['post']);
        $this->Authentication->logout();

        return $this->redirect('/');
    }
}
