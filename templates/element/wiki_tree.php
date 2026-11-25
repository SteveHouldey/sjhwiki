<ul class="wiki-tree">
    <?php foreach ($nodes as $node): ?>
        <li>
            <?php if ($node['page']): ?>
                <?php if ($node['children']): ?>
                    <details class="wiki-tree-branch">
                        <summary>
                            <?= $this->Html->link(
                                $node['page']['title'],
                                '/wikis/' . $wikiId . '/pages/' . $node['page']['id'],
                            ) ?>
                        </summary>
                        <?= $this->element('wiki_tree', ['nodes' => $node['children'], 'wikiId' => $wikiId]) ?>
                    </details>
                <?php else: ?>
                    <?= $this->Html->link(
                        $node['page']['title'],
                        '/wikis/' . $wikiId . '/pages/' . $node['page']['id'],
                    ) ?>
                <?php endif ?>
            <?php else: ?>
                <?php if ($node['children']): ?>
                    <details class="wiki-tree-branch" open>
                        <summary class="tree-folder"><?= h($node['name']) ?></summary>
                        <?= $this->element('wiki_tree', ['nodes' => $node['children'], 'wikiId' => $wikiId]) ?>
                    </details>
                <?php else: ?>
                    <span class="tree-folder"><?= h($node['name']) ?></span>
                <?php endif ?>
            <?php endif; ?>
        </li>
    <?php endforeach; ?>
</ul>
