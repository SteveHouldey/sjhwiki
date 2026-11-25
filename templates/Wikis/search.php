<?php $this->assign('title', 'Search'); ?>
<section class="panel">
    <h1>Search</h1>
    <?= $this->Form->create(null, ['type' => 'get', 'url' => '/search', 'class' => 'search-form']) ?>
    <?= $this->Form->control('q', ['label' => 'Search pages you can access', 'value' => $query, 'maxlength' => 100]) ?>
    <?= $this->Form->button('Search') ?>
    <?= $this->Form->end() ?>
    <?php if ($query !== ''): ?>
        <h2>Results</h2>
        <?php if (!$results): ?>
            <p>No matching pages.</p>
        <?php else: ?>
            <ul class="page-list">
                <?php foreach ($results as $result): ?>
                    <li>
                        <a href="<?= h($this->Url->build('/wikis/' . $result['wiki_id'] . '/pages/' . $result['id'])) ?>">
                            <strong><?= h($result['title']) ?></strong>
                            <small><?= h($result['wiki_name']) ?> / <?= h($result['path']) ?></small>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    <?php endif; ?>
</section>
