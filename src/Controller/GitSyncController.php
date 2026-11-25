<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\GitSync;
use App\Service\GitSyncException;
use Cake\Http\Response;

class GitSyncController extends AppController
{
    /** @return void */
    public function index(): void
    {
        $this->requireAdmin();
        $pending = $this->db()->execute(
            "SELECT q.kind, q.git_error, COALESCE(r.operation, 'image') AS operation,
            COALESCE(r.created, m.created) AS created, COALESCE(p.path, m.sha256) AS path,
            COALESCE(w.name, mw.name) AS wiki_name
            FROM wiki_git_queue q
            LEFT JOIN wiki_page_revisions r ON q.kind = 'revision' AND r.id = q.entity_id
            LEFT JOIN wiki_pages p ON p.id = r.wiki_page_id
            LEFT JOIN wikis w ON w.id = p.wiki_id
            LEFT JOIN wiki_media m ON q.kind = 'media' AND m.id = q.entity_id
            LEFT JOIN wikis mw ON mw.id = m.wiki_id
            ORDER BY q.id LIMIT 100",
        )->fetchAll('assoc');
        $configured = (bool)getenv('WIKI_GIT_DIR');
        $this->set(compact('pending', 'configured'));
    }

    /** @return \Cake\Http\Response */
    public function run(): Response
    {
        $this->request->allowMethod(['post']);
        $this->requireAdmin();
        try {
            $count = (new GitSync($this->db()))->synchronize();
            $remaining = (int)$this->db()->execute(
                'SELECT COUNT(*) FROM wiki_git_queue',
            )->fetchColumn(0);
            if ($remaining) {
                $this->Flash->warning($count . ' items synchronized; ' . $remaining . ' remain queued.');
            } else {
                $this->Flash->success($count . ' items synchronized.');
            }
        } catch (GitSyncException $exception) {
            $this->Flash->error('Git synchronization failed: ' . $exception->getMessage());
        }

        return $this->redirect('/admin/sync');
    }
}
