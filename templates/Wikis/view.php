<?php $this->assign('title', $wiki['name']); ?>
<section class="page-heading">
    <div>
        <p class="eyebrow"><?= $wiki['is_public'] ? 'PUBLIC WIKI' : 'PRIVATE WIKI' ?></p>
        <h1><?= h($wiki['name']) ?></h1>
        <p>Browse pages by their hierarchical paths.</p>
    </div>
    <div class="actions">
        <?php if ($permissions['manage_roles']): ?>
            <?= $this->Html->link('Roles', '/wikis/' . $wiki['id'] . '/roles', ['class' => 'button button-outline']) ?>
        <?php endif; ?>
        <?php if ($permissions['manage_wiki']): ?>
            <?= $this->Html->link('Settings', '/wikis/' . $wiki['id'] . '/settings', ['class' => 'button button-outline']) ?>
        <?php endif; ?>
        <?php if ($permissions['create']): ?>
            <?= $this->Html->link('New page', '/wikis/' . $wiki['id'] . '/pages/add', ['class' => 'button']) ?>
        <?php endif; ?>
    </div>
</section>
<section class="panel">
    <h2>Pages</h2>
    <?php if (!$tree): ?>
        <p>No pages have been created yet.</p>
    <?php else: ?>
        <?= $this->element('wiki_tree', ['nodes' => $tree, 'wikiId' => $wiki['id']]) ?>
    <?php endif; ?>
</section>
<?php if ($deletedPages): ?>
    <section class="panel">
        <h2>Deleted pages</h2>
        <ul class="page-list">
            <?php foreach ($deletedPages as $page): ?>
                <li>
                    <?= $this->Html->link(
                        $page['title'] . ' (' . $page['path'] . ')',
                        '/wikis/' . $wiki['id'] . '/pages/' . $page['id'] . '/history',
                    ) ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>
