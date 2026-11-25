<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Authentication\PasswordHasher\DefaultPasswordHasher;
use Cake\Datasource\ConnectionManager;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\EmailTrait;
use Cake\TestSuite\TestCase;

class AccountsControllerTest extends TestCase
{
    use IntegrationTestTrait;
    use EmailTrait;

    protected function setUp(): void
    {
        parent::setUp();
        ConnectionManager::get('test')->begin();
        $this->enableCsrfToken();
        putenv('WIKI_SETUP_TOKEN=0123456789abcdef0123456789abcdef');
    }

    protected function tearDown(): void
    {
        ConnectionManager::get('test')->rollback();
        putenv('WIKI_SETUP_TOKEN');
        parent::tearDown();
    }

    public function testGlobalAdminCanSaveAndAccessAdminCenter(): void
    {
        $db = ConnectionManager::get('test');
        $now = gmdate('Y-m-d H:i:s');
        $id = (int)$db->insert('users', [
            'username' => 'global-admin',
            'password' => (new DefaultPasswordHasher())->hash('a long password'),
            'is_admin' => 1,
            'active' => 1,
            'created' => $now,
            'modified' => $now,
        ])->lastInsertId('users');
        $this->session(['Auth' => $id, 'AuthVersion' => 1]);

        $this->post('/admin/settings', [
            'site_name' => 'Acme Knowledge',
            'site_tagline' => 'Internal knowledge',
            'footer_text' => 'Acme documentation',
            'full_base_url' => 'https://wiki.example.test',
            'registration_enabled' => '1',
            'require_verified_email' => '1',
            'mail_from' => 'wiki@example.test',
            'smtp_host' => '',
            'smtp_port' => '587',
            'smtp_encryption' => 'tls',
            'smtp_username' => '',
            'smtp_password' => '',
            'landing_eyebrow' => 'YOUR KNOWLEDGE',
            'landing_title' => 'Welcome to Acme',
            'landing_message' => 'Find and share documentation.',
            'landing_primary_label' => 'Sign in',
            'landing_primary_url' => '/login',
            'landing_secondary_label' => 'Browse',
            'landing_secondary_url' => '#public-wikis',
            'feature_one_title' => 'Write naturally',
            'feature_one_message' => 'Create rich pages.',
            'feature_two_title' => 'Share securely',
            'feature_two_message' => 'Control wiki access.',
            'feature_three_title' => 'Keep history',
            'feature_three_message' => 'Review revisions.',
            'about_eyebrow' => 'ABOUT ACME',
            'about_title' => 'Knowledge shared',
            'about_message' => 'Documentation for everyone.',
            'about_content' => '<p>Welcome to Acme documentation.</p>',
        ]);
        $this->assertRedirect('/admin/settings');

        $this->get('/admin/settings');
        $this->assertResponseOk();
        $this->assertResponseContains('Acme Knowledge');
        $this->assertResponseContains('Admin center');
        $this->assertSame('Acme Knowledge', $db->execute(
            'SELECT setting_value FROM application_settings WHERE setting_key = ?',
            ['site_name'],
        )->fetchColumn(0));

        $this->post('/admin/settings/test-email', [
            'test_email_address' => 'test@example.test',
        ]);
        $this->assertRedirect('/admin/settings');
        $this->assertMailSentTo('test@example.test');
    }

    public function testSetupRequiresTokenAndCannotBeRepeated(): void
    {
        $data = [
            'token' => 'wrong',
            'username' => 'administrator',
            'password' => 'a long password',
            'password_confirm' => 'a long password',
        ];
        $this->post('/setup', $data);
        $this->assertResponseOk();
        $this->assertSame(0, (int)ConnectionManager::get('test')->execute('SELECT COUNT(*) FROM users')->fetchColumn(0));

        $data['token'] = '0123456789abcdef0123456789abcdef';
        $this->post('/setup', $data);
        $this->assertRedirect('/login');
        $user = ConnectionManager::get('test')->execute('SELECT * FROM users')->fetch('assoc');
        $this->assertSame('administrator', $user['username']);
        $this->assertTrue((bool)$user['is_admin']);
        $this->assertTrue((new DefaultPasswordHasher())->check('a long password', $user['password']));

        $this->post('/setup', $data);
        $this->assertResponseCode(404);
    }

    public function testLoginAndRevokedSession(): void
    {
        $db = ConnectionManager::get('test');
        $now = gmdate('Y-m-d H:i:s');
        $insert = $db->insert('users', [
            'username' => 'editor',
            'password' => (new DefaultPasswordHasher())->hash('a long password'),
            'is_admin' => true,
            'active' => true,
            'created' => $now,
            'modified' => $now,
        ]);
        $id = (int)$insert->lastInsertId('users');

        $this->post('/login', ['username' => 'editor', 'password' => 'incorrect password']);
        $this->assertResponseOk();
        $this->assertResponseContains('Invalid username or password.');
        $this->post('/login', ['username' => 'editor', 'password' => 'a long password']);
        $this->assertRedirect('/');

        $this->session(['Auth' => $id, 'AuthVersion' => 1]);
        $this->get('/admin/users');
        $this->assertResponseOk();
        $db->execute('UPDATE users SET auth_version = auth_version + 1 WHERE id = ?', [$id]);
        $this->get('/admin/users');
        $this->assertResponseCode(403);
    }

    public function testFailedLoginsAreRateLimited(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/login', ['username' => 'unknown', 'password' => 'incorrect password']);
            $this->assertResponseContains('Invalid username or password.');
        }
        $this->post('/login', ['username' => 'unknown', 'password' => 'incorrect password']);
        $this->assertResponseContains('Too many sign-in attempts.');
    }

    public function testSetupRejectsRequestsWithoutCsrfToken(): void
    {
        $this->_csrfToken = false;
        $this->post('/setup', [
            'token' => '0123456789abcdef0123456789abcdef',
            'username' => 'administrator',
            'password' => 'a long password',
            'password_confirm' => 'a long password',
        ]);
        $this->assertResponseCode(403);
        $this->assertSame(0, (int)ConnectionManager::get('test')->execute('SELECT COUNT(*) FROM users')->fetchColumn(0));
    }

    public function testAccountScreensUseAccountLayout(): void
    {
        $this->get('/setup');
        $this->assertResponseOk();
        $this->assertResponseNotContains('account-main');

        ConnectionManager::get('test')->insert('wiki_installation', ['id' => 1, 'created' => gmdate('Y-m-d H:i:s')]);
        $token = str_repeat('a', 64);
        $routes = [
            '/login', '/register', '/forgot-password', '/resend-verification',
            '/verify-email/' . $token, '/reset-password/' . $token,
        ];
        foreach ($routes as $route) {
            $this->get($route);
            $this->assertResponseOk();
            $this->assertResponseContains('class="main-content account-main"');
        }
        $this->get('/about');
        $this->assertResponseNotContains('account-main');
    }
}
