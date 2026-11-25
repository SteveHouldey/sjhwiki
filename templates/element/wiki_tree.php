<ul class="wiki-tree">
    <?php foreach ($nodes as $node): ?>
        <li>
            <?php if ($node['page']): ?>
                <?= $this->Html->link(
                    $node['page']['title'],
                    '/wikis/' . $wikiId . '/pages/' . $node['page']['id'],
                ) ?>
            <?php else: ?>
                <span class="tree-folder"><?= h($node['name']) ?></span>
            <?php endif; ?>
            <?php if ($node['children']): ?>
                <?= $this->element('wiki_tree', ['nodes' => $node['children'], 'wikiId' => $wikiId]) ?>
            <?php endif; ?>
        </li>
    <?php endforeach; ?>
</ul>
