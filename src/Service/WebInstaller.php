<?php
declare(strict_types=1);

namespace App\Service;

use Authentication\PasswordHasher\DefaultPasswordHasher;
use Cake\Database\Connection;
use Cake\Database\Driver\Mysql;
use Cake\Database\Driver\Sqlite;
use Cake\Datasource\ConnectionManager;
use Cake\Mailer\Transport\SmtpTransport;
use InvalidArgumentException;
use Migrations\Migrations;
use RuntimeException;

class WebInstaller
{
    /** @param string $configDirectory Directory for installation configuration. */
    public function __construct(private string $configDirectory, private string $webroot)
    {
    }

    /** @return bool */
    public function enabled(): bool
    {
        return !file_exists($this->configDirectory . '/app_local.php') &&
            !getenv('DATABASE_URL') && is_file($this->configDirectory . '/setup-token.txt');
    }

    /** @return bool */
    public function authorize(mixed $value): bool
    {
        if (!$this->enabled() || !is_string($value)) {
            return false;
        }
        $token = file_get_contents($this->configDirectory . '/setup-token.txt');
        if (!is_string($token)) {
            throw new RuntimeException('Cannot read the setup token file.');
        }
        $token = trim($token);

        return (bool)preg_match('/\A[a-z0-9]{64}\z/D', $token) && hash_equals($token, $value);
    }

    /**
     * @param array $data Submitted setup values.
     * @return bool Whether the setup token was removed.
     */
    public function install(array $data): bool
    {
        $username = AccountInput::username($data['username'] ?? null);
        $email = AccountInput::email($data['email'] ?? null);
        $password = AccountInput::password($data['password'] ?? null);
        if ($password !== ($data['password_confirm'] ?? null)) {
            throw new InvalidArgumentException('Passwords do not match.');
        }

        $base = $this->required($data, 'base_url', 'Public URL');
        $url = parse_url($base);
        if (
            !$url || !isset($url['scheme'], $url['host']) ||
            ($url['scheme'] !== 'https' &&
                !($url['scheme'] === 'http' && in_array($url['host'], ['localhost', '127.0.0.1', '[::1]'], true))) ||
            isset($url['user']) || isset($url['pass']) || isset($url['query']) || isset($url['fragment'])
        ) {
            throw new InvalidArgumentException('Enter an HTTPS public URL (HTTP is allowed only for localhost).');
        }
        $database = $this->databaseConfig($data);
        $smtpHost = $this->required($data, 'smtp_host', 'SMTP host');
        if (preg_match('/[\s\/:]/', $smtpHost)) {
            throw new InvalidArgumentException('Enter an SMTP host without a scheme, port or whitespace.');
        }
        $smtpPort = $this->port($data['smtp_port'] ?? null, 'SMTP port');
        $encryption = $data['smtp_encryption'] ?? '';
        if (!in_array($encryption, ['tls', 'ssl', 'none'], true)) {
            throw new InvalidArgumentException('Select a valid SMTP encryption method.');
        }
        $sender = AccountInput::email($data['mail_from'] ?? null);
        $smtpUser = $data['smtp_username'] ?? '';
        $smtpPassword = $data['smtp_password'] ?? '';
        if (!is_string($smtpUser) || !is_string($smtpPassword) || ($smtpUser === '') !== ($smtpPassword === '')) {
            throw new InvalidArgumentException('Enter both SMTP username and password, or leave both empty.');
        }

        $lock = fopen($this->configDirectory . '/.setup.lock', 'c');
        if ($lock === false || !flock($lock, LOCK_EX)) {
            throw new RuntimeException('Cannot lock setup. Check that the config directory is writable.');
        }
        try {
            if (!$this->authorize($data['token'] ?? null)) {
                throw new RuntimeException('Setup is disabled or the setup token is invalid.');
            }
            if (ConnectionManager::getConfig('web_install')) {
                ConnectionManager::drop('web_install');
            }
            ConnectionManager::setConfig('web_install', $database);
            $db = ConnectionManager::get('web_install');
            if ($db->getSchemaCollection()->listTables()) {
                throw new InvalidArgumentException(
                    'The database is not empty. Setup will not modify an existing database.',
                );
            }
            (new Migrations(['connection' => 'web_install']))->migrate();
            $hash = (new DefaultPasswordHasher())->hash($password);
            $now = gmdate('Y-m-d H:i:s');
            $db->transactional(function () use ($db, $username, $email, $hash, $now): void {
                $db->insert('wiki_installation', ['id' => 1, 'created' => $now]);
                $db->insert('users', [
                    'username' => $username, 'email' => $email, 'password' => $hash,
                    'is_admin' => true, 'active' => true, 'created' => $now, 'modified' => $now,
                ]);
            });

            $cookieSuffix = bin2hex(random_bytes(8));
            $config = [
                'debug' => false,
                'Security' => ['salt' => bin2hex(random_bytes(32))],
                'App' => [
                    'fullBaseUrl' => rtrim($base, '/'), 'csrfCookieName' => 'wikiCsrf' . $cookieSuffix,
                    'allowInsecureLocalEmail' => $url['scheme'] === 'http',
                ],
                'Session' => [
                    'cookie' => 'WikiSession' . $cookieSuffix,
                    'ini' => ['session.cookie_secure' => $url['scheme'] === 'https'],
                ],
                'Datasources' => ['default' => $database + ['url' => null]],
                'EmailTransport' => ['default' => [
                    'className' => SmtpTransport::class,
                    'host' => ($encryption === 'ssl' ? 'ssl://' : '') . $smtpHost,
                    'port' => $smtpPort, 'tls' => $encryption === 'tls',
                    'username' => $smtpUser === '' ? null : $smtpUser,
                    'password' => $smtpPassword === '' ? null : $smtpPassword, 'url' => null,
                ]],
                'Email' => ['default' => ['from' => $sender]],
            ];
            $this->writeConfig($config);

            return unlink($this->configDirectory . '/setup-token.txt');
        } finally {
            if (ConnectionManager::getConfig('web_install')) {
                ConnectionManager::drop('web_install');
            }
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** @return array */
    private function databaseConfig(array $data): array
    {
        $type = $data['database_type'] ?? '';
        if ($type === 'sqlite') {
            if (!extension_loaded('pdo_sqlite')) {
                throw new InvalidArgumentException('The PHP pdo_sqlite extension is required for SQLite.');
            }
            $path = $this->required($data, 'database', 'SQLite database path');
            $directory = realpath(dirname($path));
            $webroot = realpath($this->webroot);
            $existing = file_exists($path) ? realpath($path) : false;
            if (
                !str_starts_with($path, '/') || !$directory || !$webroot || !is_writable($directory) ||
                str_contains(basename($path), DIRECTORY_SEPARATOR) ||
                $directory === $webroot || str_starts_with($directory, $webroot . DIRECTORY_SEPARATOR) ||
                ($existing && ($existing === $webroot || str_starts_with($existing, $webroot . DIRECTORY_SEPARATOR)))
            ) {
                throw new InvalidArgumentException(
                    'SQLite needs an absolute path in a writable directory outside webroot.',
                );
            }

            return [
                'className' => Connection::class, 'driver' => Sqlite::class, 'database' => $path,
                'init' => ['PRAGMA foreign_keys = ON'],
            ];
        }
        if ($type !== 'mysql') {
            throw new InvalidArgumentException('Select MySQL/MariaDB or SQLite.');
        }
        if (!extension_loaded('pdo_mysql')) {
            throw new InvalidArgumentException('The PHP pdo_mysql extension is required for MySQL/MariaDB.');
        }

        return [
            'className' => Connection::class, 'driver' => Mysql::class,
            'host' => $this->required($data, 'database_host', 'Database host'),
            'port' => $this->port($data['database_port'] ?? null, 'Database port'),
            'database' => $this->required($data, 'database', 'Database name'),
            'username' => $this->required($data, 'database_username', 'Database username'),
            'password' => is_string($data['database_password'] ?? null) ? $data['database_password'] : '',
            'encoding' => 'utf8mb4',
        ];
    }

    /** @return string */
    private function required(array $data, string $key, string $label): string
    {
        $value = $data[$key] ?? null;
        if (!is_string($value) || trim($value) === '' || strlen($value) > 1024) {
            throw new InvalidArgumentException($label . ' is required.');
        }

        return trim($value);
    }

    /** @return int */
    private function port(mixed $value, string $label): int
    {
        if (!is_string($value) || !ctype_digit($value) || (int)$value < 1 || (int)$value > 65535) {
            throw new InvalidArgumentException($label . ' must be between 1 and 65535.');
        }

        return (int)$value;
    }

    /** @return void */
    private function writeConfig(array $config): void
    {
        $temporary = $this->configDirectory . '/.setup-' . bin2hex(random_bytes(16)) . '.tmp';
        $file = fopen($temporary, 'x');
        if ($file === false) {
            throw new RuntimeException('Cannot create configuration. Check directory permissions.');
        }
        try {
            if (!chmod($temporary, 0600)) {
                throw new RuntimeException('Cannot protect the configuration file.');
            }
            $content = "<?php\nreturn " . var_export($config, true) . ";\n";
            if (fwrite($file, $content) !== strlen($content) || !fflush($file)) {
                throw new RuntimeException('Cannot write the complete configuration file.');
            }
            if (!link($temporary, $this->configDirectory . '/app_local.php')) {
                throw new RuntimeException('Cannot publish configuration; it may already exist.');
            }
        } finally {
            fclose($file);
            if (file_exists($temporary) && !unlink($temporary)) {
                throw new RuntimeException('Cannot remove temporary setup configuration.');
            }
        }
    }
}
