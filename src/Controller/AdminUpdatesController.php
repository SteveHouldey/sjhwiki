<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\ApplicationUpdater;
use Cake\Http\Response;
use RuntimeException;

class AdminUpdatesController extends AppController
{
    /** @return void */
    public function index(): void
    {
        $this->requireAdmin();
        $updater = new ApplicationUpdater();
        $check = null;
        $error = null;
        $changelog = [];
        try {
            $check = $updater->check();
            if ($check === null) {
                $error = 'Could not reach GitHub release information.';
            }
            $changelog = $updater->changelog();
        } catch (RuntimeException $exception) {
            $error = $exception->getMessage();
        }
        $this->set(compact('check', 'error', 'changelog'));
    }

    /** @return \Cake\Http\Response */
    public function apply(): Response
    {
        $this->request->allowMethod(['post']);
        $this->requireAdmin();
        $updater = new ApplicationUpdater();
        try {
            $release = $updater->latest();
            if (!$release || $updater->compareVersions((string)$release['version'], ApplicationUpdater::VERSION) <= 0) {
                throw new RuntimeException('No newer release is available.');
            }
            $result = $updater->apply($release);
            $this->Flash->success(
                'Update package installed. Run database migrations once on the next deployment/CLI request.',
            );
            $this->set('updateResult', $result);
        } catch (RuntimeException $exception) {
            $this->Flash->error('Update failed: ' . $exception->getMessage());
        }

        return $this->redirect('/admin/updates');
    }
}
