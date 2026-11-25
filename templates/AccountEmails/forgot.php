<?php $this->assign('title', 'Forgot password'); ?>
<section class="panel narrow">
    <h1>Forgot password?</h1>
    <p>Enter your verified email address to request a password reset link.</p>
    <?= $this->Form->create(null) ?>
    <?= $this->Form->control('email', ['type' => 'email', 'required' => true, 'autocomplete' => 'email']) ?>
    <?= $this->Form->button('Send reset link') ?>
    <?= $this->Form->end() ?>
    <p><?= $this->Html->link('Back to sign in', '/login') ?></p>
</section>
