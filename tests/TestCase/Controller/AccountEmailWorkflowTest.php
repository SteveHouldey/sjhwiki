<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Service\AccountEmail;
use Authentication\PasswordHasher\DefaultPasswordHasher;
use Cake\Core\Configure;
use Cake\Datasource\ConnectionManager;
use Cake\TestSuite\EmailTrait;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;
use Cake\TestSuite\TestEmailTransport;
use RuntimeException;

class AccountEmailWorkflowTest extends TestCase
{
    use EmailTrait;
    use IntegrationTestTrait;

    protected function setUp(): void
    {
        parent::setUp();
        ConnectionManager::get('test')->begin();
        $this->enableCsrfToken();
        ConnectionManager::get('test')->insert('wiki_installation', ['id' => 1, 'created' => gmdate('Y-m-d H:i:s')]);
    }

    protected function tearDown(): void
    {
        ConnectionManager::get('test')->rollback();
        parent::tearDown();
    }

    public function testRegistrationVerificationAndWikiAccess(): void
    {
        $db = ConnectionManager::get('test');
        $this->register();
        $this->assertRedirect('/login');
        $this->assertMailSentTo('reader@example.com');
        $link = $this->mailPath('verify-email', 0);
        $token = basename($link);
        $user = $db->execute('SELECT * FROM users WHERE username = ?', ['new-reader'])->fetch('assoc');
        $this->assertSame('reader@example.com', $user['email']);
        $this->assertFalse((bool)$user['is_admin']);
        $this->assertSame(hash('sha256', $token), $db->execute(
            'SELECT token_hash FROM account_email_tokens WHERE user_id = ?',
            [$user['id']],
        )->fetchColumn(0));
        $this->post('/login', ['username' => 'new-reader', 'password' => 'a long password']);
        $this->assertResponseContains('Invalid username or password.');
        $this->session(['Auth' => (int)$user['id'], 'AuthVersion' => 1]);
        $this->get('/');
        $this->assertResponseContains('A better home for everything you know');
        $this->session(['Auth' => null]);
        $this->get($link);
        $this->assertResponseContains('Confirm this address');
        $this->get($link);
        $this->assertResponseContains('Confirm this address');
        $this->post($link, []);
        $this->assertRedirect('/login');
        $this->post($link, []);
        $this->assertResponseContains('invalid, expired or has already been used');
        $this->post('/login', ['username' => 'new-reader', 'password' => 'a long password']);
        $this->assertRedirect('/');
        $this->session(['Auth' => (int)$user['id'], 'AuthVersion' => 1]);

        $now = gmdate('Y-m-d H:i:s');
        $public = (int)$db->insert('wikis', [
            'name' => 'Public', 'slug' => 'public', 'is_public' => 1, 'created' => $now, 'modified' => $now,
        ])->lastInsertId('wikis');
        $private = (int)$db->insert('wikis', [
            'name' => 'Private', 'slug' => 'private', 'is_public' => 0, 'created' => $now, 'modified' => $now,
        ])->lastInsertId('wikis');
        $this->get('/wikis/' . $public);
        $this->assertResponseOk();
        $this->get('/wikis/' . $private);
        $this->assertResponseCode(404);
        $role = (int)$db->insert('wiki_roles', ['wiki_id' => $private, 'name' => 'Read only'])->lastInsertId('wiki_roles');
        $db->insert('wiki_role_permissions', ['wiki_role_id' => $role, 'permission' => 'read']);
        $db->insert('wiki_role_members', ['wiki_role_id' => $role, 'user_id' => $user['id']]);
        $this->get('/wikis/' . $private);
        $this->assertResponseOk();
        $this->get('/wikis/' . $private . '/pages/add');
        $this->assertResponseCode(403);
    }

    public function testPasswordResetIsSingleUseAndRevokesSessions(): void
    {
        $db = ConnectionManager::get('test');
        $this->register();
        $verify = $this->mailPath('verify-email', 0);
        $this->post($verify, []);
        $user = $db->execute('SELECT * FROM users WHERE username = ?', ['new-reader'])->fetch('assoc');
        $this->session(['Auth' => (int)$user['id'], 'AuthVersion' => 1]);
        $this->get('/');
        $this->assertResponseContains('Your wikis');
        $this->post('/forgot-password', ['email' => 'reader@example.com']);
        $this->assertRedirect('/forgot-password');
        $first = $this->mailPath('reset-password', 1);
        $this->post('/forgot-password', ['email' => 'reader@example.com']);
        $second = $this->mailPath('reset-password', 2);
        $this->get($first);
        $this->assertResponseContains('invalid, expired or has already been used');
        $this->get($second);
        $this->assertResponseContains('Change password');
        $this->assertHeader('Referrer-Policy', 'no-referrer');
        $this->post($second, ['password' => 'new long password', 'password_confirm' => 'different password']);
        $this->assertResponseContains('Passwords do not match.');
        $this->post($second, ['password' => 'new long password', 'password_confirm' => 'new long password']);
        $this->assertRedirect('/login');
        $user = $db->execute('SELECT * FROM users WHERE id = ?', [$user['id']])->fetch('assoc');
        $this->assertTrue((new DefaultPasswordHasher())->check('new long password', $user['password']));
        $this->assertSame(2, (int)$user['auth_version']);
        $this->get('/');
        $this->assertResponseContains('A better home for everything you know');
        $this->post($second, ['password' => 'another password', 'password_confirm' => 'another password']);
        $this->assertResponseContains('invalid, expired or has already been used');
    }

    public function testAccountEmailsIncludeMatchingHtmlAndTextLinks(): void
    {
        $this->register();
        $verifyPath = $this->mailPath('verify-email', 0);
        $verify = TestEmailTransport::getMessages()[0];
        $base = rtrim(Configure::read('App.fullBaseUrl'), '/');
        $this->assertSame('both', $verify->getEmailFormat());
        $this->assertStringContainsString('Verify your wiki email', $verify->getBodyHtml());
        $this->assertStringContainsString('24 hours', $verify->getBodyHtml());
        $this->assertStringContainsString('24 hours', $verify->getBodyText());
        $this->assertStringContainsString('href="' . $base . $verifyPath . '"', $verify->getBodyHtml());
        $this->assertStringContainsString($base . $verifyPath, $verify->getBodyText());
        $this->assertStringContainsString('Steven Houldey', $verify->getBodyHtml());

        $this->post($verifyPath, []);
        $this->post('/forgot-password', ['email' => 'reader@example.com']);
        $resetPath = $this->mailPath('reset-password', 1);
        $reset = TestEmailTransport::getMessages()[1];
        $this->assertSame('both', $reset->getEmailFormat());
        $this->assertStringContainsString('Reset your wiki password', $reset->getBodyHtml());
        $this->assertStringContainsString('one hour', $reset->getBodyHtml());
        $this->assertStringContainsString('one hour', $reset->getBodyText());
        $this->assertStringContainsString('href="' . $base . $resetPath . '"', $reset->getBodyHtml());
        $this->assertStringContainsString($base . $resetPath, $reset->getBodyText());
    }

    public function testLocalHttpEmailRequiresExplicitInstallerSetting(): void
    {
        $base = Configure::read('App.fullBaseUrl');
        $debug = Configure::read('debug');
        $allowLocal = Configure::read('App.allowInsecureLocalEmail');
        try {
            $this->register();
            $userId = (int)ConnectionManager::get('test')->execute(
                'SELECT id FROM users WHERE username = ?',
                ['new-reader'],
            )->fetchColumn(0);
            Configure::write('App.fullBaseUrl', 'http://127.0.0.1:8765');
            Configure::write('debug', false);
            Configure::write('App.allowInsecureLocalEmail', true);
            (new AccountEmail(ConnectionManager::get('test')))->send($userId, 'verify');
            $this->assertMailCount(2);
            $this->assertStringContainsString(
                'http://127.0.0.1:8765/verify-email/',
                TestEmailTransport::getMessages()[1]->getBodyText(),
            );
            Configure::write('App.fullBaseUrl', 'http://example.test');
            try {
                (new AccountEmail(ConnectionManager::get('test')))->send(1, 'verify');
                $this->fail('The local HTTP exception must not allow remote hosts.');
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString('App.fullBaseUrl', $exception->getMessage());
            }
        } finally {
            Configure::write('App.fullBaseUrl', $base);
            Configure::write('debug', $debug);
            Configure::write('App.allowInsecureLocalEmail', $allowLocal);
        }
    }

    public function testExpiredDisabledAndUnknownAccountsCannotUseLinks(): void
    {
        $db = ConnectionManager::get('test');
        $this->register();
        $verification = $this->mailPath('verify-email', 0);
        $db->update('account_email_tokens', ['expires' => gmdate('Y-m-d H:i:s', time() - 1)], []);
        $this->post($verification, []);
        $this->assertResponseContains('invalid, expired or has already been used');
        $this->post('/resend-verification', ['email' => 'reader@example.com']);
        $newVerification = $this->mailPath('verify-email', 1);
        $db->update('users', ['active' => 0], ['username' => 'new-reader']);
        $this->post($newVerification, []);
        $this->assertResponseContains('invalid, expired or has already been used');
        $this->post('/forgot-password', ['email' => 'unknown@example.com']);
        $this->assertRedirect('/forgot-password');
        $this->post('/forgot-password', ['email' => 'reader@example.com']);
        $this->assertRedirect('/forgot-password');
        $this->assertMailCount(2);
    }

    public function testDuplicateEmailAndRequestRateLimit(): void
    {
        $this->register();
        $this->post('/register', [
            'username' => 'another-user', 'email' => 'READER@example.com',
            'password' => 'a long password', 'password_confirm' => 'a long password',
        ]);
        $this->assertResponseContains('Username or email is already in use.');
        for ($i = 0; $i < 6; $i++) {
            $this->post('/resend-verification', ['email' => 'reader@example.com']);
            $this->assertRedirect('/resend-verification');
        }
        $this->assertMailCount(6);
    }

    public function testVerificationAndResetRequireCsrf(): void
    {
        $this->register();
        $verify = $this->mailPath('verify-email', 0);
        $this->_csrfToken = false;
        $this->post($verify, []);
        $this->assertResponseCode(403);
        $this->enableCsrfToken();
        $this->post($verify, []);
        $this->post('/forgot-password', ['email' => 'reader@example.com']);
        $reset = $this->mailPath('reset-password', 1);
        $this->_csrfToken = false;
        $this->post($reset, ['password' => 'new long password', 'password_confirm' => 'new long password']);
        $this->assertResponseCode(403);
    }

    public function testAdministratorCanAddEmailToExistingAccountAndRevokeResetLinks(): void
    {
        $db = ConnectionManager::get('test');
        $now = gmdate('Y-m-d H:i:s');
        $id = (int)$db->insert('users', [
            'username' => 'old-admin',
            'password' => (new DefaultPasswordHasher())->hash('a long password'),
            'is_admin' => 1, 'active' => 1, 'created' => $now, 'modified' => $now,
        ])->lastInsertId('users');
        $this->session(['Auth' => $id, 'AuthVersion' => 1]);
        $this->post('/admin/users/' . $id, [
            'email' => 'admin@example.com', 'active' => '1', 'is_admin' => '1',
        ]);
        $this->assertRedirect('/login');
        $this->assertMailSentTo('admin@example.com');
        $user = $db->execute('SELECT * FROM users WHERE id = ?', [$id])->fetch('assoc');
        $this->assertFalse((bool)$user['verification_required']);
        $this->assertNull($user['email_verified']);
        $this->post('/login', ['username' => 'old-admin', 'password' => 'a long password']);
        $this->assertRedirect('/');
        $this->post($this->mailPath('verify-email', 0), []);
        $this->post('/forgot-password', ['email' => 'admin@example.com']);
        $reset = $this->mailPath('reset-password', 1);
        $this->session(['Auth' => $id, 'AuthVersion' => 2]);
        $this->post('/admin/users/' . $id, [
            'email' => 'new-admin@example.com', 'active' => '1', 'is_admin' => '1',
        ]);
        $this->assertRedirect('/login');
        $this->get($reset);
        $this->assertResponseContains('invalid, expired or has already been used');
        $this->assertMailSentTo('new-admin@example.com');
    }

    public function testMissingBaseUrlReportsRegistrationDeliveryFailure(): void
    {
        $base = Configure::read('App.fullBaseUrl');
        Configure::write('App.fullBaseUrl', false);
        try {
            $this->register();
            $this->assertRedirect('/resend-verification');
            $this->assertSession(
                'Account created, but the verification email could not be sent. Please request it again.',
                'Flash.flash.0.message',
            );
            $this->assertNoMailSent();
        } finally {
            Configure::write('App.fullBaseUrl', $base);
        }
    }

    private function register(): void
    {
        $this->post('/register', [
            'username' => 'new-reader', 'email' => 'Reader@example.com',
            'password' => 'a long password', 'password_confirm' => 'a long password',
        ]);
    }

    private function mailPath(string $route, int $index): string
    {
        $body = TestEmailTransport::getMessages()[$index]->getBodyText();
        $this->assertMatchesRegularExpression('#/' . $route . '/[a-f0-9]{64}#', $body);
        preg_match('#/' . $route . '/[a-f0-9]{64}#', $body, $matches);

        return $matches[0];
    }
}
