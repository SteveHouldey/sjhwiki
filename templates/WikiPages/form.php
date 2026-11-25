<?php
$this->assign('title', $page ? 'Edit page' : 'New page');
$posted = $this->request->is(['post', 'put']);
$formatTools = [
    ['bold', 'Bold', 'type-bold'], ['italic', 'Italic', 'type-italic'],
    ['underline', 'Underline', 'type-underline'], ['strike', 'Strikethrough', 'type-strikethrough'],
    ['subscript', 'Subscript', 'subscript'], ['superscript', 'Superscript', 'superscript'],
];
$contentTools = [
    ['clear', 'Clear formatting', 'eraser'], ['bulletList', 'Bullet list', 'list-ul'],
    ['orderedList', 'Numbered list', 'list-ol'], ['blockquote', 'Block quote', 'blockquote-left'],
    ['code', 'Inline code', 'code-slash'], ['codeBlock', 'Code block', 'file-code'],
    ['horizontalRule', 'Horizontal line', 'hr'], ['left', 'Align left', 'text-left'],
    ['center', 'Align center', 'text-center'], ['right', 'Align right', 'text-right'],
    ['link', 'Insert or edit link', 'link-45deg'], ['unlink', 'Remove link', 'link'],
    ['upload', 'Upload an image', 'cloud-upload'], ['image', 'Insert an image URL', 'image'],
];
$tableTools = [
    ['table', 'Insert table', 'table', ''],
    ['addRow', 'Add table row', 'border-middle', 'plus-circle-fill'],
    ['deleteRow', 'Delete table row', 'border-middle', 'dash-circle-fill'],
    ['addColumn', 'Add table column', 'border-center', 'plus-circle-fill'],
    ['deleteColumn', 'Delete table column', 'border-center', 'dash-circle-fill'],
    ['headerRow', 'Toggle header row', 'border-top', ''],
    ['headerColumn', 'Toggle header column', 'border-left', ''],
    ['footer', 'Toggle footer row', 'border-bottom', ''],
    ['mergeCells', 'Merge selected cells', 'arrows-angle-contract', ''],
    ['splitCell', 'Split cell', 'arrows-angle-expand', ''],
    ['deleteTable', 'Delete table', 'table', 'dash-circle-fill'],
    ['undo', 'Undo', 'arrow-counterclockwise', ''],
    ['redo', 'Redo', 'arrow-clockwise', ''],
];
?>
<?= $this->Html->css(['bootstrap-icons.min', 'highlight.min'], ['block' => true]) ?>
<section class="panel editor">
    <h1><?= $page ? 'Edit page' : 'Create a page' ?></h1>
    <p>Format your page with the toolbar. Images are stored with this wiki.</p>
    <?= $this->Form->create(null, ['id' => 'wiki-page-form']) ?>
    <?php if ($page): ?>
        <p>Path: <strong><?= h($page['path']) ?></strong></p>
        <?= $this->Form->hidden('version', ['value' => $posted ? $this->request->getData('version') : $page['version']]) ?>
    <?php else: ?>
        <?= $this->Form->control('path', ['required' => true, 'maxlength' => 190, 'placeholder' => 'guides/getting-started']) ?>
        <p class="help">Use slash-separated lowercase paths. Paths cannot be changed later.</p>
    <?php endif; ?>
    <?= $this->Form->control('title', ['required' => true, 'maxlength' => 255, 'value' => $posted ? $this->request->getData('title') : ($page['title'] ?? '')]) ?>
    <?= $this->Form->control('body', ['type' => 'textarea', 'label' => 'Page content (HTML if JavaScript is unavailable)', 'rows' => 20, 'value' => $posted ? $this->request->getData('body') : ($page['body'] ?? '')]) ?>
    <div id="visual-editor" data-upload-url="<?= h($this->Url->build('/wikis/' . $wiki['id'] . '/media')) ?>" hidden>
        <div class="editor-toolbar" role="toolbar" aria-label="Text formatting">
            <select data-command="heading" aria-label="Text style">
                <option value="0">Paragraph</option><option value="1">Heading 1</option>
                <option value="2">Heading 2</option><option value="3">Heading 3</option>
                <option value="4">Heading 4</option><option value="5">Heading 5</option><option value="6">Heading 6</option>
            </select>
            <select data-command="fontFamily" aria-label="Font">
                <option value="">Default font</option><option value="Arial">Arial</option>
                <option value="Georgia">Georgia</option><option value="Tahoma">Tahoma</option>
                <option value="Times New Roman">Times New Roman</option><option value="Verdana">Verdana</option>
            </select>
            <select data-command="fontSize" aria-label="Font size">
                <option value="">Default size</option><option value="12px">12</option><option value="14px">14</option>
                <option value="16px">16</option><option value="18px">18</option><option value="24px">24</option>
                <option value="32px">32</option><option value="48px">48</option>
            </select>
            <?php foreach (array_merge($formatTools, $contentTools) as [$command, $label, $icon]): ?>
                <button type="button" data-command="<?= h($command) ?>" title="<?= h($label) ?>" aria-label="<?= h($label) ?>">
                    <i class="bi bi-<?= h($icon) ?>" aria-hidden="true"></i>
                </button>
            <?php endforeach; ?>
            <label class="editor-color" title="Text color"><i class="bi bi-palette" aria-hidden="true"></i><input type="color" data-command="color" value="#263445" aria-label="Text color"></label>
            <label class="editor-color" title="Highlight color"><i class="bi bi-highlighter" aria-hidden="true"></i><input type="color" data-command="backgroundColor" value="#fff59d" aria-label="Highlight color"></label>
            <label class="editor-code-language">Code language
                <select data-command="codeLanguage" title="Code language" disabled>
                    <option value="plaintext">Plain text</option>
                </select>
            </label>
            <input type="file" id="editor-image" accept="image/jpeg,image/png,image/gif,image/webp" hidden>
            <?php foreach ($tableTools as [$command, $label, $icon, $modifier]): ?>
                <button type="button" data-command="<?= h($command) ?>" title="<?= h($label) ?>" aria-label="<?= h($label) ?>">
                    <i class="bi bi-<?= h($icon) ?>" aria-hidden="true"></i>
                    <?php if ($modifier): ?><i class="bi bi-<?= h($modifier) ?> editor-icon-modifier" aria-hidden="true"></i><?php endif; ?>
                </button>
            <?php endforeach; ?>
        </div>
        <div id="editor-link-panel" class="editor-link-panel" hidden>
            <label for="editor-link-url">Link URL</label>
            <input id="editor-link-url" type="text" placeholder="https://example.com">
            <button type="button" data-panel-action="apply-link">Apply link</button>
            <button type="button" data-panel-action="cancel-link" class="button-outline">Cancel</button>
        </div>
        <div id="editor-image-panel" class="editor-link-panel" hidden>
            <label for="editor-image-url">Image URL</label>
            <input id="editor-image-url" type="text" placeholder="https://example.com/image.png">
            <button type="button" data-panel-action="apply-image">Insert image</button>
            <button type="button" data-panel-action="cancel-image" class="button-outline">Cancel</button>
        </div>
        <div id="editor-table-panel" class="editor-link-panel editor-table-panel" hidden>
            <label for="editor-table-rows">Rows</label>
            <input id="editor-table-rows" type="number" min="1" max="20" value="3">
            <label for="editor-table-columns">Columns</label>
            <input id="editor-table-columns" type="number" min="1" max="20" value="3">
            <label><input id="editor-table-header" type="checkbox" checked> Header row</label>
            <button type="button" data-panel-action="insert-table">Insert table</button>
            <button type="button" data-panel-action="cancel-table" class="button-outline">Cancel</button>
        </div>
        <div id="editor-content"></div>
        <p id="editor-status" class="help" role="status" aria-live="polite"></p>
    </div>
    <?= $this->Form->button('Save page') ?>
    <?= $this->Form->end() ?>
</section>
<?= $this->Html->script('wiki-editor', ['block' => true, 'defer' => true]) ?>
