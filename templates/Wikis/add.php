<?php $this->assign('title', 'Create wiki'); ?>
<section class="panel narrow">
    <h1>Create a wiki</h1>
    <?= $this->Form->create(null) ?>
    <?= $this->Form->control('name', ['required' => true, 'maxlength' => 150]) ?>
    <?= $this->Form->control('slug', ['required' => true]) ?>
    <p class="help">Use lowercase letters, numbers and hyphens. The slug cannot be changed later.</p>
    <?= $this->Form->control('is_public', ['type' => 'checkbox', 'label' => 'Allow public reading']) ?>
    <p>Private wiki content is also stored in the shared Git repository.</p>
    <?= $this->Form->button('Create wiki') ?>
    <?= $this->Form->end() ?>
</section>
