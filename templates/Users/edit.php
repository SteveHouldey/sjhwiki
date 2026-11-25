<?php $this->assign('title', 'Edit user'); ?>
<section class="panel narrow">
    <h1>Edit <?= h($user['username']) ?></h1>
    <?= $this->Form->create(null) ?>
    <?= $this->Form->control('email', ['type' => 'email', 'label' => 'Email', 'value' => $user['email'], 'autocomplete' => 'email']) ?>
    <p class="help"><?= $user['email'] ? ($user['email_verified'] ? 'Email verified.' : 'Email verification pending.') : 'A verified email is required for password recovery.' ?></p>
    <?= $this->Form->control('active', ['type' => 'checkbox', 'checked' => (bool)$user['active']]) ?>
    <?= $this->Form->control('is_admin', ['type' => 'checkbox', 'label' => 'Application administrator', 'checked' => (bool)$user['is_admin']]) ?>
    <?= $this->Form->control('password', ['label' => 'New password (leave blank to keep current)', 'type' => 'password', 'autocomplete' => 'new-password']) ?>
    <?= $this->Form->control('password_confirm', ['label' => 'Confirm new password', 'type' => 'password']) ?>
    <?= $this->Form->button('Save') ?>
    <?= $this->Form->end() ?>
</section>
