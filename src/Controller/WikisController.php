<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\WikiAccess;
use App\Service\WikiTree;
use Cake\Http\Response;
use InvalidArgumentException;

class WikisController extends AppController
{
    /** @return void */
    public function index(): void
    {
        $signedIn = $this->currentUser() !== null;
        $all = $this->db()->execute(
            $signedIn ? 'SELECT * FROM wikis ORDER BY name' : 'SELECT * FROM wikis WHERE is_public = 1 ORDER BY name',
        )->fetchAll('assoc');
        $access = $this->wikiAccess();
        $wikis = array_values(array_filter($all, fn(array $wiki): bool => $access->can($wiki, 'read')));
        $installed = (bool)$this->db()->execute('SELECT id FROM wiki_installation WHERE id = 1')->fetch('assoc');
        $this->set(compact('wikis', 'installed'));
        if (!$signedIn) {
            $this->set('hideSidebar', true);
            $this->set('fullWidthLanding', true);
            $this->render('landing');
        }
    }

    /** @return void */
    public function about(): void
    {
        $this->set('hideSidebar', true);
    }

    /** @return \Cake\Http\Response|null */
    public function add(): ?Response
    {
        $this->requireAdmin();
        if (!$this->request->is('post')) {
            return null;
        }
        try {
            $name = $this->wikiName($this->request->getData('name'));
            $slug = $this->request->getData('slug');
            if (
                !is_string($slug) || strlen($slug) > 100 ||
                !preg_match('/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/D', $slug)
            ) {
                throw new InvalidArgumentException('Use lowercase letters, numbers and hyphens for the wiki slug.');
            }
            if ($this->db()->execute('SELECT id FROM wikis WHERE slug = ?', [$slug])->fetch('assoc')) {
                throw new InvalidArgumentException('That wiki slug is already in use.');
            }
        } catch (InvalidArgumentException $exception) {
            $this->Flash->error($exception->getMessage());

            return null;
        }

        $now = gmdate('Y-m-d H:i:s');
        $statement = $this->db()->insert('wikis', [
            'name' => $name,
            'slug' => $slug,
            'is_public' => (int)($this->request->getData('is_public') === '1'),
            'created' => $now,
            'modified' => $now,
        ]);
        $this->Flash->success('Wiki created. Add roles and members to grant access.');

        return $this->redirect('/wikis/' . $statement->lastInsertId('wikis'));
    }

    /** @return void */
    public function view(string $wikiId): void
    {
        $access = $this->wikiAccess();
        $wiki = $access->wiki((int)$wikiId);
        $pages = $this->db()->execute(
            'SELECT id, path, title, modified FROM wiki_pages WHERE wiki_id = ? AND deleted = 0 ORDER BY path',
            [(int)$wikiId],
        )->fetchAll('assoc');
        $tree = WikiTree::build($pages);
        $deletedPages = [];
        if ($access->can($wiki, 'history')) {
            $deletedPages = $this->db()->execute(
                'SELECT id, path, title, modified FROM wiki_pages WHERE wiki_id = ? AND deleted = 1 ORDER BY path',
                [(int)$wikiId],
            )->fetchAll('assoc');
        }
        $permissions = [];
        foreach (array_keys(WikiAccess::PERMISSIONS) as $permission) {
            $permissions[$permission] = $access->can($wiki, $permission);
        }
        $this->set(compact('wiki', 'tree', 'deletedPages', 'permissions'));
    }

    /** @return \Cake\Http\Response|null */
    public function settings(string $wikiId): ?Response
    {
        $access = $this->wikiAccess();
        $wiki = $access->wiki((int)$wikiId);
        $access->require($wiki, 'manage_wiki');
        $this->set(compact('wiki'));
        if (!$this->request->is(['post', 'put'])) {
            return null;
        }
        try {
            $name = $this->wikiName($this->request->getData('name'));
        } catch (InvalidArgumentException $exception) {
            $this->Flash->error($exception->getMessage());

            return null;
        }
        $this->db()->update('wikis', [
            'name' => $name,
            'is_public' => (int)($this->request->getData('is_public') === '1'),
            'modified' => gmdate('Y-m-d H:i:s'),
        ], ['id' => (int)$wikiId]);
        $this->Flash->success('Wiki settings saved.');

        return $this->redirect('/wikis/' . $wikiId);
    }

    /** @return void */
    public function search(): void
    {
        $query = $this->request->getQuery('q', '');
        $results = [];
        if (!is_string($query) || mb_strlen($query) > 100) {
            $this->Flash->error('Search terms must be at most 100 characters.');
            $query = '';
        }
        $query = trim($query);
        if ($query !== '') {
            $term = '%' . mb_strtolower($query) . '%';
            $sql = 'SELECT p.id, p.wiki_id, p.title, p.path, w.name AS wiki_name
                FROM wiki_pages p INNER JOIN wikis w ON w.id = p.wiki_id
                WHERE p.deleted = 0 AND (LOWER(p.title) LIKE ? OR LOWER(p.path) LIKE ? OR LOWER(p.body) LIKE ?)';
            $params = [$term, $term, $term];
            $user = $this->currentUser();
            if (!$user || !$user['is_admin']) {
                $sql .= ' AND (w.is_public = 1';
                if ($user) {
                    $sql .= ' OR EXISTS (SELECT 1 FROM wiki_roles r
                        INNER JOIN wiki_role_members m ON m.wiki_role_id = r.id
                        INNER JOIN wiki_role_permissions rp ON rp.wiki_role_id = r.id
                        WHERE r.wiki_id = w.id AND m.user_id = ? AND rp.permission = ?)';
                    $params[] = $user['id'];
                    $params[] = 'read';
                }
                $sql .= ')';
            }
            $sql .= ' ORDER BY w.name, p.path LIMIT 100';
            $results = $this->db()->execute($sql, $params)->fetchAll('assoc');
        }
        $this->set(compact('query', 'results'));
    }

    /** @return string */
    private function wikiName(mixed $value): string
    {
        if (
            !is_string($value) || !mb_check_encoding($value, 'UTF-8') ||
            trim($value) === '' || mb_strlen($value) > 150
        ) {
            throw new InvalidArgumentException('A wiki name of at most 150 characters is required.');
        }

        return trim($value);
    }
}
