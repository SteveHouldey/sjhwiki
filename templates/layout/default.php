<?php
/**
 * @var \App\View\AppView $this
 * @var array|null $currentUser
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?= $this->Html->charset() ?>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($this->fetch('title')) ?> | <?= h(\Cake\Core\Configure::read('App.siteName', 'SJHWiki')) ?></title>
    <?= $this->Html->meta('icon') ?>
    <?= $this->Html->css(['bootstrap.min', 'wiki']) ?>
    <?= $this->fetch('meta') ?>
    <?= $this->fetch('css') ?>
    <?= $this->fetch('script') ?>
</head>
<body>
    <header class="app-header">
        <a class="brand" href="<?= h($this->Url->build('/')) ?>"><span class="brand-mark">W</span><span><?= h(\Cake\Core\Configure::read('App.siteName', 'SJHWiki')) ?></span></a>
        <form class="header-search" method="get" action="<?= h($this->Url->build('/search')) ?>">
            <label class="sr-only" for="header-query">Search wikis</label>
            <span aria-hidden="true">&#9906;</span>
            <input id="header-query" name="q" type="search" placeholder="Search knowledge..." maxlength="100">
        </form>
        <div class="header-account">
            <?= $this->Html->link('About', '/about', ['class' => 'header-button']) ?>
            <?php if ($currentUser): ?>
                <span class="account-name"><?= h($currentUser['username']) ?></span>
                <?= $this->Form->create(null, ['url' => '/logout', 'class' => 'inline-form']) ?>
                <?= $this->Form->button('Sign out', ['class' => 'header-button']) ?>
                <?= $this->Form->end() ?>
            <?php else: ?>
                <?= $this->Html->link('Sign in', '/login', ['class' => 'header-button']) ?>
            <?php endif; ?>
        </div>
    </header>
    <div class="app-shell">
        <?php if ($currentUser && empty($hideSidebar)): ?>
        <nav class="sidebar" aria-label="Main navigation">
            <p class="nav-label">WORKSPACE</p>
            <?= $this->Html->link('<span aria-hidden="true">#</span> All wikis', '/', ['escape' => false]) ?>
            <?= $this->Html->link('<span aria-hidden="true">&#9906;</span> Search', '/search', ['escape' => false]) ?>
            <?php if ($currentUser && $currentUser['is_admin']): ?>
                <p class="nav-label">ADMINISTRATION</p>
                <?= $this->Html->link('<span aria-hidden="true">+</span> Create wiki', '/wikis/add', ['escape' => false]) ?>
                <?= $this->Html->link('<span aria-hidden="true">@</span> Users', '/admin/users', ['escape' => false]) ?>
                <?= $this->Html->link('<span aria-hidden="true">&lt;&gt;</span> Git sync', '/admin/sync', ['escape' => false]) ?>
                <?= $this->Html->link('<span aria-hidden="true">&#9881;</span> Admin center', '/admin/settings', ['escape' => false]) ?>
            <?php endif; ?>
        </nav>
        <?php endif; ?>
        <main class="main-content<?= !empty($fullWidthLanding) ? ' landing-main' : '' ?><?= !empty($accountScreen) ? ' account-main' : '' ?>">
            <div class="content-inner">
                <?= $this->Flash->render() ?>
                <?= $this->fetch('content') ?>
            </div>
        </main>
    </div>
    <?= $this->element('site_footer') ?>
</body>
</html>
