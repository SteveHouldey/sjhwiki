<?php $this->assign('title', 'Wiki settings'); ?>
<section class="panel narrow">
    <h1>Wiki settings</h1>
    <p>The Git repository includes this wiki even when it is private.</p>
    <?= $this->Form->create(null) ?>
    <?= $this->Form->control('name', ['required' => true, 'value' => $wiki['name']]) ?>
    <?= $this->Form->control('is_public', ['type' => 'checkbox', 'checked' => (bool)$wiki['is_public'], 'label' => 'Allow public reading']) ?>
    <?= $this->Form->button('Save settings') ?>
    <?= $this->Form->end() ?>
</section>
