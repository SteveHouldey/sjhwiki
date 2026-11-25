<?php
declare(strict_types=1);

use Cake\Routing\Route\DashedRoute;
use Cake\Routing\RouteBuilder;

return function (RouteBuilder $routes): void {
    $routes->setRouteClass(DashedRoute::class);
    $routes->scope('/', function (RouteBuilder $builder): void {
        $builder->connect('/', ['controller' => 'Wikis', 'action' => 'index']);
        $builder->connect('/about', ['controller' => 'Wikis', 'action' => 'about']);
        $builder->connect('/setup', ['controller' => 'Accounts', 'action' => 'setup']);
        $builder->connect('/login', ['controller' => 'Accounts', 'action' => 'login']);
        $builder->connect('/logout', ['controller' => 'Accounts', 'action' => 'logout']);
        $builder->connect('/register', ['controller' => 'AccountEmails', 'action' => 'register']);
        $builder->connect('/resend-verification', ['controller' => 'AccountEmails', 'action' => 'resend']);
        $builder->connect('/forgot-password', ['controller' => 'AccountEmails', 'action' => 'forgot']);
        $builder->connect('/verify-email/{token}', ['controller' => 'AccountEmails', 'action' => 'verify'])
            ->setPatterns(['token' => '[a-f0-9]{64}'])->setPass(['token']);
        $builder->connect('/reset-password/{token}', ['controller' => 'AccountEmails', 'action' => 'reset'])
            ->setPatterns(['token' => '[a-f0-9]{64}'])->setPass(['token']);
        $builder->connect('/admin/users', ['controller' => 'Users', 'action' => 'index']);
        $builder->connect('/admin/users/add', ['controller' => 'Users', 'action' => 'add']);
        $builder->connect('/admin/users/{id}', ['controller' => 'Users', 'action' => 'edit'])
            ->setPatterns(['id' => '\d+'])->setPass(['id']);
        $builder->connect('/admin/settings', ['controller' => 'AdminSettings', 'action' => 'index']);
        $builder->connect('/wikis/add', ['controller' => 'Wikis', 'action' => 'add']);
        $builder->connect('/wikis/{wikiId}', ['controller' => 'Wikis', 'action' => 'view'])
            ->setPatterns(['wikiId' => '\d+'])->setPass(['wikiId']);
        $builder->connect('/wikis/{wikiId}/settings', ['controller' => 'Wikis', 'action' => 'settings'])
            ->setPatterns(['wikiId' => '\d+'])->setPass(['wikiId']);
        $builder->connect('/wikis/{wikiId}/roles', ['controller' => 'WikiRoles', 'action' => 'index'])
            ->setPatterns(['wikiId' => '\d+'])->setPass(['wikiId']);
        $builder->connect('/wikis/{wikiId}/roles/add', ['controller' => 'WikiRoles', 'action' => 'add'])
            ->setPatterns(['wikiId' => '\d+'])->setPass(['wikiId']);
        $builder->connect('/wikis/{wikiId}/roles/{roleId}', ['controller' => 'WikiRoles', 'action' => 'edit'])
            ->setPatterns(['wikiId' => '\d+', 'roleId' => '\d+'])->setPass(['wikiId', 'roleId']);
        $builder->connect('/wikis/{wikiId}/roles/{roleId}/delete', [
            'controller' => 'WikiRoles', 'action' => 'delete',
        ])->setPatterns(['wikiId' => '\d+', 'roleId' => '\d+'])->setPass(['wikiId', 'roleId']);
        $builder->connect('/wikis/{wikiId}/pages/add', ['controller' => 'WikiPages', 'action' => 'add'])
            ->setPatterns(['wikiId' => '\d+'])->setPass(['wikiId']);
        $builder->connect('/wikis/{wikiId}/media', ['controller' => 'WikiMedia', 'action' => 'upload'])
            ->setPatterns(['wikiId' => '\d+'])->setPass(['wikiId']);
        $builder->connect('/wikis/{wikiId}/media/{mediaId}', ['controller' => 'WikiMedia', 'action' => 'view'])
            ->setPatterns(['wikiId' => '\d+', 'mediaId' => '\d+'])->setPass(['wikiId', 'mediaId']);
        foreach (['view', 'edit', 'delete', 'history'] as $action) {
            $suffix = $action === 'view' ? '' : '/' . $action;
            $builder->connect('/wikis/{wikiId}/pages/{pageId}' . $suffix, [
                'controller' => 'WikiPages', 'action' => $action,
            ])->setPatterns(['wikiId' => '\d+', 'pageId' => '\d+'])->setPass(['wikiId', 'pageId']);
        }
        $builder->connect('/wikis/{wikiId}/pages/{pageId}/history/{revisionId}/restore', [
            'controller' => 'WikiPages', 'action' => 'restore',
        ])->setPatterns(['wikiId' => '\d+', 'pageId' => '\d+', 'revisionId' => '\d+'])
            ->setPass(['wikiId', 'pageId', 'revisionId']);
        $builder->connect('/search', ['controller' => 'Wikis', 'action' => 'search']);
        $builder->connect('/admin/sync', ['controller' => 'GitSync', 'action' => 'index']);
        $builder->connect('/admin/sync/run', ['controller' => 'GitSync', 'action' => 'run']);
    });
};
