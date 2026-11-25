<?php $this->assign('title', 'Admin center'); ?>
<section class="page-heading">
    <div><p class="eyebrow">GLOBAL ADMINISTRATION</p><h1>Admin center</h1><p>Configure the site, account access, email, and public-facing details.</p></div>
</section>

<?= $this->Form->create(null) ?>
<section class="panel narrow">
    <h2>Site</h2>
    <?= $this->Form->control('site_name', ['label' => 'Site name', 'value' => $settings['site_name'] ?? 'SJHWiki', 'maxlength' => 100, 'required' => true]) ?>
    <?= $this->Form->control('site_tagline', ['label' => 'Site tagline', 'value' => $settings['site_tagline'] ?? '', 'maxlength' => 180]) ?>
    <?= $this->Form->control('footer_text', ['label' => 'Footer text', 'value' => $settings['footer_text'] ?? '', 'maxlength' => 180]) ?>
    <?= $this->Form->control('full_base_url', ['label' => 'Public URL', 'value' => $settings['full_base_url'] ?? \Cake\Core\Configure::read('App.fullBaseUrl'), 'placeholder' => 'https://wiki.example.com', 'maxlength' => 1024]) ?>
</section>

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

<section class="panel narrow">
    <h2>Email</h2>
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

<?= $this->Form->button('Save site settings') ?>
<?= $this->Form->end() ?>
