<?php
declare(strict_types=1);

namespace App\Service;

use Cake\Database\Connection;
use Cake\Http\Exception\ConflictException;
use InvalidArgumentException;

class PageWriter
{
    /** @param \Cake\Database\Connection $db Database connection. */
    public function __construct(private Connection $db)
    {
    }

    /** @return string */
    public static function path(mixed $value): string
    {
        if (
            !is_string($value) || strlen($value) > 190 ||
            !preg_match('/\A[a-z0-9]+(?:[-_][a-z0-9]+)*(?:\/[a-z0-9]+(?:[-_][a-z0-9]+)*)*\z/D', $value)
        ) {
            throw new InvalidArgumentException(
                'Paths must use lowercase letters, numbers, hyphens, underscores and slash-separated segments.',
            );
        }

        return $value;
    }

    /** @return string */
    public static function title(mixed $value): string
    {
        if (
            !is_string($value) || !mb_check_encoding($value, 'UTF-8') ||
            trim($value) === '' || mb_strlen($value) > 255
        ) {
            throw new InvalidArgumentException('A title of at most 255 characters is required.');
        }

        return trim($value);
    }

    /** @return string */
    public static function body(mixed $value): string
    {
        if (!is_string($value) || !mb_check_encoding($value, 'UTF-8') || strlen($value) > 60000) {
            throw new InvalidArgumentException('The page content must be valid UTF-8 and at most 60,000 bytes.');
        }

        $html = RichText::sanitize($value);
        if (strlen($html) > 60000) {
            throw new InvalidArgumentException('The sanitized page content must be at most 60,000 bytes.');
        }

        return $html;
    }

    /** @return int */
    public function create(int $wikiId, int $userId, string $path, string $title, string $body): int
    {
        return $this->db->transactional(function () use ($wikiId, $userId, $path, $title, $body): int {
            $now = gmdate('Y-m-d H:i:s');
            $statement = $this->db->insert('wiki_pages', [
                'wiki_id' => $wikiId,
                'path' => $path,
                'title' => $title,
                'body' => $body,
                'content_format' => 'html',
                'created' => $now,
                'modified' => $now,
            ]);
            $pageId = (int)$statement->lastInsertId('wiki_pages');
            $this->revision($pageId, $userId, $path, $title, $body, 'create', $now);

            return $pageId;
        });
    }

    /** @return void */
    public function change(
        array $page,
        ?int $userId,
        string $title,
        string $body,
        string $operation,
        int $version,
        string $contentFormat = 'html',
    ): void {
        if (!in_array($operation, ['edit', 'delete', 'restore'], true)) {
            throw new InvalidArgumentException('Invalid page operation.');
        }
        if (!in_array($contentFormat, ['markdown', 'html'], true)) {
            throw new InvalidArgumentException('Invalid content format.');
        }

        $this->db->transactional(function () use (
            $page,
            $userId,
            $title,
            $body,
            $operation,
            $version,
            $contentFormat,
        ): void {
            $now = gmdate('Y-m-d H:i:s');
            $updated = $this->db->execute(
                'UPDATE wiki_pages SET title = ?, body = ?, content_format = ?, deleted = ?,
                version = version + 1, modified = ?
                WHERE id = ? AND wiki_id = ? AND version = ?',
                [
                    $title, $body, $contentFormat, $operation === 'delete' ? 1 : 0,
                    $now, $page['id'], $page['wiki_id'], $version,
                ],
            );
            if ($updated->rowCount() !== 1) {
                throw new ConflictException('This page changed while you were editing. Reload before saving.');
            }
            $this->revision((int)$page['id'], $userId, $page['path'], $title, $body, $operation, $now, $contentFormat);
        });
    }

    /** @return void */
    private function revision(
        int $pageId,
        ?int $userId,
        string $path,
        string $title,
        string $body,
        string $operation,
        string $now,
        string $contentFormat = 'html',
    ): void {
        $revision = $this->db->insert('wiki_page_revisions', [
            'wiki_page_id' => $pageId,
            'user_id' => $userId,
            'path' => $path,
            'title' => $title,
            'body' => $body,
            'content_format' => $contentFormat,
            'operation' => $operation,
            'git_synced' => 0,
            'created' => $now,
        ]);
        $this->db->insert('wiki_git_queue', [
            'kind' => 'revision',
            'entity_id' => (int)$revision->lastInsertId('wiki_page_revisions'),
        ]);
    }
}
