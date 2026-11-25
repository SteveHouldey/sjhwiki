<?php $this->assign('title', 'First-run setup'); ?>
<section class="panel narrow">
    <h1>Set up your wiki</h1>
    <?php if (!empty($webSetup)): ?>
        <p>Use an empty database. Existing databases and configuration are never overwritten.</p>
        <p>Use HTTPS, keep the setup token private and protect the configuration directory from web access.</p>
        <p>After installation, sign in and request a verification email for the administrator address.</p>
        <p><strong>Setup token file:</strong> <code>config/setup-token.txt</code> must contain exactly 64 lowercase letters or digits and no extra text.</p>
        <?php if (!empty($setupProblems)): ?>
            <h2>Server requirements</h2>
            <ul>
                <?php foreach ($setupProblems as $problem): ?>
                    <li><?= h($problem) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <?= $this->Form->create(null, ['autocomplete' => 'off']) ?>
        <?= $this->Form->control('token', ['label' => 'Setup token from config/setup-token.txt', 'type' => 'password', 'required' => true]) ?>
        <h2>Website</h2>
        <?= $this->Form->control('base_url', ['label' => 'Public website URL', 'type' => 'url', 'required' => true, 'placeholder' => 'https://wiki.example.com']) ?>
        <h2>Database</h2>
        <?= $this->Form->control('database_type', ['label' => 'Database type', 'options' => ['mysql' => 'MySQL / MariaDB', 'sqlite' => 'SQLite']]) ?>
        <?= $this->Form->control('database', ['label' => 'Database name or absolute SQLite file path', 'required' => true]) ?>
        <?= $this->Form->control('database_host', ['label' => 'MySQL host', 'default' => 'localhost']) ?>
        <?= $this->Form->control('database_port', ['label' => 'MySQL port', 'default' => '3306', 'type' => 'number', 'min' => 1, 'max' => 65535]) ?>
        <?= $this->Form->control('database_username', ['label' => 'MySQL username']) ?>
        <?= $this->Form->control('database_password', ['label' => 'MySQL password', 'type' => 'password', 'autocomplete' => 'new-password']) ?>
        <p>For SQLite, enter a file path outside the public webroot. The parent directory must already exist and be writable.</p>
        <h2>Email</h2>
        <?= $this->Form->control('mail_from', ['label' => 'Sender email address', 'type' => 'email', 'required' => true]) ?>
        <?= $this->Form->control('smtp_host', ['label' => 'SMTP host', 'required' => true, 'default' => 'localhost']) ?>
        <?= $this->Form->control('smtp_port', ['label' => 'SMTP port', 'type' => 'number', 'min' => 1, 'max' => 65535, 'required' => true, 'default' => '587']) ?>
        <?= $this->Form->control('smtp_encryption', ['label' => 'SMTP security', 'options' => ['tls' => 'STARTTLS', 'ssl' => 'SSL/TLS', 'none' => 'None (local testing only)']]) ?>
        <?= $this->Form->control('smtp_username', ['label' => 'SMTP username (optional)']) ?>
        <?= $this->Form->control('smtp_password', ['label' => 'SMTP password', 'type' => 'password', 'autocomplete' => 'new-password']) ?>
        <h2>Administrator</h2>
        <?= $this->Form->control('username', ['required' => true, 'autocomplete' => 'username']) ?>
        <?= $this->Form->control('email', ['type' => 'email', 'label' => 'Administrator email', 'required' => true, 'autocomplete' => 'email']) ?>
        <?= $this->Form->control('password', ['type' => 'password', 'required' => true, 'autocomplete' => 'new-password']) ?>
        <?= $this->Form->control('password_confirm', ['label' => 'Confirm password', 'type' => 'password', 'required' => true, 'autocomplete' => 'new-password']) ?>
        <?= $this->Form->button('Install wiki') ?>
        <?= $this->Form->end() ?>
    <?php elseif (!$setupEnabled): ?>
        <p>Setup is disabled. Configure a setup token of at least 32 characters on the server and reload this page.</p>
    <?php else: ?>
        <p>Create the first application administrator. Setup can only be completed once.</p>
        <?= $this->Form->create(null) ?>
        <?= $this->Form->control('token', ['label' => 'Setup token', 'type' => 'password', 'required' => true]) ?>
        <?= $this->Form->control('username', ['required' => true, 'autocomplete' => 'username']) ?>
        <?= $this->Form->control('email', ['type' => 'email', 'label' => 'Email (optional)', 'autocomplete' => 'email']) ?>
        <?= $this->Form->control('password', ['type' => 'password', 'required' => true, 'autocomplete' => 'new-password']) ?>
        <?= $this->Form->control('password_confirm', ['label' => 'Confirm password', 'type' => 'password', 'required' => true]) ?>
        <?= $this->Form->button('Create administrator') ?>
        <?= $this->Form->end() ?>
    <?php endif; ?>
</section>
