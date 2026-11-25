<?php
declare(strict_types=1);

namespace App\Service;

use Cake\Database\Connection;
use Cake\Http\Exception\ForbiddenException;
use Cake\Http\Exception\NotFoundException;

class WikiAccess
{
    public const PERMISSIONS = [
        'read' => 'Read pages',
        'create' => 'Create pages',
        'edit' => 'Edit pages',
        'delete' => 'Delete pages',
        'history' => 'View revision history',
        'restore' => 'Restore revisions',
        'manage_wiki' => 'Manage wiki settings',
        'manage_roles' => 'Manage roles and membership',
    ];

    /** @param \Cake\Database\Connection $db Database connection. */
    public function __construct(private Connection $db, private ?array $user)
    {
    }

    /** @return array */
    public function wiki(int $id): array
    {
        $wiki = $this->db->execute('SELECT * FROM wikis WHERE id = ?', [$id])->fetch('assoc');
        if (!$wiki || !$this->can($wiki, 'read')) {
            throw new NotFoundException();
        }

        return $wiki;
    }

    /** @return bool */
    public function can(array $wiki, string $permission): bool
    {
        if (!array_key_exists($permission, self::PERMISSIONS)) {
            return false;
        }
        if ($permission === 'read' && (bool)$wiki['is_public']) {
            return true;
        }
        if (!$this->user) {
            return false;
        }
        if ($this->user['is_admin']) {
            return true;
        }

        return (bool)$this->db->execute(
            'SELECT 1 FROM wiki_roles r
            INNER JOIN wiki_role_members m ON m.wiki_role_id = r.id
            INNER JOIN wiki_role_permissions p ON p.wiki_role_id = r.id
            WHERE r.wiki_id = ? AND m.user_id = ? AND p.permission = ? LIMIT 1',
            [(int)$wiki['id'], $this->user['id'], $permission],
        )->fetch('num');
    }

    /** @return void */
    public function require(array $wiki, string $permission): void
    {
        if (!$this->can($wiki, $permission)) {
            throw new ForbiddenException('You do not have permission to perform this action.');
        }
    }
}
