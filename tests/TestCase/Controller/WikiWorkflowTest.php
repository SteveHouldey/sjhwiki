<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Cake\Datasource\ConnectionManager;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;
use Laminas\Diactoros\UploadedFile;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class WikiWorkflowTest extends TestCase
{
    use IntegrationTestTrait;

    private string|false $previousGitDirectory;
    private string|false $previousMediaDirectory;
    private string $mediaDirectory;

    protected function setUp(): void
    {
        parent::setUp();
        ConnectionManager::get('test')->begin();
        $this->enableCsrfToken();
        $this->previousGitDirectory = getenv('WIKI_GIT_DIR');
        $this->previousMediaDirectory = getenv('WIKI_MEDIA_DIR');
        $this->mediaDirectory = TMP . 'tests' . DS . 'media-' . bin2hex(random_bytes(8));
        mkdir($this->mediaDirectory, 0700, true);
        putenv('WIKI_MEDIA_DIR=' . $this->mediaDirectory);
        putenv('WIKI_GIT_DIR');
    }

    protected function tearDown(): void
    {
        ConnectionManager::get('test')->rollback();
        if ($this->previousGitDirectory === false) {
            putenv('WIKI_GIT_DIR');
        } else {
            putenv('WIKI_GIT_DIR=' . $this->previousGitDirectory);
        }
        if ($this->previousMediaDirectory === false) {
            putenv('WIKI_MEDIA_DIR');
        } else {
            putenv('WIKI_MEDIA_DIR=' . $this->previousMediaDirectory);
        }
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->mediaDirectory, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $item) {
            if ($item->isDir() && !$item->isLink()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }
        rmdir($this->mediaDirectory);
        parent::tearDown();
    }

    public function testPrivateWikisAreExcludedFromSearchAndPages(): void
    {
        $public = $this->wiki('public', true);
        $private = $this->wiki('private', false);
        $other = $this->wiki('other-private', false);
        $publicPage = $this->page($public, 'public-page', 'Public needle', 'public content');
        $privatePage = $this->page($private, 'secret-page', 'Private needle', 'secret content');
        $this->page($other, 'other-secret', 'Other needle', 'other content');
        $user = $this->user('reader');
        $this->role($private, $user, ['read']);

        $this->get('/search?q=needle');
        $this->assertResponseOk();
        $this->assertResponseContains('Public needle');
        $this->assertResponseNotContains('Private needle');
        $this->assertResponseNotContains('Other needle');
        $this->get('/wikis/' . $private . '/pages/' . $privatePage);
        $this->assertResponseCode(404);

        $this->session(['Auth' => $user, 'AuthVersion' => 1]);
        $this->get('/search?q=needle');
        $this->assertResponseContains('Private needle');
        $this->assertResponseNotContains('Other needle');
        $this->get('/wikis/' . $private . '/pages/' . $privatePage . '/edit');
        $this->assertResponseCode(403);
        $this->get('/wikis/' . $other . '/pages/' . $publicPage);
        $this->assertResponseCode(404);
    }

    public function testEditingHistoryRestoreAndHtmlSafety(): void
    {
        $db = ConnectionManager::get('test');
        $wiki = $this->wiki('editing', false);
        $user = $this->user('writer');
        $this->role($wiki, $user, ['read', 'create', 'edit', 'delete', 'history', 'restore']);
        $this->session(['Auth' => $user, 'AuthVersion' => 1]);

        $this->post('/wikis/' . $wiki . '/pages/add', [
            'path' => 'guides/start',
            'title' => 'Start',
            'body' => '<h1>Safe</h1><p><span style="font-size:24px;color:#ff0000"><strong>Bold</strong></span>' .
                '<script>alert(1)</script>' .
                '<a href="javascript:alert(1)">bad</a><img src="x" onerror="alert(1)"></p>',
        ]);
        $page = $db->execute('SELECT * FROM wiki_pages WHERE wiki_id = ?', [$wiki])->fetch('assoc');
        $pageId = (int)$page['id'];
        $this->assertRedirect('/wikis/' . $wiki . '/pages/' . $pageId);
        $this->get('/wikis/' . $wiki . '/pages/' . $pageId);
        $this->assertResponseOk();
        $this->assertResponseContains('<h1>Safe</h1>');
        $this->assertResponseContains('<strong>Bold</strong>');
        $this->assertResponseContains('font-size:24px');
        $this->assertResponseContains('color:#ff0000');
        $this->assertResponseNotContains('<script>alert');
        $this->assertResponseNotContains('href="javascript:');
        $this->assertResponseNotContains('onerror=');

        $this->post('/wikis/' . $wiki . '/pages/' . $pageId . '/edit', [
            'version' => 1,
            'title' => 'Updated',
            'body' => 'Version two',
        ]);
        $this->assertRedirect();
        $this->post('/wikis/' . $wiki . '/pages/' . $pageId . '/edit', [
            'version' => 1,
            'title' => 'Stale content',
            'body' => 'Keep this text',
        ]);
        $this->assertResponseCode(409);
        $this->assertResponseContains('Keep this text');
        $page = $db->execute('SELECT * FROM wiki_pages WHERE id = ?', [$pageId])->fetch('assoc');
        $this->assertSame('Version two', $page['body']);
        $first = $db->execute(
            'SELECT id FROM wiki_page_revisions WHERE wiki_page_id = ? ORDER BY id',
            [$pageId],
        )->fetch('assoc');

        $this->post('/wikis/' . $wiki . '/pages/' . $pageId . '/delete', ['version' => 2]);
        $this->assertRedirect('/wikis/' . $wiki);
        $this->get('/wikis/' . $wiki . '/pages/' . $pageId);
        $this->assertResponseCode(404);
        $this->get('/wikis/' . $wiki . '/pages/' . $pageId . '/history');
        $this->assertResponseOk();
        $this->post(
            '/wikis/' . $wiki . '/pages/' . $pageId . '/history/' . $first['id'] . '/restore',
            ['version' => 3],
        );
        $this->assertRedirect();
        $page = $db->execute('SELECT * FROM wiki_pages WHERE id = ?', [$pageId])->fetch('assoc');
        $this->assertFalse((bool)$page['deleted']);
        $this->assertSame('Start', $page['title']);
        $this->assertSame(4, (int)$db->execute(
            'SELECT COUNT(*) FROM wiki_page_revisions WHERE wiki_page_id = ?',
            [$pageId],
        )->fetchColumn(0));
        $this->get('/wikis/' . $wiki . '/pages/' . $pageId);
        $this->assertResponseContains('<strong>Bold</strong>');
    }

    public function testLandingOnlyListsPublicWikis(): void
    {
        $this->wiki('public-landing', true);
        $this->wiki('private-landing', false);
        $this->get('/');
        $this->assertResponseOk();
        $this->assertResponseContains('A better home for everything you know');
        $this->assertResponseContains('Public-landing');
        $this->assertResponseContains('SJHWiki v1.0.1 &copy; ' . gmdate('Y'));
        $this->assertResponseContains('Created by Steven Houldey');
        $this->assertResponseNotContains('Private-landing');
        $this->assertResponseNotContains('class="sidebar"');
        $this->assertResponseContains('class="main-content landing-main"');
        $this->get('/about');
        $this->assertResponseOk();
        $this->assertResponseContains('Knowledge is better when it is shared');
        $this->assertResponseContains('Sign in');
        $this->assertResponseNotContains('class="sidebar"');

        $admin = $this->user('landing-admin');
        ConnectionManager::get('test')->update('users', ['is_admin' => 1], ['id' => $admin]);
        ConnectionManager::get('test')->insert('wiki_installation', ['id' => 1, 'created' => gmdate('Y-m-d H:i:s')]);
        $this->session(['Auth' => $admin, 'AuthVersion' => 1]);
        $this->get('/');
        $this->assertResponseContains('Your wikis');
        $this->assertResponseContains('Steven Houldey');
        $this->assertResponseContains('Private-landing');
        $this->assertResponseContains('class="sidebar"');
        $this->assertResponseNotContains('landing-main');
        $this->get('/about');
        $this->assertResponseContains('Go to dashboard');
        $this->assertResponseNotContains('class="sidebar"');
    }

    public function testSidebarIsShownOnlyToSignedInUsers(): void
    {
        ConnectionManager::get('test')->insert('wiki_installation', ['id' => 1, 'created' => gmdate('Y-m-d H:i:s')]);
        $wiki = $this->wiki('public-navigation', true);
        $page = $this->page($wiki, 'welcome', 'Welcome', 'Public content');
        $routes = [
            '/login', '/register', '/forgot-password', '/search', '/wikis/' . $wiki,
            '/wikis/' . $wiki . '/pages/' . $page,
        ];
        foreach ($routes as $route) {
            $this->get($route);
            $this->assertResponseOk();
            $this->assertResponseNotContains('class="sidebar"');
        }

        $this->session(['Auth' => $this->user('navigation-reader'), 'AuthVersion' => 1]);
        foreach ($routes as $route) {
            $this->get($route);
            $this->assertResponseOk();
            $this->assertResponseContains('class="sidebar"');
        }
    }

    public function testTableAndCodeSurviveHistoryAndRestore(): void
    {
        $db = ConnectionManager::get('test');
        $wiki = $this->wiki('rich-table', false);
        $user = $this->user('rich-writer');
        $this->role($wiki, $user, ['read', 'create', 'edit', 'history', 'restore']);
        $this->session(['Auth' => $user, 'AuthVersion' => 1]);
        $body = '<table><colgroup><col style="width:123px"></colgroup>' .
            '<tbody><tr><th colwidth="123"><p>Héader</p></th></tr>' .
            '<tr><td colwidth="99999" onclick="alert(1)"><p>Body</p></td></tr></tbody>' .
            '<tfoot><tr><td colwidth="123"><p>Footer</p></td></tr></tfoot></table>' .
            '<pre><code class="language-javascript evil">const answer = 42;</code></pre>' .
            '<script>alert(1)</script>';
        $this->post('/wikis/' . $wiki . '/pages/add', [
            'path' => 'table', 'title' => 'Table', 'body' => $body,
        ]);
        $page = $db->execute('SELECT * FROM wiki_pages WHERE wiki_id = ?', [$wiki])->fetch('assoc');
        $pageId = (int)$page['id'];
        $this->assertRedirect('/wikis/' . $wiki . '/pages/' . $pageId);
        $this->assertStringContainsString('Héader', $page['body']);
        $this->assertStringContainsString('colwidth="123"', $page['body']);
        $this->assertStringNotContainsString('colwidth="99999"', $page['body']);
        $this->assertStringContainsString('class="language-javascript"', $page['body']);
        $this->assertStringNotContainsString('evil', $page['body']);
        $this->assertStringNotContainsString('onclick', $page['body']);
        $this->assertStringNotContainsString('<script', $page['body']);
        $this->assertLessThan(strpos($page['body'], '<tfoot'), strpos($page['body'], '<tbody'));

        $revision = $db->execute(
            'SELECT id FROM wiki_page_revisions WHERE wiki_page_id = ? ORDER BY id',
            [$pageId],
        )->fetch('assoc');
        $this->post('/wikis/' . $wiki . '/pages/' . $pageId . '/edit', [
            'version' => 1, 'title' => 'Updated', 'body' => '<p>Replacement</p>',
        ]);
        $this->get('/wikis/' . $wiki . '/pages/' . $pageId . '/history');
        $this->assertResponseContains('language-javascript');
        $this->assertResponseContains('<tfoot>');
        $this->post('/wikis/' . $wiki . '/pages/' . $pageId . '/history/' . $revision['id'] . '/restore', [
            'version' => 2,
        ]);
        $this->assertRedirect();
        $restored = $db->execute('SELECT body FROM wiki_pages WHERE id = ?', [$pageId])->fetch('assoc');
        $this->assertSame($page['body'], $restored['body']);
    }

    public function testRestoringLegacyMarkdownPreservesOriginalRevision(): void
    {
        $db = ConnectionManager::get('test');
        $wiki = $this->wiki('legacy', false);
        $user = $this->user('legacy-writer');
        $this->role($wiki, $user, ['read', 'edit', 'history', 'restore']);
        $pageId = $this->page($wiki, 'legacy', 'Legacy', '# Current');
        $original = "# Earlier\n\n**Important**\n\n```js\nconst answer = 42;\n```";
        $revisionId = (int)$db->insert('wiki_page_revisions', [
            'wiki_page_id' => $pageId,
            'user_id' => $user,
            'path' => 'legacy',
            'title' => 'Earlier',
            'body' => $original,
            'operation' => 'edit',
            'git_synced' => 1,
            'created' => gmdate('Y-m-d H:i:s'),
        ])->lastInsertId('wiki_page_revisions');
        $this->session(['Auth' => $user, 'AuthVersion' => 1]);
        $this->post('/wikis/' . $wiki . '/pages/' . $pageId . '/history/' . $revisionId . '/restore', ['version' => 1]);
        $this->assertRedirect();
        $page = $db->execute('SELECT body, content_format FROM wiki_pages WHERE id = ?', [$pageId])->fetch('assoc');
        $this->assertSame('html', $page['content_format']);
        $this->assertStringContainsString('<strong>Important</strong>', $page['body']);
        $this->assertStringContainsString('class="language-js"', $page['body']);
        $legacy = $db->execute(
            'SELECT body, content_format FROM wiki_page_revisions WHERE id = ?',
            [$revisionId],
        )->fetch('assoc');
        $this->assertSame('markdown', $legacy['content_format']);
        $this->assertSame($original, $legacy['body']);
    }

    public function testProtectedImageUploadAndReadPermissions(): void
    {
        $wiki = $this->wiki('media-private', false);
        $other = $this->wiki('media-other', false);
        $writer = $this->user('media-writer');
        $reader = $this->user('media-reader');
        $this->role($wiki, $writer, ['read', 'edit']);
        $this->role($wiki, $reader, ['read']);
        $this->session(['Auth' => $writer, 'AuthVersion' => 1]);
        $gif = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7', true);
        $this->post('/wikis/' . $wiki . '/media', ['image' => $this->upload($gif)]);
        $this->assertResponseCode(201);
        $result = json_decode((string)$this->_response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertFalse($result['synced']);
        $this->assertStringStartsWith('/wikis/' . $wiki . '/media/', $result['url']);
        $this->get($result['url']);
        $this->assertResponseOk();
        $this->assertSame($gif, (string)$this->_response->getBody());
        $mediaId = (int)basename($result['url']);
        $this->get('/wikis/' . $other . '/media/' . $mediaId);
        $this->assertResponseCode(404);
        $this->session(['Auth' => null]);
        $this->get($result['url']);
        $this->assertResponseCode(404);
        $this->session(['Auth' => $reader, 'AuthVersion' => 1]);
        $this->get($result['url']);
        $this->assertResponseOk();
        $this->post('/wikis/' . $wiki . '/media', ['image' => $this->upload($gif)]);
        $this->assertResponseCode(403);
    }

    public function testImageValidationAndTwentyMebibyteLimit(): void
    {
        $wiki = $this->wiki('media-limit', false);
        $writer = $this->user('limit-writer');
        $this->role($wiki, $writer, ['read', 'create']);
        $this->session(['Auth' => $writer, 'AuthVersion' => 1]);
        $this->post('/wikis/' . $wiki . '/media', ['image' => $this->upload('<svg onload="alert(1)"></svg>')]);
        $this->assertResponseCode(422);
        $gif = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7', true);
        $this->post('/wikis/' . $wiki . '/media', ['image' => $this->upload($gif, 20 * 1024 * 1024 + 1)]);
        $this->assertResponseCode(422);
        $large = str_pad($gif, 20 * 1024 * 1024, "\0");
        $this->post('/wikis/' . $wiki . '/media', ['image' => $this->upload($large)]);
        $this->assertResponseCode(201);
    }

    private function upload(string $bytes, ?int $size = null): UploadedFile
    {
        $stream = fopen('php://temp', 'w+b');
        fwrite($stream, $bytes);
        rewind($stream);

        return new UploadedFile($stream, $size ?? strlen($bytes), UPLOAD_ERR_OK, 'test.gif', 'image/gif');
    }

    public function testRoleManagementIsScopedAndRevocationRemovesAccess(): void
    {
        $db = ConnectionManager::get('test');
        $wiki = $this->wiki('roles', false);
        $other = $this->wiki('other-roles', false);
        $admin = $this->user('role-admin');
        $member = $this->user('role-member');
        $db->update('users', ['is_admin' => 1], ['id' => $admin]);
        $this->session(['Auth' => $admin, 'AuthVersion' => 1]);
        $this->post('/wikis/' . $wiki . '/roles/add', [
            'name' => 'Editors',
            'permissions' => ['read', 'edit'],
            'members' => [$member],
        ]);
        $this->assertRedirect('/wikis/' . $wiki . '/roles');
        $role = $db->execute('SELECT id FROM wiki_roles WHERE wiki_id = ?', [$wiki])->fetch('assoc');
        $this->get('/wikis/' . $other . '/roles/' . $role['id']);
        $this->assertResponseCode(404);

        $this->session(['Auth' => $member, 'AuthVersion' => 1]);
        $this->get('/wikis/' . $wiki);
        $this->assertResponseOk();
        $this->get('/wikis/' . $wiki . '/roles');
        $this->assertResponseCode(403);

        $this->session(['Auth' => $admin, 'AuthVersion' => 1]);
        $this->post('/wikis/' . $wiki . '/roles/' . $role['id'] . '/delete');
        $this->assertRedirect('/wikis/' . $wiki . '/roles');
        $this->assertSame(0, (int)$db->execute(
            'SELECT COUNT(*) FROM wiki_role_members WHERE wiki_role_id = ?',
            [$role['id']],
        )->fetchColumn(0));
        $this->assertSame(0, (int)$db->execute(
            'SELECT COUNT(*) FROM wiki_role_permissions WHERE wiki_role_id = ?',
            [$role['id']],
        )->fetchColumn(0));
        $this->session(['Auth' => $member, 'AuthVersion' => 1]);
        $this->get('/wikis/' . $wiki);
        $this->assertResponseCode(404);
    }

    private function wiki(string $slug, bool $public): int
    {
        $now = gmdate('Y-m-d H:i:s');
        $statement = ConnectionManager::get('test')->insert('wikis', [
            'name' => ucfirst($slug),
            'slug' => $slug . '-' . bin2hex(random_bytes(4)),
            'is_public' => (int)$public,
            'created' => $now,
            'modified' => $now,
        ]);

        return (int)$statement->lastInsertId('wikis');
    }

    private function user(string $name): int
    {
        $now = gmdate('Y-m-d H:i:s');
        $statement = ConnectionManager::get('test')->insert('users', [
            'username' => $name . '-' . bin2hex(random_bytes(4)),
            'password' => 'not-a-real-password-hash',
            'created' => $now,
            'modified' => $now,
        ]);

        return (int)$statement->lastInsertId('users');
    }

    private function page(int $wikiId, string $path, string $title, string $body): int
    {
        $now = gmdate('Y-m-d H:i:s');
        $statement = ConnectionManager::get('test')->insert('wiki_pages', [
            'wiki_id' => $wikiId,
            'path' => $path,
            'title' => $title,
            'body' => $body,
            'created' => $now,
            'modified' => $now,
        ]);

        return (int)$statement->lastInsertId('wiki_pages');
    }

    private function role(int $wikiId, int $userId, array $permissions): void
    {
        $db = ConnectionManager::get('test');
        $role = $db->insert('wiki_roles', ['wiki_id' => $wikiId, 'name' => 'Members-' . $userId]);
        $roleId = (int)$role->lastInsertId('wiki_roles');
        $db->insert('wiki_role_members', ['wiki_role_id' => $roleId, 'user_id' => $userId]);
        foreach ($permissions as $permission) {
            $db->insert('wiki_role_permissions', ['wiki_role_id' => $roleId, 'permission' => $permission]);
        }
    }
}
