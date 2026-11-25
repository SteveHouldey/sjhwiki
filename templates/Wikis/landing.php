<?php $this->assign('title', 'Welcome'); ?>
<section class="landing-hero">
    <div class="landing-hero-content">
        <p class="eyebrow"><?= h(\Cake\Core\Configure::read('Landing.eyebrow', 'YOUR KNOWLEDGE, CONNECTED')) ?></p>
        <h1><?= h(\Cake\Core\Configure::read('Landing.title', 'A better home for everything you know.')) ?></h1>
        <p><?= h(\Cake\Core\Configure::read('Landing.message', 'Bring ideas, documentation and shared knowledge together in a wiki built for your team.')) ?></p>
        <div class="actions">
            <?php if (!$installed): ?>
                <?= $this->Html->link('Set up your wiki', '/setup', ['class' => 'button']) ?>
            <?php else: ?>
                <?= $this->Html->link(
                    \Cake\Core\Configure::read('Landing.primary.label', 'Sign in to your workspace'),
                    \Cake\Core\Configure::read('Landing.primary.url', '/login'),
                    ['class' => 'button'],
                ) ?>
            <?php endif; ?>
            <a class="button button-outline" href="<?= h(\Cake\Core\Configure::read('Landing.secondary.url', '#public-wikis')) ?>">
                <?= h(\Cake\Core\Configure::read('Landing.secondary.label', 'Explore public wikis')) ?>
            </a>
        </div>
    </div>
</section>
<section class="landing-features">
    <div><h2><?= h(\Cake\Core\Configure::read('Features.one.title', 'Write naturally')) ?></h2><p><?= h(\Cake\Core\Configure::read('Features.one.message', 'Create rich pages with formatting, tables and images.')) ?></p></div>
    <div><h2><?= h(\Cake\Core\Configure::read('Features.two.title', 'Share securely')) ?></h2><p><?= h(\Cake\Core\Configure::read('Features.two.message', 'Keep private knowledge protected with per-wiki permissions.')) ?></p></div>
    <div><h2><?= h(\Cake\Core\Configure::read('Features.three.title', 'Keep your history')) ?></h2><p><?= h(\Cake\Core\Configure::read('Features.three.message', 'Review earlier versions and synchronize your work with Git.')) ?></p></div>
</section>
<section id="public-wikis">
    <div class="section-heading"><div><p class="eyebrow">DISCOVER</p><h2>Public wikis</h2></div></div>
    <?php if (!$wikis): ?>
        <div class="panel"><p>There are no public wikis yet.</p></div>
    <?php else: ?>
        <div class="wiki-grid">
            <?php foreach ($wikis as $wiki): ?>
                <a class="wiki-card" href="<?= h($this->Url->build('/wikis/' . $wiki['id'])) ?>">
                    <span class="card-icon" aria-hidden="true">#</span>
                    <span class="card-content"><strong><?= h($wiki['name']) ?></strong><small><?= h($wiki['slug']) ?></small></span>
                    <span class="badge">Public</span>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
