<?php $this->assign('title', 'Add user'); ?>
<section class="panel narrow">
    <h1>Add user</h1>
    <?= $this->Form->create(null) ?>
    <?= $this->Form->control('username', ['required' => true, 'autocomplete' => 'off']) ?>
    <?= $this->Form->control('email', ['type' => 'email', 'label' => 'Email (optional)', 'autocomplete' => 'email']) ?>
    <?= $this->Form->control('password', ['type' => 'password', 'required' => true, 'autocomplete' => 'new-password']) ?>
    <?= $this->Form->control('password_confirm', ['label' => 'Confirm password', 'type' => 'password', 'required' => true]) ?>
    <?= $this->Form->control('is_admin', ['type' => 'checkbox', 'label' => 'Application administrator']) ?>
    <?= $this->Form->button('Create user') ?>
    <?= $this->Form->end() ?>
</section>
