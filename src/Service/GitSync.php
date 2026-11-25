<?php
declare(strict_types=1);

namespace App\Service;

use Cake\Database\Connection;
use Cake\Log\Log;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessSignaledException;
use Symfony\Component\Process\Exception\ProcessStartFailedException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

class GitSync
{
    private string $repository;
    private ?string $indexPath = null;
    private ?string $indexDirectory = null;

    /** @param \Cake\Database\Connection $db Database connection. */
    public function __construct(private Connection $db)
    {
    }

    /** @return int */
    public function synchronize(int $limit = 10): int
    {
        $event = $this->nextEvent();
        if (!$event) {
            return 0;
        }

        $lock = null;
        try {
            if (!function_exists('proc_open')) {
                throw new GitSyncException('Git synchronization requires PHP process execution (proc_open).');
            }
            $configured = getenv('WIKI_GIT_DIR');
            $path = is_string($configured) ? realpath($configured) : false;
            $webroot = realpath(WWW_ROOT);
            if (
                !$path || !is_dir($path) || !$webroot ||
                $path === $webroot || str_starts_with($path . DIRECTORY_SEPARATOR, $webroot . DIRECTORY_SEPARATOR)
            ) {
                throw new GitSyncException('Configure WIKI_GIT_DIR as a bare Git repository outside the web root.');
            }
            $this->repository = $path;
            if (trim($this->git(['rev-parse', '--is-bare-repository'], 'inspect repository')) !== 'true') {
                throw new GitSyncException('The configured Git repository must be bare.');
            }
            // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged -- Converted to a safe error below.
            $lock = @fopen($path . DIRECTORY_SEPARATOR . 'wiki-sync.lock', 'c');
            if (!$lock) {
                throw new GitSyncException('Cannot open the Git synchronization lock.');
            }
            if (!flock($lock, LOCK_EX | LOCK_NB)) {
                throw new GitSyncException('Another Git synchronization is already running.');
            }

            $event = $this->nextEvent();
            if (!$event) {
                return 0;
            }
            $count = 0;
            do {
                $this->syncEvent($event);
                $count++;
                $event = $count < $limit ? $this->nextEvent() : null;
            } while ($event);

            return $count;
        } catch (GitSyncException $exception) {
            if ($event) {
                $this->db->update('wiki_git_queue', ['git_error' => $exception->getMessage()], ['id' => $event['id']]);
                $table = match ($event['kind']) {
                    'revision' => 'wiki_page_revisions',
                    'media' => 'wiki_media',
                    default => null,
                };
                if ($table !== null) {
                    $this->db->update($table, ['git_error' => $exception->getMessage()], [
                        'id' => (int)$event['entity_id'], 'git_synced' => 0,
                    ]);
                }
            }
            Log::error('Wiki Git synchronization failed: ' . $exception->getMessage());
            throw $exception;
        } finally {
            $this->cleanupIndex();
            if (is_resource($lock)) {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
        }
    }

    /** @return array|false */
    private function nextEvent(): array|false
    {
        return $this->db->execute(
            'SELECT * FROM wiki_git_queue ORDER BY id LIMIT 1',
        )->fetch('assoc');
    }

    /** @return void */
    private function syncEvent(array $event): void
    {
        if ($event['kind'] === 'revision') {
            $record = $this->db->execute(
                'SELECT r.*, p.wiki_id, w.slug AS wiki_slug FROM wiki_page_revisions r
                INNER JOIN wiki_pages p ON p.id = r.wiki_page_id
                INNER JOIN wikis w ON w.id = p.wiki_id WHERE r.id = ?',
                [$event['entity_id']],
            )->fetch('assoc');
            $table = 'wiki_page_revisions';
        } elseif ($event['kind'] === 'media') {
            $record = $this->db->execute(
                'SELECT m.*, w.slug AS wiki_slug FROM wiki_media m
                INNER JOIN wikis w ON w.id = m.wiki_id WHERE m.id = ?',
                [$event['entity_id']],
            )->fetch('assoc');
            $table = 'wiki_media';
        } else {
            throw new GitSyncException('The Git queue contains an unknown event.');
        }
        if (!$record || !preg_match('/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/D', $record['wiki_slug'])) {
            throw new GitSyncException('The queued item is missing or has an invalid wiki slug.');
        }
        if ($event['kind'] === 'revision') {
            try {
                PageWriter::path($record['path']);
            } catch (InvalidArgumentException $exception) {
                throw new GitSyncException('The queued revision has an invalid page path.', 0, $exception);
            }
            if (!in_array($record['operation'], ['create', 'edit', 'delete', 'restore'], true)) {
                throw new GitSyncException('The queued revision has an invalid operation.');
            }
            $path = 'wikis/' . $record['wiki_slug'] . '/' . $record['path'] . '.md';
            $message = 'Wiki revision ' . $record['id'] . "\n\nWiki-Revision: " . $record['id'];
            $body = $record['content_format'] === 'html' ? $this->exportImages($record) : $record['body'];
            $content = "---\ntitle: " . json_encode(
                $record['title'],
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            ) . "\n---\n\n" . $body;
            $operation = $record['operation'];
        } else {
            try {
                $content = (new MediaStorage($this->db))->read($record);
            } catch (RuntimeException $exception) {
                throw new GitSyncException('Cannot synchronize the image: ' . $exception->getMessage(), 0, $exception);
            }
            $path = 'wikis/' . $record['wiki_slug'] . '/_media/' . $record['sha256'] . '.' . $record['extension'];
            $message = 'Wiki image ' . $record['id'] . "\n\nWiki-Media: " . $record['id'];
            $operation = 'image';
        }
        $ref = trim($this->git(['symbolic-ref', 'HEAD'], 'identify branch'));
        if (!str_starts_with($ref, 'refs/heads/')) {
            throw new GitSyncException('The bare Git repository must have a branch checked out in HEAD.');
        }
        $head = trim($this->git(['rev-parse', '--verify', '--quiet', 'HEAD'], 'read HEAD', null, [0, 1]));
        $commit = $event['git_commit'];
        if ($commit !== null && $commit !== '') {
            if (!hash_equals($commit, $head)) {
                throw new GitSyncException('The local Git branch changed while a revision was pending.');
            }
        } elseif ($head !== '' && trim($this->git(['show', '-s', '--format=%B', $head], 'read commit')) === $message) {
            $this->verifyRecoveredCommit($head, $path, $content, $operation);
            $commit = $head;
        } else {
            $commit = $this->createCommit($head, $ref, $path, $content, $message, $operation);
        }
        $this->db->transactional(function () use ($event, $table, $commit): void {
            $this->db->update('wiki_git_queue', ['git_commit' => $commit], ['id' => (int)$event['id']]);
            $this->db->update($table, ['git_commit' => $commit], ['id' => (int)$event['entity_id']]);
        });

        $this->git(['push', 'origin', $commit . ':' . $ref], 'push to origin');
        $this->db->transactional(function () use ($event, $table): void {
            $this->db->update($table, ['git_synced' => 1, 'git_error' => null], ['id' => (int)$event['entity_id']]);
            $this->db->delete('wiki_git_queue', ['id' => (int)$event['id']]);
        });
    }

    /** @return string */
    private function exportImages(array $revision): string
    {
        return preg_replace_callback(
            '~\bsrc="(/(?:[^"?#]*/)?wikis/(\d+)/media/(\d+))"~',
            function (array $match) use ($revision): string {
                if ((int)$match[2] !== (int)$revision['wiki_id']) {
                    return $match[0];
                }
                $media = $this->db->execute(
                    'SELECT sha256, extension FROM wiki_media WHERE wiki_id = ? AND id = ?',
                    [$revision['wiki_id'], (int)$match[3]],
                )->fetch('assoc');
                if (!$media) {
                    return $match[0];
                }

                return 'src="' . str_repeat('../', substr_count($revision['path'], '/')) .
                    '_media/' . $media['sha256'] . '.' . $media['extension'] . '"';
            },
            $revision['body'],
        );
    }

    /** @return string */
    private function createCommit(
        string $head,
        string $ref,
        string $path,
        string $content,
        string $message,
        string $operation,
    ): string {
        $temporary = tempnam(TMP, 'wiki-index-');
        if ($temporary === false) {
            throw new GitSyncException('Cannot create a temporary Git index.');
        }
        $this->indexPath = $temporary;
        if (!unlink($temporary)) {
            throw new GitSyncException('Cannot prepare a temporary Git index.');
        }
        $this->indexPath = null;
        if (!mkdir($temporary, 0700)) {
            throw new GitSyncException('Cannot create a private temporary Git directory.');
        }
        $this->indexDirectory = $temporary;
        $this->indexPath = $temporary . DIRECTORY_SEPARATOR . 'index';
        $this->git($head === '' ? ['read-tree', '--empty'] : ['read-tree', $head], 'prepare Git index');
        if ($operation === 'delete') {
            $format = trim($this->git(['rev-parse', '--show-object-format'], 'inspect Git object format'));
            if (!in_array($format, ['sha1', 'sha256'], true)) {
                throw new GitSyncException('The Git repository has an unsupported object format.');
            }
            $zero = str_repeat('0', $format === 'sha256' ? 64 : 40);
            $this->git(['update-index', '--index-info'], 'remove page from Git', "0 {$zero}\t{$path}\n");
        } else {
            $blob = trim($this->git(['hash-object', '-w', '--stdin'], 'write page to Git', $content));
            if (!preg_match('/\A[0-9a-f]{40,64}\z/D', $blob)) {
                throw new GitSyncException('Git returned an invalid object identifier.');
            }
            $this->git(['update-index', '--add', '--cacheinfo', '100644', $blob, $path], 'stage page in Git');
        }
        $tree = trim($this->git(['write-tree'], 'write Git tree'));
        $args = ['commit-tree', $tree];
        if ($head !== '') {
            array_push($args, '-p', $head);
        }
        array_push($args, '-m', $message);
        $commit = trim($this->git($args, 'create Git commit'));
        if (!preg_match('/\A[0-9a-f]{40,64}\z/D', $commit)) {
            throw new GitSyncException('Git returned an invalid commit identifier.');
        }
        $this->git(['update-ref', $ref, $commit, $head], 'update Git branch');
        $this->cleanupIndex();

        return $commit;
    }

    /** @return void */
    private function cleanupIndex(): void
    {
        if ($this->indexPath !== null && is_file($this->indexPath) && !unlink($this->indexPath)) {
            throw new GitSyncException('Cannot remove the temporary Git index.');
        }
        $this->indexPath = null;
        if ($this->indexDirectory !== null && is_dir($this->indexDirectory) && !rmdir($this->indexDirectory)) {
            throw new GitSyncException('Cannot remove the temporary Git directory.');
        }
        $this->indexDirectory = null;
    }

    /** @return void */
    private function verifyRecoveredCommit(string $head, string $path, string $content, string $operation): void
    {
        $object = $head . ':' . $path;
        if ($operation === 'delete') {
            $this->git(['cat-file', '-e', $object], 'verify deleted page', null, [128]);

            return;
        }
        if ($this->git(['show', $object], 'verify recovered page') !== $content) {
            throw new GitSyncException('The recovered Git commit does not match the saved revision.');
        }
    }

    /** @return string */
    private function git(array $arguments, string $action, ?string $input = null, array $allowed = [0]): string
    {
        $env = [
            'GIT_DIR' => false,
            'GIT_WORK_TREE' => false,
            'GIT_INDEX_FILE' => $this->indexPath ?? false,
            'GIT_TERMINAL_PROMPT' => '0',
        ];
        $process = new Process(array_merge(['git', '--git-dir=' . $this->repository], $arguments), null, $env);
        $process->setTimeout(30);
        if ($input !== null) {
            $process->setInput($input);
        }
        try {
            $process->run();
        } catch (ProcessTimedOutException $exception) {
            throw new GitSyncException('Git could not ' . $action . ': operation timed out.', 0, $exception);
        } catch (ProcessStartFailedException | ProcessSignaledException $exception) {
            throw new GitSyncException('Git could not ' . $action . ': process failed.', 0, $exception);
        }
        if (!in_array($process->getExitCode(), $allowed, true)) {
            throw new GitSyncException('Git could not ' . $action . ' (exit ' . $process->getExitCode() . ').');
        }

        return $process->getOutput();
    }
}
