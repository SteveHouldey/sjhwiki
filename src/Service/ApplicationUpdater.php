<?php
declare(strict_types=1);

namespace App\Service;

use RuntimeException;
use ZipArchive;

class ApplicationUpdater
{
    public const VERSION = '1.0.1';
    public const REPOSITORY = 'SteveHouldey/sjhwiki';

    /** @return array<string,mixed>|null */
    public function latest(): ?array
    {
        $url = 'https://api.github.com/repos/' . self::REPOSITORY . '/releases/latest';
        $release = $this->requestJson($url);
        if (!$release || !isset($release['tag_name'])) {
            return null;
        }
        $tag = trim((string)$release['tag_name']);

        return [
            'tag' => $tag,
            'version' => ltrim($tag, 'v'),
            'name' => (string)($release['name'] ?? $tag),
            'notes' => (string)($release['body'] ?? ''),
            'published_at' => (string)($release['published_at'] ?? ''),
            'url' => (string)($release['html_url'] ?? ''),
            'assets' => array_values(array_filter(
                (array)($release['assets'] ?? []),
                fn(array $asset): bool => is_string($asset['name'] ?? null),
            )),
        ];
    }

    /** @return array<int,array<string,mixed>> */
    public function changelog(int $limit = 10): array
    {
        $limit = max(1, min($limit, 20));
        $url = 'https://api.github.com/repos/' . self::REPOSITORY
            . '/releases?per_page=' . $limit;
        $releases = $this->requestJsonList($url);
        $changelog = [];
        foreach ($releases as $release) {
            if (!isset($release['tag_name']) || ($release['draft'] ?? false)) {
                continue;
            }
            $tag = trim((string)$release['tag_name']);
            $changelog[] = [
                'tag' => $tag,
                'version' => ltrim($tag, 'v'),
                'name' => (string)($release['name'] ?? $tag),
                'notes' => (string)($release['body'] ?? ''),
                'published_at' => (string)($release['published_at'] ?? ''),
                'prerelease' => (bool)($release['prerelease'] ?? false),
                'url' => (string)($release['html_url'] ?? ''),
            ];
        }

        return $changelog;
    }

    /** @return array<string,mixed>|null */
    public function check(): ?array
    {
        $release = $this->latest();
        if (!$release) {
            return null;
        }
        $available = $this->compareVersions((string)$release['version'], self::VERSION) > 0;

        return $release + [
            'current' => self::VERSION,
            'available' => $available,
        ];
    }

    /** @return array<string,string> */
    public function download(array $release): array
    {
        $package = null;
        $checksum = null;
        foreach ((array)($release['assets'] ?? []) as $asset) {
            $name = strtolower((string)($asset['name'] ?? ''));
            $url = (string)($asset['browser_download_url'] ?? '');
            if ($url === '') {
                continue;
            }
            if ($package === null && str_ends_with($name, '.zip')) {
                $package = $asset;
            } elseif ($checksum === null && (
                str_ends_with($name, '.sha256') ||
                str_ends_with($name, '.sha256.txt')
            )) {
                $checksum = $asset;
            }
        }
        if (!$package || !$checksum) {
            throw new RuntimeException(
                'This release must provide a .zip package and a matching SHA-256 checksum file.',
            );
        }

        $updates = TMP . 'updates';
        if (!is_dir($updates) && !mkdir($updates, 0700, true)) {
            throw new RuntimeException('Cannot create the update staging directory.');
        }
        $token = bin2hex(random_bytes(16));
        $archive = $updates . DS . $token . '.zip';
        $checksumFile = $updates . DS . $token . '.sha256';
        $this->downloadFile((string)$package['browser_download_url'], $archive);
        $this->downloadFile((string)$checksum['browser_download_url'], $checksumFile);

        $expected = $this->parseChecksum(file_get_contents($checksumFile));
        $actual = hash_file('sha256', $archive);
        if ($expected === null || $actual === false || !hash_equals(strtolower($expected), strtolower($actual))) {
            @unlink($archive);
            @unlink($checksumFile);
            throw new RuntimeException('The downloaded update failed its SHA-256 checksum verification.');
        }

        return ['archive' => $archive, 'checksum' => $checksumFile];
    }

    /** @return array<string,string|bool> */
    public function apply(array $release): array
    {
        $download = $this->download($release);
        $updates = TMP . 'updates';
        $extract = $updates . DS . 'extract-' . bin2hex(random_bytes(8));
        $backup = $updates . DS . 'backup-' . bin2hex(random_bytes(8));
        if (!mkdir($extract, 0700, true) || !mkdir($backup, 0700, true)) {
            throw new RuntimeException('Cannot create update directories.');
        }

        $zip = new ZipArchive();
        if ($zip->open($download['archive']) !== true) {
            throw new RuntimeException('Cannot open the downloaded update package.');
        }
        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if (!is_string($name) || $this->unsafeArchiveName($name)) {
                    throw new RuntimeException('The update package contains an unsafe path.');
                }
            }
            if (!$zip->extractTo($extract)) {
                throw new RuntimeException('Cannot extract the update package.');
            }
        } finally {
            $zip->close();
        }

        $source = $extract;
        $children = array_values(array_diff(scandir($source) ?: [], ['.', '..']));
        if (count($children) === 1 && is_dir($source . DS . $children[0])) {
            $source = $source . DS . $children[0];
        }

        $this->copyDirectory(ROOT, $backup, [
            '.git', 'logs', 'tmp', 'vendor', 'config/app_local.php',
            'config/setup-token.txt', 'resources/wiki-media',
        ]);
        $this->copyDirectory($source, ROOT, [
            '.git', 'config/app_local.php', 'config/setup-token.txt',
            'resources/wiki-media', 'tmp', 'logs',
        ]);

        @unlink($download['archive']);
        @unlink($download['checksum']);
        $this->removeDirectory($extract);

        return [
            'success' => true,
            'backup' => $backup,
            'version' => (string)$release['version'],
        ];
    }

    /** @return int */
    public function compareVersions(string $left, string $right): int
    {
        return version_compare($this->versionToken($left), $this->versionToken($right));
    }

    /** @return array<string,mixed>|null */
    private function requestJson(string $url): ?array
    {
        $json = $this->request($url);
        if ($json === null) {
            return null;
        }
        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : null;
    }

    /** @return array<int,array<string,mixed>> */
    private function requestJsonList(string $url): array
    {
        $json = $this->request($url);
        if ($json === null) {
            return [];
        }
        $decoded = json_decode($json, true);

        return is_array($decoded) ? array_values(array_filter(
            $decoded,
            fn(mixed $release): bool => is_array($release),
        )) : [];
    }

    /** @return string|null */
    private function request(string $url): ?string
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 20,
                'follow_location' => 1,
                'header' => "Accept: application/vnd.github+json\r\n"
                    . "User-Agent: SJHWiki-Updater\r\n"
                    . "X-GitHub-Api-Version: 2022-11-28\r\n",
            ],
        ]);
        $result = @file_get_contents($url, false, $context);

        return is_string($result) ? $result : null;
    }

    private function downloadFile(string $url, string $destination): void
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 60,
                'follow_location' => 1,
                'header' => "Accept: application/octet-stream\r\n"
                    . "User-Agent: SJHWiki-Updater\r\n",
            ],
        ]);
        $stream = @fopen($url, 'rb', false, $context);
        if ($stream === false) {
            throw new RuntimeException('Cannot download the update from GitHub.');
        }
        $file = @fopen($destination, 'xb');
        if ($file === false) {
            fclose($stream);
            throw new RuntimeException('Cannot create the update download file.');
        }
        try {
            while (!feof($stream)) {
                $chunk = fread($stream, 1024 * 1024);
                if ($chunk === false) {
                    throw new RuntimeException('Cannot read the update download.');
                }
                if ($chunk !== '' && fwrite($file, $chunk) !== strlen($chunk)) {
                    throw new RuntimeException('Cannot write the update download.');
                }
            }
        } finally {
            fclose($file);
            fclose($stream);
        }
    }

    /** @return string|null */
    private function parseChecksum(mixed $contents): ?string
    {
        if (!is_string($contents) || !preg_match('/\b([a-fA-F0-9]{64})\b/', $contents, $match)) {
            return null;
        }

        return $match[1];
    }

    private function unsafeArchiveName(string $name): bool
    {
        $normalized = str_replace('\\', '/', $name);

        return str_starts_with($normalized, '/') ||
            str_contains($normalized, '../') ||
            str_starts_with($normalized, '../');
    }

    /** @return string */
    private function versionToken(string $version): string
    {
        return preg_replace('/[^0-9.].*$/', '', ltrim(trim($version), 'v')) ?: '0';
    }

    /** @param array<string> $excluded */
    private function copyDirectory(string $source, string $destination, array $excluded): void
    {
        $source = rtrim($source, DS);
        $destination = rtrim($destination, DS);
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );
        foreach ($iterator as $item) {
            /** @var \SplFileInfo $item */
            $relative = substr($item->getPathname(), strlen($source) + 1);
            $relative = str_replace('\\', '/', $relative);
            if ($this->isExcluded($relative, $excluded)) {
                continue;
            }
            $target = $destination . DS . str_replace('/', DS, $relative);
            if ($item->isDir()) {
                if (!is_dir($target) && !mkdir($target, 0700, true)) {
                    throw new RuntimeException('Cannot create update directory: ' . $relative);
                }
            } elseif ($item->isFile()) {
                $parent = dirname($target);
                if (!is_dir($parent) && !mkdir($parent, 0700, true)) {
                    throw new RuntimeException('Cannot create update destination directory.');
                }
                if (!copy($item->getPathname(), $target)) {
                    throw new RuntimeException('Cannot copy update file: ' . $relative);
                }
            }
        }
    }

    /** @param array<string> $excluded */
    private function isExcluded(string $relative, array $excluded): bool
    {
        foreach ($excluded as $path) {
            $path = str_replace('\\', '/', trim($path, '/'));
            if ($relative === $path || str_starts_with($relative, $path . '/')) {
                return true;
            }
        }

        return false;
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }
        @rmdir($directory);
    }
}
