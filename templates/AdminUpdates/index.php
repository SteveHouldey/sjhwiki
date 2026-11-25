<?php $this->assign('title', 'Application updates'); ?>
<section class="page-heading">
    <div><p class="eyebrow">GLOBAL ADMINISTRATION</p><h1>Application updates</h1><p>Updates are checked through GitHub releases.</p></div>
</section>

<section class="panel narrow">
    <?php if ($error): ?>
        <p class="message warning"><?= h($error) ?></p>
    <?php elseif (!$check): ?>
        <p>Update information is unavailable.</p>
    <?php elseif ($check['available']): ?>
        <h2>Update available: <?= h($check['name']) ?></h2>
        <p>Current version: <strong><?= h($check['current']) ?></strong><br>
        Available version: <strong><?= h($check['version']) ?></strong></p>
        <?php if (!empty($check['published_at'])): ?>
            <p><small>Published <?= h(substr($check['published_at'], 0, 10)) ?></small></p>
        <?php endif ?>
        <?php if (!empty($check['notes'])): ?>
            <h3>Release notes</h3>
            <p><?= h(mb_substr($check['notes'], 0, 3000)) ?></p>
        <?php endif ?>
        <p class="message warning">Before updating, back up <code>config/app_local.php</code>, the database, uploaded media, and Git repository. The update overwrites application files but preserves local configuration and media.</p>
        <?= $this->Form->create(null, ['url' => '/admin/updates/apply']) ?>
        <?= $this->Form->button('Download and apply update', ['confirm' => 'Install the latest verified update now?']) ?>
        <?= $this->Form->end() ?>
    <?php else: ?>
        <h2>You are up to date</h2>
        <p>Installed version: <strong><?= h($check['current']) ?></strong></p>
        <?php if (!empty($check['url'])): ?>
            <p><?= $this->Html->link('View latest GitHub release', $check['url'], ['target' => '_blank', 'rel' => 'noopener']) ?></p>
        <?php endif ?>
    <?php endif ?>
</section>

<section class="panel">
    <h2>Changelog</h2>
    <?php if (!$changelog): ?>
        <p>No published GitHub releases are available to display.</p>
    <?php else: ?>
        <?php foreach ($changelog as $release): ?>
            <article style="padding:18px 0;border-top:1px solid var(--border);">
                <h3 style="margin:0;">
                    <?= h($release['name'] ?: $release['tag']) ?>
                    <small><?= h($release['version']) ?></small>
                </h3>
                <?php if (!empty($release['published_at'])): ?>
                    <p><small>Published <?= h(substr($release['published_at'], 0, 10)) ?></small></p>
                <?php elseif (!empty($release['prerelease'])): ?>
                    <p><small>Pre-release</small></p>
                <?php endif ?>
                <?php if (!empty($release['notes'])): ?>
                    <div style="white-space:pre-wrap;"><?= h($release['notes']) ?></div>
                <?php else: ?>
                    <p><small>No release notes were provided.</small></p>
                <?php endif ?>
                <?php if (!empty($release['url'])): ?>
                    <p><?= $this->Html->link('View on GitHub', $release['url'], ['target' => '_blank', 'rel' => 'noopener']) ?></p>
                <?php endif ?>
            </article>
        <?php endforeach ?>
    <?php endif ?>
</section>
