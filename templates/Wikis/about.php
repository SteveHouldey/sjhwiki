<?php $this->assign('title', 'About'); ?>
<section class="landing-hero">
    <div>
        <p class="eyebrow"><?= h(\Cake\Core\Configure::read('About.eyebrow', 'ABOUT THIS WIKI')) ?></p>
        <h1><?= h(\Cake\Core\Configure::read('About.title', 'Knowledge is better when it is shared.')) ?></h1>
        <p><?= h(\Cake\Core\Configure::read('About.message', 'Bring documentation, ideas and practical guides together in one accessible place.')) ?></p>
        <div class="actions">
            <?= $this->Html->link('Search wikis', '/search', ['class' => 'button']) ?>
            <?php if ($currentUser): ?>
                <?= $this->Html->link('Go to dashboard', '/', ['class' => 'button button-outline']) ?>
            <?php else: ?>
                <?= $this->Html->link('Sign in', '/login', ['class' => 'button button-outline']) ?>
            <?php endif; ?>
        </div>
    </div>
    <div class="landing-illustration" aria-hidden="true">
        <div class="landing-document">
            <span class="landing-document-icon">W</span>
            <div class="landing-line landing-line-title"></div>
            <div class="landing-line"></div>
            <div class="landing-line"></div>
            <div class="landing-block"></div>
            <div class="landing-line landing-line-short"></div>
        </div>
    </div>
</section>
<section class="panel about-custom-content markdown-body">
    <?= \Cake\Core\Configure::read('About.content', '<p>Write about this wiki, its purpose, policies and community.</p>') ?>
</section>
