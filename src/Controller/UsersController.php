<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\AccountInput;
use Authentication\PasswordHasher\DefaultPasswordHasher;
use Cake\Http\Exception\ForbiddenException;
use Cake\Http\Exception\NotFoundException;
use Cake\Http\Response;
use InvalidArgumentException;
use PDOException;

class UsersController extends AppController
{
    /** @return void */
    public function index(): void
    {
        $this->requireAdmin();
        $users = $this->db()->execute(
            'SELECT id, username, email, email_verified, is_admin, active FROM users ORDER BY username',
        )->fetchAll('assoc');
        $this->set(compact('users'));
    }

    /** @return \Cake\Http\Response|null */
    public function add(): ?Response
    {
        $this->requireAdmin();
        if (!$this->request->is('post')) {
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
            if ($this->db()->execute('SELECT id FROM users WHERE username = ?', [$username])->fetch('assoc')) {
                throw new InvalidArgumentException('That username is already in use.');
            }
            if ($email && $this->db()->execute('SELECT id FROM users WHERE email = ?', [$email])->fetch('assoc')) {
                throw new InvalidArgumentException('That email address is already in use.');
            }
        } catch (InvalidArgumentException $exception) {
            $this->Flash->error($exception->getMessage());

            return null;
        }

        $now = gmdate('Y-m-d H:i:s');
        try {
            $result = $this->db()->insert('users', [
                'username' => $username,
                'email' => $email,
                'password' => (new DefaultPasswordHasher())->hash($password),
                'is_admin' => (int)($this->request->getData('is_admin') === '1'),
                'active' => 1,
                'created' => $now,
                'modified' => $now,
            ]);
        } catch (PDOException $exception) {
            if (
                !$this->db()->execute(
                    'SELECT id FROM users WHERE username = ? OR email = ?',
                    [$username, $email],
                )->fetch('assoc')
            ) {
                throw $exception;
            }
            $this->Flash->error('Username or email is already in use.');

            return null;
        }
        if ($email && !$this->sendAccountEmail((int)$result->lastInsertId('users'), 'verify')) {
            $this->Flash->error(
                'User created, but the verification email could not be sent. The user can request it again.',
            );

            return $this->redirect('/admin/users');
        }
        $this->Flash->success('User created.');

        return $this->redirect('/admin/users');
    }

    /** @return \Cake\Http\Response|null */
    public function edit(string $id): ?Response
    {
        $current = $this->requireAdmin();
        $user = $this->db()->execute(
            'SELECT id, username, email, email_verified, verification_required, is_admin, active
             FROM users WHERE id = ?',
            [(int)$id],
        )->fetch('assoc');
        if (!$user) {
            throw new NotFoundException();
        }
        $this->set(compact('user'));
        if (!$this->request->is(['post', 'put'])) {
            return null;
        }

        $isAdmin = $this->request->getData('is_admin') === '1';
        $active = $this->request->getData('active') === '1';
        if ((int)$id === $current['id'] && (!$isAdmin || !$active)) {
            $this->Flash->error('You cannot disable or remove your own administrator access.');

            return null;
        }

        $password = $this->request->getData('password');
        $changes = ['is_admin' => (int)$isAdmin, 'active' => (int)$active, 'modified' => gmdate('Y-m-d H:i:s')];
        try {
            $email = $this->request->getData('email', $user['email']);
            $email = $email === '' || $email === null ? null : AccountInput::email($email);
            if (!$email && $user['verification_required']) {
                throw new InvalidArgumentException('Registered accounts must have an email address.');
            }
            if ($email !== $user['email']) {
                if (
                    $email && $this->db()->execute(
                        'SELECT id FROM users WHERE email = ? AND id != ?',
                        [$email, (int)$id],
                    )->fetch('assoc')
                ) {
                    throw new InvalidArgumentException('That email address is already in use.');
                }
                $changes['email'] = $email;
                $changes['email_verified'] = null;
            }
        } catch (InvalidArgumentException $exception) {
            $this->Flash->error($exception->getMessage());

            return null;
        }
        if ($password !== '' && $password !== null) {
            try {
                $password = AccountInput::password($password);
                if ($password !== $this->request->getData('password_confirm')) {
                    throw new InvalidArgumentException('Passwords do not match.');
                }
            } catch (InvalidArgumentException $exception) {
                $this->Flash->error($exception->getMessage());

                return null;
            }
            $changes['password'] = (new DefaultPasswordHasher())->hash($password);
        }
        $emailChanged = array_key_exists('email', $changes);
        $revoke = isset($changes['password']) || $emailChanged || ((bool)$user['active'] && !$active);
        try {
            $this->db()->transactional(function () use ($changes, $id, $revoke, $current): void {
                $this->db()->execute('UPDATE wiki_installation SET created = created WHERE id = 1');
                $administrator = $this->db()->execute(
                    'SELECT active, is_admin FROM users WHERE id = ?',
                    [$current['id']],
                )->fetch('assoc');
                if (!$administrator || !$administrator['active'] || !$administrator['is_admin']) {
                    throw new ForbiddenException('Administrator access is required.');
                }
                if ($revoke) {
                    $this->db()->execute('UPDATE users SET auth_version = auth_version + 1 WHERE id = ?', [(int)$id]);
                    $this->db()->delete('account_email_tokens', ['user_id' => (int)$id]);
                }
                $this->db()->update('users', $changes, ['id' => (int)$id]);
            });
        } catch (PDOException $exception) {
            if (
                !$email || !$this->db()->execute(
                    'SELECT id FROM users WHERE email = ? AND id != ?',
                    [$email, (int)$id],
                )->fetch('assoc')
            ) {
                throw $exception;
            }
            $this->Flash->error('That email address is already in use.');

            return null;
        }
        $redirect = $revoke && (int)$id === $current['id'] ? '/login' : '/admin/users';
        if ($emailChanged && $email && $active && !$this->sendAccountEmail((int)$id, 'verify')) {
            $this->Flash->error(
                'User updated, but the verification email could not be sent. The user can request it again.',
            );

            return $this->redirect($redirect);
        }
        $this->Flash->success($redirect === '/login' ? 'User updated. Sign in again.' : 'User updated.');

        return $this->redirect($redirect);
    }
}
