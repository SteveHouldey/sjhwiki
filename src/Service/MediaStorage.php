<?php
declare(strict_types=1);

namespace App\Service;

use Cake\Database\Connection;
use finfo;
use InvalidArgumentException;
use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;

class MediaStorage
{
    public const MAX_BYTES = 20 * 1024 * 1024;
    private const TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];

    /** @param \Cake\Database\Connection $db Database connection. */
    public function __construct(private Connection $db)
    {
    }

    /** @return array */
    public function store(int $wikiId, int $userId, UploadedFileInterface $upload): array
    {
        if ($upload->getError() !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('Image upload failed. Check the file and PHP upload size limits.');
        }
        $size = $upload->getSize();
        if ($size === null || $size < 1 || $size > self::MAX_BYTES) {
            throw new InvalidArgumentException('Images must be no larger than 20 MiB.');
        }
        $stream = $upload->getStream();
        if ($stream->isSeekable()) {
            $stream->rewind();
        }
        $bytes = $stream->getContents();
        if (strlen($bytes) !== $size || strlen($bytes) > self::MAX_BYTES) {
            throw new InvalidArgumentException('The uploaded image size is invalid.');
        }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged -- Invalid image data is reported below.
        $image = @getimagesizefromstring($bytes);
        if (
            !is_string($mime) || !isset(self::TYPES[$mime]) || $image === false ||
            $image['mime'] !== $mime || $image[0] < 1 || $image[1] < 1 || $image[0] * $image[1] > 100000000
        ) {
            throw new InvalidArgumentException('Use a valid JPEG, PNG, GIF or WebP image of at most 100 megapixels.');
        }
        $hash = hash('sha256', $bytes);
        $existing = $this->db->execute(
            'SELECT * FROM wiki_media WHERE wiki_id = ? AND sha256 = ?',
            [$wikiId, $hash],
        )->fetch('assoc');
        if ($existing) {
            $this->read($existing);

            return $existing;
        }
        $media = [
            'wiki_id' => $wikiId,
            'user_id' => $userId,
            'sha256' => $hash,
            'extension' => self::TYPES[$mime],
            'mime_type' => $mime,
            'file_size' => $size,
            'created' => gmdate('Y-m-d H:i:s'),
            'git_synced' => 0,
        ];
        $path = $this->path($media, true);
        // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged -- Existing content is verified below.
        $file = @fopen($path, 'xb');
        if ($file === false) {
            if (!is_file($path) || !hash_equals($hash, (string)hash_file('sha256', $path))) {
                throw new RuntimeException('Cannot write the image to protected storage.');
            }
        } else {
            try {
                $written = 0;
                while ($written < $size) {
                    $count = fwrite($file, substr($bytes, $written));
                    if ($count === false || $count === 0) {
                        throw new RuntimeException('Cannot write the complete image to protected storage.');
                    }
                    $written += $count;
                }
            } catch (RuntimeException $exception) {
                fclose($file);
                unlink($path);
                throw $exception;
            }
            if (!fclose($file)) {
                unlink($path);
                throw new RuntimeException('Cannot finish writing the image to protected storage.');
            }
        }
        $media['id'] = $this->db->transactional(function () use ($media): int {
            $record = $this->db->insert('wiki_media', $media);
            $id = (int)$record->lastInsertId('wiki_media');
            $this->db->insert('wiki_git_queue', ['kind' => 'media', 'entity_id' => $id]);

            return $id;
        });

        return $media;
    }

    /** @return string */
    public function read(array $media): string
    {
        $path = $this->path($media);
        if (!is_file($path) || is_link($path)) {
            throw new RuntimeException('The protected image file is missing.');
        }
        $bytes = file_get_contents($path);
        if (
            $bytes === false || strlen($bytes) !== (int)$media['file_size'] ||
            !hash_equals($media['sha256'], hash('sha256', $bytes))
        ) {
            throw new RuntimeException('The protected image failed its integrity check.');
        }

        return $bytes;
    }

    /** @return string */
    private function path(array $media, bool $create = false): string
    {
        $configured = getenv('WIKI_MEDIA_DIR');
        if (is_string($configured) && $configured !== '') {
            $root = realpath($configured);
        } else {
            $parent = realpath(ROOT . DS . 'resources');
            if (!$parent) {
                throw new RuntimeException('The media storage parent directory is missing.');
            }
            $directory = $parent . DS . 'wiki-media';
            if ($create && !is_dir($directory) && !mkdir($directory, 0700)) {
                throw new RuntimeException('Cannot create protected image storage.');
            }
            $root = realpath($directory);
        }
        $webroot = realpath(WWW_ROOT);
        if (
            !$root || !$webroot || !is_dir($root) || is_link($root) ||
            $root === $webroot || str_starts_with($root . DS, $webroot . DS)
        ) {
            throw new RuntimeException('Configure WIKI_MEDIA_DIR as a directory outside the web root.');
        }
        if (
            (int)$media['wiki_id'] < 1 || !preg_match('/\A[0-9a-f]{64}\z/D', $media['sha256']) ||
            !in_array($media['extension'], self::TYPES, true)
        ) {
            throw new RuntimeException('Invalid protected image metadata.');
        }
        $directory = $root . DS . (int)$media['wiki_id'];
        if ($create && !is_dir($directory) && !mkdir($directory, 0700)) {
            throw new RuntimeException('Cannot create the wiki image directory.');
        }
        if (!is_dir($directory) || is_link($directory)) {
            throw new RuntimeException('The wiki image directory is missing or unsafe.');
        }

        return $directory . DS . $media['sha256'] . '.' . $media['extension'];
    }
}
