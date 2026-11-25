<?php $this->assign('title', 'Users'); ?>
<section class="panel">
    <div class="section-heading">
        <div><h1>Users</h1><p>Manage application accounts.</p></div>
        <?= $this->Html->link('Add user', '/admin/users/add', ['class' => 'button']) ?>
    </div>
    <table>
        <thead><tr><th>Username</th><th>Email</th><th>Application administrator</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($users as $user): ?>
            <tr>
                <td><?= h($user['username']) ?></td>
                <td><?= h($user['email'] ?? 'Not set') ?><?= $user['email'] && !$user['email_verified'] ? ' (unverified)' : '' ?></td>
                <td><?= $user['is_admin'] ? 'Yes' : 'No' ?></td>
                <td><?= $user['active'] ? 'Active' : 'Disabled' ?></td>
                <td><?= $this->Html->link('Edit', '/admin/users/' . $user['id']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>
