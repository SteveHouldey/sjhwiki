<?php
declare(strict_types=1);

/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link      https://cakephp.org CakePHP(tm) Project
 * @since     0.2.9
 * @license   https://opensource.org/licenses/mit-license.php MIT License
 */
namespace App\Controller;

use App\Service\AccountEmail;
use App\Service\ApplicationSettings;
use App\Service\WikiAccess;
use Cake\Controller\Controller;
use Cake\Database\Connection;
use Cake\Datasource\ConnectionManager;
use Cake\Event\EventInterface;
use Cake\Http\Exception\ForbiddenException;
use Cake\Log\Log;
use Throwable;

/**
 * Application Controller
 *
 * Add your application-wide methods in the class below, your controllers
 * will inherit them.
 *
 * @link https://book.cakephp.org/5/en/controllers.html#the-app-controller
 */
class AppController extends Controller
{
    /**
     * Initialization hook method.
     *
     * Use this method to add common initialization code like loading components.
     *
     * e.g. `$this->loadComponent('FormProtection');`
     *
     * @return void
     */
    public function initialize(): void
    {
        parent::initialize();

        $this->loadComponent('Flash');
        $this->loadComponent('Authentication.Authentication', ['requireIdentity' => false]);
        (new ApplicationSettings($this->db()))->applyRuntime();

        /*
         * Enable the following component for recommended CakePHP form protection settings.
         * see https://book.cakephp.org/5/en/controllers/components/form-protection.html
         */
        //$this->loadComponent('FormProtection');
    }

    /**
     * Expose the current identity to the layout.
     *
     * @param \Cake\Event\EventInterface $event Event.
     * @return void
     */
    public function beforeRender(EventInterface $event): void
    {
        parent::beforeRender($event);
        $this->set('currentUser', $this->currentUser());
        $controller = $this->request->getParam('controller');
        $action = $this->request->getParam('action');
        $this->set(
            'accountScreen',
            $controller === 'AccountEmails' || ($controller === 'Accounts' && $action === 'login'),
        );
    }

    /** @return \Cake\Database\Connection */
    protected function db(): Connection
    {
        return ConnectionManager::get('default');
    }

    /** @return array|null */
    protected function currentUser(): ?array
    {
        $identity = $this->request->getAttribute('identity');
        if ($identity === null) {
            return null;
        }

        return [
            'id' => (int)$identity['id'],
            'username' => (string)$identity['username'],
            'is_admin' => (bool)$identity['is_admin'],
        ];
    }

    /** @return array */
    protected function requireAdmin(): array
    {
        $user = $this->currentUser();
        if (!$user || !$user['is_admin']) {
            throw new ForbiddenException('Administrator access is required.');
        }

        return $user;
    }

    /** @return \App\Service\WikiAccess */
    protected function wikiAccess(): WikiAccess
    {
        return new WikiAccess($this->db(), $this->currentUser());
    }

    /** @return bool */
    protected function sendAccountEmail(int $userId, string $purpose): bool
    {
        try {
            (new AccountEmail($this->db()))->send($userId, $purpose);

            return true;
        } catch (Throwable $exception) {
            Log::error('Account email delivery failed (' . $exception::class . '). Check mail and base URL settings.');

            return false;
        }
    }
}
