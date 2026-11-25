<?php $this->assign('title', 'Wiki role'); ?>
<section class="panel narrow">
    <h1><?= isset($role['id']) ? 'Edit role' : 'Add role' ?></h1>
    <p>Permissions and members apply only to <?= h($wiki['name']) ?>.</p>
    <?= $this->Form->create(null) ?>
    <?= $this->Form->control('name', ['required' => true, 'value' => $role['name'], 'maxlength' => 100]) ?>
    <?= $this->Form->control('permissions', ['type' => 'select', 'multiple' => 'checkbox', 'options' => $permissionOptions, 'value' => $permissions]) ?>
    <p class="help">Roles with permissions must include read access.</p>
    <?= $this->Form->control('members', ['type' => 'select', 'multiple' => 'checkbox', 'options' => $users, 'value' => $members]) ?>
    <?= $this->Form->button('Save role') ?>
    <?= $this->Form->end() ?>
    <?php if (isset($role['id'])): ?>
        <?= $this->Form->postLink(
            'Delete role',
            '/wikis/' . $wiki['id'] . '/roles/' . $role['id'] . '/delete',
            ['confirm' => 'Delete this role and its assignments?', 'class' => 'danger-link'],
        ) ?>
    <?php endif; ?>
</section>
