<?php $this->assign('title', 'Roles'); ?>
<section class="panel">
    <div class="section-heading">
        <div><h1>Roles for <?= h($wiki['name']) ?></h1><p>Roles apply only to this wiki.</p></div>
        <?= $this->Html->link('Add role', '/wikis/' . $wiki['id'] . '/roles/add', ['class' => 'button']) ?>
    </div>
    <?php if (!$roles): ?>
        <p>No roles have been created.</p>
    <?php else: ?>
        <table>
            <thead><tr><th>Role</th><th>Members</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($roles as $role): ?>
                <tr>
                    <td><?= h($role['name']) ?></td><td><?= h($role['member_count']) ?></td>
                    <td><?= $this->Html->link('Edit', '/wikis/' . $wiki['id'] . '/roles/' . $role['id']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>
