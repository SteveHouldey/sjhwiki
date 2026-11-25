<?php $this->assign('title', 'Page history'); ?>
<?= $this->Html->css('highlight.min', ['block' => true]) ?>
<?= $this->Html->script('wiki-highlight', ['block' => true, 'defer' => true]) ?>
<div class="breadcrumbs"><?= $this->Html->link($wiki['name'], '/wikis/' . $wiki['id']) ?> / <?= h($page['path']) ?></div>
<section class="panel">
    <h1>History: <?= h($page['title']) ?></h1>
    <?php if ($page['deleted']): ?><p>This page is deleted. Restore a revision to make it visible again.</p><?php endif; ?>
    <?php foreach ($revisions as $revision): ?>
        <div class="revision">
            <h2><?= h(ucfirst($revision['operation'])) ?> &middot; <?= h($revision['created']) ?> UTC</h2>
            <p>By <?= h($revision['username'] ?? 'System or deleted user') ?> &middot; <?= h($revision['title']) ?></p>
            <details><summary>Preview revision</summary><div class="markdown-body"><?= $revision['rendered'] ?></div></details>
            <details><summary>View <?= h($revision['content_format'] === 'markdown' ? 'Markdown' : 'HTML') ?> source</summary><pre><?= h($revision['body']) ?></pre></details>
            <?php if ($canRestore): ?>
                <?= $this->Form->create(null, ['url' => '/wikis/' . $wiki['id'] . '/pages/' . $page['id'] . '/history/' . $revision['id'] . '/restore']) ?>
                <?= $this->Form->hidden('version', ['value' => $page['version']]) ?>
                <?= $this->Form->button('Restore this revision', ['class' => 'button button-outline']) ?>
                <?= $this->Form->end() ?>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</section>
