<?php $this->assign('title', 'Wikis'); ?>
<section class="page-heading">
    <div><p class="eyebrow">KNOWLEDGE BASE</p><h1>Your wikis</h1><p>Explore and share knowledge in one place.</p></div>
    <?php if ($currentUser && $currentUser['is_admin']): ?>
        <?= $this->Html->link('Create wiki', '/wikis/add', ['class' => 'button']) ?>
    <?php endif; ?>
</section>
<?php if (!$installed): ?>
    <section class="panel">
        <h2>Get started</h2>
        <p>Complete the first-run setup to create an administrator.</p>
        <?= $this->Html->link('Open setup', '/setup', ['class' => 'button']) ?>
    </section>
<?php elseif (!$wikis): ?>
    <section class="panel"><p>No wikis are available to you yet.</p></section>
<?php else: ?>
    <div class="wiki-grid">
        <?php foreach ($wikis as $wiki): ?>
            <a class="wiki-card" href="<?= h($this->Url->build('/wikis/' . $wiki['id'])) ?>">
                <span class="card-icon" aria-hidden="true">#</span>
                <span class="card-content"><strong><?= h($wiki['name']) ?></strong><small><?= h($wiki['slug']) ?></small></span>
                <span class="badge"><?= $wiki['is_public'] ? 'Public' : 'Private' ?></span>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
