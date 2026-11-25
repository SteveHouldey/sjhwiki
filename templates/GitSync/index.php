<?php $this->assign('title', 'Git synchronization'); ?>
<section class="panel">
    <h1>Git synchronization</h1>
    <p>All wikis, including private wikis, are pushed to the shared repository.</p>
    <?php if (!$configured): ?>
        <p>Set WIKI_GIT_DIR to a dedicated bare Git repository outside the web root.</p>
    <?php endif; ?>
    <?= $this->Form->create(null, ['url' => '/admin/sync/run']) ?>
    <?= $this->Form->button('Retry queued items') ?>
    <?= $this->Form->end() ?>
    <h2>Pending pages and images</h2>
    <?php if (!$pending): ?>
        <p>There are no queued items.</p>
    <?php else: ?>
        <table>
            <thead><tr><th>Wiki / page</th><th>Operation</th><th>Created</th><th>Last error</th></tr></thead>
            <tbody>
            <?php foreach ($pending as $item): ?>
                <tr>
                    <td><?= h($item['wiki_name']) ?> / <?= h($item['path']) ?></td>
                    <td><?= h($item['operation']) ?></td>
                    <td><?= h($item['created']) ?> UTC</td>
                    <td><?= h($item['git_error'] ?? 'Waiting to synchronize') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>
