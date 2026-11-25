<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\GitSync;
use App\Service\GitSyncException;
use App\Service\PageWriter;
use App\Service\RichText;
use App\Service\WikiTree;
use Cake\Http\Exception\BadRequestException;
use Cake\Http\Exception\ConflictException;
use Cake\Http\Exception\NotFoundException;
use Cake\Http\Response;
use InvalidArgumentException;

class WikiPagesController extends AppController
{
    /** @return void */
    public function view(string $wikiId, string $pageId): void
    {
        $access = $this->wikiAccess();
        $wiki = $access->wiki((int)$wikiId);
        $page = $this->page((int)$wikiId, (int)$pageId);
        if ($page['deleted']) {
            throw new NotFoundException();
        }
        $rendered = RichText::render($page['body'], $page['content_format']);
        $canEdit = $access->can($wiki, 'edit');
        $canDelete = $access->can($wiki, 'delete');
        $canHistory = $access->can($wiki, 'history');
        $pages = $this->db()->execute(
            'SELECT id, path, title FROM wiki_pages WHERE wiki_id = ? AND deleted = 0 ORDER BY path',
            [(int)$wikiId],
        )->fetchAll('assoc');
        $tree = WikiTree::build($pages);
        $this->set(compact('wiki', 'page', 'rendered', 'canEdit', 'canDelete', 'canHistory', 'tree'));
    }

    /** @return \Cake\Http\Response|null */
    public function add(string $wikiId): ?Response
    {
        $access = $this->wikiAccess();
        $wiki = $access->wiki((int)$wikiId);
        $access->require($wiki, 'create');
        $page = null;
        $this->set(compact('wiki', 'page'));
        if (!$this->request->is('post')) {
            $this->render('form');

            return null;
        }
        try {
            $path = PageWriter::path($this->request->getData('path'));
            $title = PageWriter::title($this->request->getData('title'));
            $body = PageWriter::body($this->request->getData('body'));
            if (
                $this->db()->execute(
                    'SELECT id FROM wiki_pages WHERE wiki_id = ? AND path = ?',
                    [(int)$wikiId, $path],
                )->fetch('assoc')
            ) {
                throw new InvalidArgumentException('This path is already used, including by deleted pages.');
            }
        } catch (InvalidArgumentException $exception) {
            $this->Flash->error($exception->getMessage());
            $this->render('form');

            return null;
        }
        $user = $this->currentUser();
        $id = (new PageWriter($this->db()))->create((int)$wikiId, $user['id'], $path, $title, $body);
        $this->reportSync($id, 'Page created.');

        return $this->redirect('/wikis/' . $wikiId . '/pages/' . $id);
    }

    /** @return \Cake\Http\Response|null */
    public function edit(string $wikiId, string $pageId): ?Response
    {
        $access = $this->wikiAccess();
        $wiki = $access->wiki((int)$wikiId);
        $access->require($wiki, 'edit');
        $page = $this->page((int)$wikiId, (int)$pageId);
        if ($page['deleted']) {
            throw new NotFoundException();
        }
        if ($page['content_format'] === 'markdown') {
            $page['body'] = RichText::markdown($page['body']);
        }
        $this->set(compact('wiki', 'page'));
        if (!$this->request->is(['post', 'put'])) {
            $this->render('form');

            return null;
        }
        try {
            $title = PageWriter::title($this->request->getData('title'));
            $body = PageWriter::body($this->request->getData('body'));
            $version = $this->version();
            $user = $this->currentUser();
            (new PageWriter($this->db()))->change($page, $user['id'], $title, $body, 'edit', $version);
        } catch (InvalidArgumentException | ConflictException $exception) {
            $this->Flash->error($exception->getMessage());
            if ($exception instanceof ConflictException) {
                $this->response = $this->response->withStatus(409);
            }
            $this->render('form');

            return null;
        }
        $this->reportSync((int)$pageId, 'Page saved.');

        return $this->redirect('/wikis/' . $wikiId . '/pages/' . $pageId);
    }

    /** @return \Cake\Http\Response */
    public function delete(string $wikiId, string $pageId): Response
    {
        $this->request->allowMethod(['post']);
        $access = $this->wikiAccess();
        $wiki = $access->wiki((int)$wikiId);
        $access->require($wiki, 'delete');
        $page = $this->page((int)$wikiId, (int)$pageId);
        if ($page['deleted']) {
            throw new NotFoundException();
        }
        $user = $this->currentUser();
        (new PageWriter($this->db()))->change(
            $page,
            $user['id'],
            $page['title'],
            $page['body'],
            'delete',
            $this->version(),
            $page['content_format'],
        );
        $this->reportSync((int)$pageId, 'Page deleted. Its history is retained.');

        return $this->redirect('/wikis/' . $wikiId);
    }

    /** @return void */
    public function history(string $wikiId, string $pageId): void
    {
        $access = $this->wikiAccess();
        $wiki = $access->wiki((int)$wikiId);
        $access->require($wiki, 'history');
        $page = $this->page((int)$wikiId, (int)$pageId);
        $revisions = $this->db()->execute(
            'SELECT r.*, u.username FROM wiki_page_revisions r
            LEFT JOIN users u ON u.id = r.user_id WHERE r.wiki_page_id = ? ORDER BY r.id DESC',
            [(int)$pageId],
        )->fetchAll('assoc');
        foreach ($revisions as &$revision) {
            $revision['rendered'] = RichText::render($revision['body'], $revision['content_format']);
        }
        unset($revision);
        $canRestore = $access->can($wiki, 'restore');
        $this->set(compact('wiki', 'page', 'revisions', 'canRestore'));
    }

    /** @return \Cake\Http\Response */
    public function restore(string $wikiId, string $pageId, string $revisionId): Response
    {
        $this->request->allowMethod(['post']);
        $access = $this->wikiAccess();
        $wiki = $access->wiki((int)$wikiId);
        $access->require($wiki, 'history');
        $access->require($wiki, 'restore');
        $page = $this->page((int)$wikiId, (int)$pageId);
        $revision = $this->db()->execute(
            'SELECT * FROM wiki_page_revisions WHERE id = ? AND wiki_page_id = ?',
            [(int)$revisionId, (int)$pageId],
        )->fetch('assoc');
        if (!$revision) {
            throw new NotFoundException();
        }
        try {
            $body = PageWriter::body(RichText::render($revision['body'], $revision['content_format']));
        } catch (InvalidArgumentException $exception) {
            $this->Flash->error('Cannot restore this revision: ' . $exception->getMessage());

            return $this->redirect('/wikis/' . $wikiId . '/pages/' . $pageId . '/history');
        }
        $user = $this->currentUser();
        (new PageWriter($this->db()))->change(
            $page,
            $user['id'],
            $revision['title'],
            $body,
            'restore',
            $this->version(),
        );
        $this->reportSync((int)$pageId, 'Revision restored as a new version.');

        return $this->redirect('/wikis/' . $wikiId . '/pages/' . $pageId);
    }

    /** @return array */
    private function page(int $wikiId, int $pageId): array
    {
        $page = $this->db()->execute(
            'SELECT * FROM wiki_pages WHERE id = ? AND wiki_id = ?',
            [$pageId, $wikiId],
        )->fetch('assoc');
        if (!$page) {
            throw new NotFoundException();
        }

        return $page;
    }

    /** @return int */
    private function version(): int
    {
        $version = $this->request->getData('version');
        if ((!is_string($version) && !is_int($version)) || !ctype_digit((string)$version) || (int)$version < 1) {
            throw new BadRequestException('A valid page version is required.');
        }

        return (int)$version;
    }

    /** @return void */
    private function reportSync(int $pageId, string $message): void
    {
        try {
            (new GitSync($this->db()))->synchronize(1);
        } catch (GitSyncException $exception) {
            $this->Flash->warning($message . ' Git synchronization failed; an administrator can retry it.');

            return;
        }
        $revision = $this->db()->execute(
            'SELECT git_synced FROM wiki_page_revisions WHERE wiki_page_id = ? ORDER BY id DESC LIMIT 1',
            [$pageId],
        )->fetch('assoc');
        if (!$revision['git_synced']) {
            $this->Flash->warning($message . ' Git synchronization is pending.');

            return;
        }
        $this->Flash->success($message . ' Git synchronization completed.');
    }
}
