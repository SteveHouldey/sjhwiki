<?php $this->assign('title', 'Create an account'); ?>
<section class="panel narrow">
    <h1>Create an account</h1>
    <p>Verify your email before signing in. Private wiki access requires an assigned role.</p>
    <?= $this->Form->create(null) ?>
    <?= $this->Form->control('username', ['required' => true, 'autocomplete' => 'username']) ?>
    <?= $this->Form->control('email', ['type' => 'email', 'required' => true, 'autocomplete' => 'email']) ?>
    <?= $this->Form->control('password', ['type' => 'password', 'required' => true, 'autocomplete' => 'new-password']) ?>
    <?= $this->Form->control('password_confirm', ['label' => 'Confirm password', 'type' => 'password', 'required' => true, 'autocomplete' => 'new-password']) ?>
    <?= $this->Form->button('Create account') ?>
    <?= $this->Form->end() ?>
    <p><?= $this->Html->link('Sign in', '/login') ?></p>
</section>
