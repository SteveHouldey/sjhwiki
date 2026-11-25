<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\WikiAccess;
use Cake\Http\Exception\NotFoundException;
use Cake\Http\Response;
use InvalidArgumentException;

class WikiRolesController extends AppController
{
    /** @return void */
    public function index(string $wikiId): void
    {
        $wiki = $this->managedWiki($wikiId);
        $roles = $this->db()->execute(
            'SELECT r.id, r.name, COUNT(m.user_id) AS member_count FROM wiki_roles r
            LEFT JOIN wiki_role_members m ON m.wiki_role_id = r.id
            WHERE r.wiki_id = ? GROUP BY r.id, r.name ORDER BY r.name',
            [(int)$wikiId],
        )->fetchAll('assoc');
        $this->set(compact('wiki', 'roles'));
    }

    /** @return \Cake\Http\Response|null */
    public function add(string $wikiId): ?Response
    {
        $wiki = $this->managedWiki($wikiId);
        $role = ['name' => ''];
        $permissions = [];
        $members = [];
        $users = $this->userOptions();
        if ($this->request->is('post')) {
            try {
                [$name, $permissions, $members] = $this->input($users);
                $this->assertUniqueName((int)$wikiId, $name);
                $this->db()->transactional(function () use ($wikiId, $name, $permissions, $members): void {
                    $statement = $this->db()->insert('wiki_roles', ['wiki_id' => (int)$wikiId, 'name' => $name]);
                    $this->replaceAssignments((int)$statement->lastInsertId('wiki_roles'), $permissions, $members);
                });
                $this->Flash->success('Role created.');

                return $this->redirect('/wikis/' . $wikiId . '/roles');
            } catch (InvalidArgumentException $exception) {
                $this->Flash->error($exception->getMessage());
            }
        }
        $permissionOptions = WikiAccess::PERMISSIONS;
        $this->set(compact('wiki', 'role', 'permissions', 'members', 'users', 'permissionOptions'));
        $this->render('form');

        return null;
    }

    /** @return \Cake\Http\Response|null */
    public function edit(string $wikiId, string $roleId): ?Response
    {
        $wiki = $this->managedWiki($wikiId);
        $role = $this->role((int)$wikiId, (int)$roleId);
        $permissions = array_column($this->db()->execute(
            'SELECT permission FROM wiki_role_permissions WHERE wiki_role_id = ?',
            [(int)$roleId],
        )->fetchAll('assoc'), 'permission');
        $members = array_column($this->db()->execute(
            'SELECT user_id FROM wiki_role_members WHERE wiki_role_id = ?',
            [(int)$roleId],
        )->fetchAll('assoc'), 'user_id');
        $users = $this->userOptions();
        if ($this->request->is(['post', 'put'])) {
            try {
                [$name, $permissions, $members] = $this->input($users);
                $this->assertUniqueName((int)$wikiId, $name, (int)$roleId);
                $this->db()->transactional(function () use ($wikiId, $roleId, $name, $permissions, $members): void {
                    $this->db()->update(
                        'wiki_roles',
                        ['name' => $name],
                        ['id' => (int)$roleId, 'wiki_id' => (int)$wikiId],
                    );
                    $this->replaceAssignments((int)$roleId, $permissions, $members);
                });
                $this->Flash->success('Role updated.');

                return $this->redirect('/wikis/' . $wikiId . '/roles');
            } catch (InvalidArgumentException $exception) {
                $this->Flash->error($exception->getMessage());
            }
        }
        $permissionOptions = WikiAccess::PERMISSIONS;
        $this->set(compact('wiki', 'role', 'permissions', 'members', 'users', 'permissionOptions'));
        $this->render('form');

        return null;
    }

    /** @return \Cake\Http\Response */
    public function delete(string $wikiId, string $roleId): Response
    {
        $this->request->allowMethod(['post']);
        $this->managedWiki($wikiId);
        $this->role((int)$wikiId, (int)$roleId);
        $this->db()->delete('wiki_roles', ['id' => (int)$roleId, 'wiki_id' => (int)$wikiId]);
        $this->Flash->success('Role deleted.');

        return $this->redirect('/wikis/' . $wikiId . '/roles');
    }

    /** @return array */
    private function managedWiki(string $wikiId): array
    {
        $access = $this->wikiAccess();
        $wiki = $access->wiki((int)$wikiId);
        $access->require($wiki, 'manage_roles');

        return $wiki;
    }

    /** @return array */
    private function role(int $wikiId, int $roleId): array
    {
        $role = $this->db()->execute(
            'SELECT * FROM wiki_roles WHERE id = ? AND wiki_id = ?',
            [$roleId, $wikiId],
        )->fetch('assoc');
        if (!$role) {
            throw new NotFoundException();
        }

        return $role;
    }

    /** @return array */
    private function userOptions(): array
    {
        $users = [];
        $rows = $this->db()->execute('SELECT id, username, active FROM users ORDER BY username')->fetchAll('assoc');
        foreach ($rows as $user) {
            $users[$user['id']] = $user['username'] . ($user['active'] ? '' : ' (disabled)');
        }

        return $users;
    }

    /** @return array */
    private function input(array $users): array
    {
        $name = $this->request->getData('name');
        $permissions = $this->request->getData('permissions', []);
        $members = $this->request->getData('members', []);
        if (!is_string($name) || !mb_check_encoding($name, 'UTF-8') || trim($name) === '' || mb_strlen($name) > 100) {
            throw new InvalidArgumentException('A role name of at most 100 characters is required.');
        }
        if (!is_array($permissions) || !is_array($members)) {
            throw new InvalidArgumentException('Invalid permissions or membership.');
        }
        foreach ($permissions as $permission) {
            if (!is_string($permission) || ($permission !== '' && !isset(WikiAccess::PERMISSIONS[$permission]))) {
                throw new InvalidArgumentException('Invalid permission.');
            }
        }
        foreach ($members as $id) {
            if (
                (!is_string($id) && !is_int($id)) ||
                ($id !== '' && (!ctype_digit((string)$id) || !isset($users[(int)$id])))
            ) {
                throw new InvalidArgumentException('Invalid role member.');
            }
        }
        $permissions = array_values(array_unique(array_filter($permissions, fn($item): bool => $item !== '')));
        $members = array_values(array_unique(array_map(
            'intval',
            array_filter($members, fn($item): bool => $item !== ''),
        )));
        if ($permissions && !in_array('read', $permissions, true)) {
            throw new InvalidArgumentException('Roles with permissions must include read access.');
        }

        return [trim($name), $permissions, $members];
    }

    /** @return void */
    private function assertUniqueName(int $wikiId, string $name, int $exceptId = 0): void
    {
        if (
            $this->db()->execute(
                'SELECT id FROM wiki_roles WHERE wiki_id = ? AND name = ? AND id <> ?',
                [$wikiId, $name, $exceptId],
            )->fetch('assoc')
        ) {
            throw new InvalidArgumentException('That role name is already in use in this wiki.');
        }
    }

    /** @return void */
    private function replaceAssignments(int $roleId, array $permissions, array $members): void
    {
        $this->db()->delete('wiki_role_permissions', ['wiki_role_id' => $roleId]);
        $this->db()->delete('wiki_role_members', ['wiki_role_id' => $roleId]);
        foreach ($permissions as $permission) {
            $this->db()->insert('wiki_role_permissions', ['wiki_role_id' => $roleId, 'permission' => $permission]);
        }
        foreach ($members as $id) {
            $this->db()->insert('wiki_role_members', ['wiki_role_id' => $roleId, 'user_id' => (int)$id]);
        }
    }
}
