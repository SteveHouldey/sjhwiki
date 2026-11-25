<?php $this->assign('title', 'Verify email'); ?>
<section class="panel narrow">
    <h1>Verify email</h1>
    <?php if ($valid): ?>
        <p>Confirm this address to complete email verification.</p>
        <?= $this->Form->create(null) ?>
        <?= $this->Form->button('Verify email') ?>
        <?= $this->Form->end() ?>
    <?php else: ?>
        <p>This link is invalid, expired or has already been used.</p>
        <p><?= $this->Html->link('Request another verification link', '/resend-verification') ?></p>
    <?php endif; ?>
</section>
