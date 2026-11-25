<?php $this->assign('title', 'Admin center'); ?>
<section class="page-heading">
    <div><p class="eyebrow">GLOBAL ADMINISTRATION</p><h1>Admin center</h1><p>Configure the site, account access, email, and public-facing details.</p></div>
</section>

<ul class="nav nav-tabs admin-settings-tabs" role="tablist">
    <li class="nav-item" role="presentation"><button class="nav-link active" type="button" role="tab" data-admin-tab="site">Site</button></li>
    <li class="nav-item" role="presentation"><button class="nav-link" type="button" role="tab" data-admin-tab="landing">Landing</button></li>
    <li class="nav-item" role="presentation"><button class="nav-link" type="button" role="tab" data-admin-tab="about">About</button></li>
    <li class="nav-item" role="presentation"><button class="nav-link" type="button" role="tab" data-admin-tab="accounts">Accounts</button></li>
    <li class="nav-item" role="presentation"><button class="nav-link" type="button" role="tab" data-admin-tab="email">Email</button></li>
</ul>

<?= $this->Form->create(null, ['id' => 'admin-settings-form']) ?>
<div class="tab-content">
    <div class="tab-pane active" data-admin-pane="site" role="tabpanel">
        <section class="panel narrow">
            <h2>Site</h2>
            <?= $this->Form->control('site_name', ['label' => 'Site name', 'value' => $settings['site_name'] ?? 'SJHWiki', 'maxlength' => 100, 'required' => true]) ?>
            <?= $this->Form->control('site_tagline', ['label' => 'Site tagline', 'value' => $settings['site_tagline'] ?? '', 'maxlength' => 180]) ?>
            <?= $this->Form->control('footer_text', ['label' => 'Footer text', 'value' => $settings['footer_text'] ?? '', 'maxlength' => 180]) ?>
            <?= $this->Form->control('full_base_url', ['label' => 'Public URL', 'value' => $settings['full_base_url'] ?? \Cake\Core\Configure::read('App.fullBaseUrl'), 'placeholder' => 'https://wiki.example.com', 'maxlength' => 1024]) ?>
        </section>
    </div>

    <div class="tab-pane" data-admin-pane="landing" role="tabpanel" hidden>
        <section class="panel">
            <h2>Landing page banner</h2>
            <?= $this->Form->control('landing_eyebrow', ['label' => 'Eyebrow', 'value' => $settings['landing_eyebrow'], 'maxlength' => 100]) ?>
            <?= $this->Form->control('landing_title', ['label' => 'Banner title', 'value' => $settings['landing_title'], 'maxlength' => 180]) ?>
            <?= $this->Form->control('landing_message', ['label' => 'Banner message', 'type' => 'textarea', 'rows' => 3, 'value' => $settings['landing_message'], 'maxlength' => 500]) ?>
            <?= $this->Form->control('landing_primary_label', ['label' => 'Primary button label', 'value' => $settings['landing_primary_label'], 'maxlength' => 50]) ?>
            <?= $this->Form->control('landing_primary_url', ['label' => 'Primary button URL', 'value' => $settings['landing_primary_url'], 'maxlength' => 1024]) ?>
            <?= $this->Form->control('landing_secondary_label', ['label' => 'Secondary button label', 'value' => $settings['landing_secondary_label'], 'maxlength' => 50]) ?>
            <?= $this->Form->control('landing_secondary_url', ['label' => 'Secondary button URL', 'value' => $settings['landing_secondary_url'], 'maxlength' => 1024]) ?>
            <h3>Feature cards</h3>
            <?php for ($index = 1; $index <= 3; $index++): ?>
                <?php $number = ['one', 'two', 'three'][$index - 1]; ?>
                <?= $this->Form->control('feature_' . $number . '_title', ['label' => 'Feature ' . $index . ' title', 'value' => $settings['feature_' . $number . '_title'], 'maxlength' => 100]) ?>
                <?= $this->Form->control('feature_' . $number . '_message', ['label' => 'Feature ' . $index . ' message', 'type' => 'textarea', 'rows' => 2, 'value' => $settings['feature_' . $number . '_message'], 'maxlength' => 300]) ?>
            <?php endfor ?>
        </section>
    </div>

    <div class="tab-pane" data-admin-pane="about" role="tabpanel" hidden>
        <section class="panel">
            <h2>About page</h2>
            <?= $this->Form->control('about_eyebrow', ['label' => 'Eyebrow', 'value' => $settings['about_eyebrow'], 'maxlength' => 100]) ?>
            <?= $this->Form->control('about_title', ['label' => 'Banner title', 'value' => $settings['about_title'], 'maxlength' => 180]) ?>
            <?= $this->Form->control('about_message', ['label' => 'Banner message', 'type' => 'textarea', 'rows' => 3, 'value' => $settings['about_message'], 'maxlength' => 500]) ?>
            <?= $this->Form->control('about_content', ['type' => 'textarea', 'label' => 'About page content', 'rows' => 12, 'value' => $settings['about_content']]) ?>
            <div id="visual-editor" hidden>
                <div class="editor-toolbar" role="toolbar" aria-label="About page text formatting">
                    <select data-command="heading" aria-label="Text style">
                        <option value="0">Paragraph</option><option value="1">Heading 1</option>
                        <option value="2">Heading 2</option><option value="3">Heading 3</option>
                    </select>
                    <button type="button" data-command="bold" title="Bold" aria-label="Bold"><i class="bi bi-type-bold" aria-hidden="true"></i></button>
                    <button type="button" data-command="italic" title="Italic" aria-label="Italic"><i class="bi bi-type-italic" aria-hidden="true"></i></button>
                    <button type="button" data-command="underline" title="Underline" aria-label="Underline"><i class="bi bi-type-underline" aria-hidden="true"></i></button>
                    <button type="button" data-command="bulletList" title="Bullet list" aria-label="Bullet list"><i class="bi bi-list-ul" aria-hidden="true"></i></button>
                    <button type="button" data-command="orderedList" title="Numbered list" aria-label="Numbered list"><i class="bi bi-list-ol" aria-hidden="true"></i></button>
                    <button type="button" data-command="blockquote" title="Block quote" aria-label="Block quote"><i class="bi bi-blockquote-left" aria-hidden="true"></i></button>
                    <button type="button" data-command="link" title="Insert link" aria-label="Insert link"><i class="bi bi-link-45deg" aria-hidden="true"></i></button>
                    <button type="button" data-command="unlink" title="Remove link" aria-label="Remove link"><i class="bi bi-link" aria-hidden="true"></i></button>
                    <select data-command="codeLanguage" title="Code language" disabled hidden><option value="plaintext">Plain text</option></select>
                </div>
                <div id="editor-link-panel" class="editor-link-panel" hidden>
                    <label for="editor-link-url">Link URL</label>
                    <input id="editor-link-url" type="text" placeholder="https://example.com">
                    <button type="button" data-panel-action="apply-link">Apply link</button>
                    <button type="button" data-panel-action="cancel-link" class="button-outline">Cancel</button>
                </div>
                <div id="editor-image-panel" hidden></div>
                <div id="editor-table-panel" hidden></div>
                <input type="file" id="editor-image" hidden>
                <div id="editor-content"></div>
                <p id="editor-status" class="help" role="status" aria-live="polite"></p>
            </div>
        </section>
    </div>

    <div class="tab-pane" data-admin-pane="accounts" role="tabpanel" hidden>
        <section class="panel narrow">
            <h2>Accounts and registration</h2>
            <?= $this->Form->control('registration_enabled', [
                'type' => 'checkbox',
                'label' => 'Allow public account registration',
                'checked' => ($settings['registration_enabled'] ?? '1') === '1',
            ]) ?>
            <?= $this->Form->control('require_verified_email', [
                'type' => 'checkbox',
                'label' => 'Require a verified email for account sign-in',
                'checked' => ($settings['require_verified_email'] ?? '1') === '1',
            ]) ?>
            <p class="help">Administrator and existing-user controls remain available regardless of registration settings.</p>
        </section>
    </div>

    <div class="tab-pane" data-admin-pane="email" role="tabpanel" hidden>
        <section class="panel narrow">
            <h2>Email delivery</h2>
            <?= $this->Form->control('mail_from', ['label' => 'Sender email address', 'type' => 'email', 'value' => $settings['mail_from'] ?? '', 'maxlength' => 254]) ?>
            <?= $this->Form->control('smtp_host', ['label' => 'SMTP host', 'value' => $settings['smtp_host'] ?? '', 'maxlength' => 253]) ?>
            <?= $this->Form->control('smtp_port', ['label' => 'SMTP port', 'type' => 'number', 'min' => 1, 'max' => 65535, 'value' => $settings['smtp_port'] ?? '587']) ?>
            <?= $this->Form->control('smtp_encryption', [
                'label' => 'SMTP security',
                'options' => ['tls' => 'STARTTLS', 'ssl' => 'SSL/TLS', 'none' => 'None (local testing only)'],
                'value' => $settings['smtp_encryption'] ?? 'tls',
            ]) ?>
            <?= $this->Form->control('smtp_username', ['label' => 'SMTP username', 'value' => $settings['smtp_username'] ?? '', 'autocomplete' => 'username']) ?>
            <?= $this->Form->control('smtp_password', [
                'label' => $smtpPasswordSet ? 'SMTP password (leave blank to keep current)' : 'SMTP password',
                'type' => 'password',
                'autocomplete' => 'new-password',
            ]) ?>
        </section>
    </div>
</div>

<div class="admin-settings-save">
    <?= $this->Form->button('Save site settings') ?>
</div>
<?= $this->Form->end() ?>

<section class="panel narrow admin-test-email" data-admin-test-email hidden>
    <h2>Send test email</h2>
    <?= $this->Form->create(null, ['url' => '/admin/settings/test-email']) ?>
    <?= $this->Form->control('test_email_address', ['label' => 'Recipient', 'type' => 'email', 'value' => $settings['test_email_address'] ?? '', 'required' => true]) ?>
    <?= $this->Form->button('Send test email', ['class' => 'button']) ?>
    <?= $this->Form->end() ?>
</section>

<script>
    const adminTabButtons = document.querySelectorAll('[data-admin-tab]');
    const adminTabPanes = document.querySelectorAll('[data-admin-pane]');
    const testEmailPanel = document.querySelector('[data-admin-test-email]');
    adminTabButtons.forEach((button) => {
        button.addEventListener('click', () => {
            const tab = button.dataset.adminTab;
            adminTabButtons.forEach((item) => item.classList.toggle('active', item === button));
            adminTabPanes.forEach((pane) => {
                const active = pane.dataset.adminPane === tab;
                pane.hidden = !active;
                pane.classList.toggle('active', active);
            });
            if (testEmailPanel) testEmailPanel.hidden = tab !== 'email';
        });
    });
</script>

<?= $this->Html->css('bootstrap-icons.min', ['block' => true]) ?>
<?= $this->Html->script('wiki-editor', ['block' => true, 'defer' => true]) ?>
