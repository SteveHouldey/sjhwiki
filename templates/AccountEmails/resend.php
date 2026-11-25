<?php $this->assign('title', 'Resend verification'); ?>
<section class="panel narrow">
    <h1>Resend verification</h1>
    <p>Enter your email address to request another verification link.</p>
    <?= $this->Form->create(null) ?>
    <?= $this->Form->control('email', ['type' => 'email', 'required' => true, 'autocomplete' => 'email']) ?>
    <?= $this->Form->button('Send verification link') ?>
    <?= $this->Form->end() ?>
    <p><?= $this->Html->link('Back to sign in', '/login') ?></p>
</section>
