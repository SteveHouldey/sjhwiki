<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\WebInstaller;
use Authentication\PasswordHasher\DefaultPasswordHasher;
use Cake\TestSuite\TestCase;
use InvalidArgumentException;
use PDO;
use RuntimeException;

class WebInstallerTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir() . '/wiki-installer-' . bin2hex(random_bytes(12));
        mkdir($this->directory, 0700);
        file_put_contents($this->directory . '/setup-token.txt', str_repeat('a', 64));
    }

    protected function tearDown(): void
    {
        foreach (['setup-token.txt', '.setup.lock', 'app_local.php', 'wiki.sqlite'] as $name) {
            $path = $this->directory . '/' . $name;
            if (is_file($path)) {
                unlink($path);
            }
        }
        rmdir($this->directory);
        parent::tearDown();
    }

    public function testInstallsAndCannotRunAgain(): void
    {
        $installer = new WebInstaller($this->directory, WWW_ROOT);
        $this->assertFalse($installer->authorize('wrong'));
        $this->assertTrue($installer->authorize(str_repeat('a', 64)));
        $this->assertTrue($installer->install($this->data()));
        $this->assertFalse($installer->enabled());
        $config = require $this->directory . '/app_local.php';
        $this->assertSame($this->directory . '/wiki.sqlite', $config['Datasources']['default']['database']);
        $this->assertSame('smtp.example.test', $config['EmailTransport']['default']['host']);
        $this->assertTrue($config['EmailTransport']['default']['tls']);
        $this->assertSame('https://wiki.example.test', $config['App']['fullBaseUrl']);
        $this->assertSame(0600, fileperms($this->directory . '/app_local.php') & 0777);
        $db = new PDO('sqlite:' . $this->directory . '/wiki.sqlite');
        $user = $db->query('SELECT * FROM users')->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('administrator', $user['username']);
        $this->assertTrue((bool)$user['is_admin']);
        $this->assertTrue((new DefaultPasswordHasher())->check('a long password', $user['password']));
        $this->assertSame(1, (int)$db->query('SELECT COUNT(*) FROM wiki_installation')->fetchColumn());
        $this->assertFalse($installer->authorize(str_repeat('a', 64)));
    }

    public function testRefusesNonEmptyDatabase(): void
    {
        $db = new PDO('sqlite:' . $this->directory . '/wiki.sqlite');
        $db->exec('CREATE TABLE existing_data (value TEXT)');
        $db->exec("INSERT INTO existing_data VALUES ('preserve')");
        $installer = new WebInstaller($this->directory, WWW_ROOT);
        try {
            $installer->install($this->data());
            $this->fail('A non-empty database must not be modified.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('not empty', $exception->getMessage());
        }
        $this->assertSame('preserve', $db->query('SELECT value FROM existing_data')->fetchColumn());
        $this->assertFileDoesNotExist($this->directory . '/app_local.php');
    }

    public function testInvalidTokenCannotCreateDatabase(): void
    {
        $data = $this->data();
        $data['token'] = str_repeat('b', 64);
        $installer = new WebInstaller($this->directory, WWW_ROOT);
        try {
            $installer->install($data);
            $this->fail('An invalid setup token must not install the application.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('invalid', $exception->getMessage());
        }
        $this->assertFileDoesNotExist($this->directory . '/wiki.sqlite');
        $this->assertFileDoesNotExist($this->directory . '/app_local.php');
    }

    public function testRejectsSqliteInsideWebroot(): void
    {
        $data = $this->data();
        $data['database'] = WWW_ROOT . 'installer-test.sqlite';
        $installer = new WebInstaller($this->directory, WWW_ROOT);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('outside webroot');
        $installer->install($data);
    }

    private function data(): array
    {
        return [
            'token' => str_repeat('a', 64),
            'base_url' => 'https://wiki.example.test',
            'database_type' => 'sqlite',
            'database' => $this->directory . '/wiki.sqlite',
            'smtp_host' => 'smtp.example.test',
            'smtp_port' => '587',
            'smtp_encryption' => 'tls',
            'smtp_username' => 'account',
            'smtp_password' => 'secret',
            'mail_from' => 'wiki@example.test',
            'username' => 'administrator',
            'email' => 'admin@example.test',
            'password' => 'a long password',
            'password_confirm' => 'a long password',
        ];
    }
}
