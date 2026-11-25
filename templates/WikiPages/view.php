<?php $this->assign('title', $page['title']); ?>
<?= $this->Html->css('highlight.min', ['block' => true]) ?>
<?= $this->Html->script('wiki-highlight', ['block' => true, 'defer' => true]) ?>
<div class="breadcrumbs">
    <?= $this->Html->link('Wikis', '/') ?> /
    <?= $this->Html->link($wiki['name'], '/wikis/' . $wiki['id']) ?> /
    <?= h($page['path']) ?>
</div>
<section class="page-heading">
    <div><p class="eyebrow"><?= h($wiki['name']) ?></p><h1><?= h($page['title']) ?></h1></div>
    <div class="actions">
        <?php if ($canHistory): ?>
            <?= $this->Html->link('History', '/wikis/' . $wiki['id'] . '/pages/' . $page['id'] . '/history', ['class' => 'button button-outline']) ?>
        <?php endif; ?>
        <?php if ($canEdit): ?>
            <?= $this->Html->link('Edit', '/wikis/' . $wiki['id'] . '/pages/' . $page['id'] . '/edit', ['class' => 'button']) ?>
        <?php endif; ?>
    </div>
</section>
<div class="wiki-layout">
    <aside class="panel page-navigation" aria-label="Wiki page navigation">
        <h2>Pages</h2>
        <?= $this->element('wiki_tree', ['nodes' => $tree, 'wikiId' => $wiki['id']]) ?>
    </aside>
    <article class="panel markdown-body"><?= $rendered ?></article>
</div>
<?php if ($canDelete): ?>
    <?= $this->Form->create(null, ['url' => '/wikis/' . $wiki['id'] . '/pages/' . $page['id'] . '/delete']) ?>
    <?= $this->Form->hidden('version', ['value' => $page['version']]) ?>
    <?= $this->Form->button('Delete page', ['class' => 'danger-button', 'onclick' => "return confirm('Delete this page?')"]) ?>
    <?= $this->Form->end() ?>
<?php endif; ?>
