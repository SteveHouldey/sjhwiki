<?php $this->assign('title', 'Sign in'); ?>
<section class="panel narrow">
    <?php if (in_array($this->request->getQuery('setup'), ['complete', 'remove-token'], true)): ?>
        <p>Setup complete. Sign in, then request an email verification link for your administrator address.</p>
        <?php if ($this->request->getQuery('setup') === 'remove-token'): ?>
            <p>Remove config/setup-token.txt using your hosting file manager.</p>
        <?php endif; ?>
    <?php endif; ?>
    <h1>Sign in</h1>
    <?= $this->Form->create(null) ?>
    <?= $this->Form->control('username', ['required' => true, 'autocomplete' => 'username']) ?>
    <?= $this->Form->control('password', ['type' => 'password', 'required' => true, 'autocomplete' => 'current-password']) ?>
    <?= $this->Form->button('Sign in') ?>
    <?= $this->Form->end() ?>
    <p><?= $this->Html->link('Forgot password?', '/forgot-password') ?> ·
        <?= $this->Html->link('Resend verification', '/resend-verification') ?></p>
    <p><?= $this->Html->link('Create an account', '/register') ?></p>
</section>
