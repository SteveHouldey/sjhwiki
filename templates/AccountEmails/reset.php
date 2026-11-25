<?php $this->assign('title', 'Reset password'); ?>
<section class="panel narrow">
    <h1>Reset password</h1>
    <?php if ($valid): ?>
        <?= $this->Form->create(null) ?>
        <?= $this->Form->control('password', ['label' => 'New password', 'type' => 'password', 'required' => true, 'autocomplete' => 'new-password']) ?>
        <?= $this->Form->control('password_confirm', ['label' => 'Confirm password', 'type' => 'password', 'required' => true, 'autocomplete' => 'new-password']) ?>
        <?= $this->Form->button('Change password') ?>
        <?= $this->Form->end() ?>
    <?php else: ?>
        <p>This link is invalid, expired or has already been used.</p>
        <p><?= $this->Html->link('Request another reset link', '/forgot-password') ?></p>
    <?php endif; ?>
</section>
