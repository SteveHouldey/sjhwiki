<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\GitSync;
use App\Service\GitSyncException;
use App\Service\MediaStorage;
use Cake\Http\Exception\ForbiddenException;
use Cake\Http\Exception\NotFoundException;
use Cake\Http\Response;
use Cake\Routing\Router;
use InvalidArgumentException;
use Psr\Http\Message\UploadedFileInterface;

class WikiMediaController extends AppController
{
    /** @return \Cake\Http\Response */
    public function upload(string $wikiId): Response
    {
        $this->request->allowMethod(['post']);
        $access = $this->wikiAccess();
        $wiki = $access->wiki((int)$wikiId);
        $user = $this->currentUser();
        if (!$user || (!$access->can($wiki, 'create') && !$access->can($wiki, 'edit'))) {
            throw new ForbiddenException('You do not have permission to upload images.');
        }
        $upload = $this->request->getData('image');
        try {
            if (!$upload instanceof UploadedFileInterface) {
                throw new InvalidArgumentException(
                    'Choose an image to upload. Check PHP upload limits for large files.',
                );
            }
            $media = (new MediaStorage($this->db()))->store((int)$wikiId, $user['id'], $upload);
        } catch (InvalidArgumentException $exception) {
            return $this->json(['error' => $exception->getMessage()], 422);
        }
        try {
            (new GitSync($this->db()))->synchronize(1);
        } catch (GitSyncException $exception) {
            // The image remains saved and queued for an administrator to retry.
        }
        $synced = (bool)$this->db()->execute(
            'SELECT git_synced FROM wiki_media WHERE id = ?',
            [$media['id']],
        )->fetchColumn(0);

        return $this->json([
            'url' => Router::url('/wikis/' . $wikiId . '/media/' . $media['id']),
            'synced' => $synced,
        ], 201);
    }

    /** @return \Cake\Http\Response */
    public function view(string $wikiId, string $mediaId): Response
    {
        $this->request->allowMethod(['get', 'head']);
        $this->wikiAccess()->wiki((int)$wikiId);
        $media = $this->db()->execute(
            'SELECT * FROM wiki_media WHERE wiki_id = ? AND id = ?',
            [(int)$wikiId, (int)$mediaId],
        )->fetch('assoc');
        if (!$media) {
            throw new NotFoundException();
        }
        $bytes = (new MediaStorage($this->db()))->read($media);

        return $this->response
            ->withType($media['mime_type'])
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('Cache-Control', 'private, no-store')
            ->withStringBody($bytes);
    }

    /** @return \Cake\Http\Response */
    private function json(array $data, int $status): Response
    {
        return $this->response->withStatus($status)->withType('application/json')
            ->withStringBody(json_encode($data, JSON_THROW_ON_ERROR));
    }
}
