<?php $this->assign('title', 'Application updates'); ?>
<section class="page-heading">
    <div><p class="eyebrow">GLOBAL ADMINISTRATION</p><h1>Application updates</h1><p>Updates are checked through GitHub releases.</p></div>
</section>

<section class="panel update-status">
    <?php if ($error): ?>
        <div class="update-notice warning-notice" role="status">
            <span class="update-notice-icon" aria-hidden="true">!</span>
            <div><strong>Update check unavailable</strong><p><?= h($error) ?></p></div>
        </div>
    <?php elseif (!$check): ?>
        <div class="update-notice warning-notice" role="status">
            <span class="update-notice-icon" aria-hidden="true">!</span>
            <div><strong>Update check unavailable</strong><p>Update information could not be retrieved.</p></div>
        </div>
    <?php elseif ($check['available']): ?>
        <div class="update-notice info-notice" role="status">
            <span class="update-notice-icon" aria-hidden="true">i</span>
            <div>
                <strong>Update available: <?= h($check['name']) ?></strong>
                <p>Current version <strong><?= h($check['current']) ?></strong> · Available version <strong><?= h($check['version']) ?></strong></p>
            </div>
        </div>
        <div class="update-details">
            <?php if (!empty($check['published_at'])): ?>
                <p><small>Published <?= h(substr($check['published_at'], 0, 10)) ?></small></p>
            <?php endif ?>
            <?php if (!empty($check['notes'])): ?>
                <h3>Release notes</h3>
                <p><?= h(mb_substr($check['notes'], 0, 3000)) ?></p>
            <?php endif ?>
            <p class="message warning">Before updating, create a full backup of the database and uploaded media. The updater backs up <code>config/app_local.php</code> and the application files, preserves uploaded media, runs migrations automatically, and rolls back application/config/database state where supported.</p>
            <?= $this->Form->create(null, ['url' => '/admin/updates/apply', 'id' => 'apply-update-form']) ?>
            <?= $this->Form->button('Download and apply update', ['id' => 'start-update-button', 'type' => 'button']) ?>
            <?= $this->Form->end() ?>
        </div>
    <?php else: ?>
        <div class="update-notice success-notice" role="status">
            <span class="update-notice-icon" aria-hidden="true">✓</span>
            <div>
                <strong>You are up to date</strong>
                <p>Installed version <strong><?= h($check['current']) ?></strong></p>
            </div>
        </div>
        <div class="update-details">
            <?php if (!empty($check['url'])): ?>
                <p><?= $this->Html->link('View latest GitHub release', $check['url'], ['target' => '_blank', 'rel' => 'noopener']) ?></p>
            <?php endif ?>
        </div>
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

<script>
    const updateForm = document.querySelector('#apply-update-form');
    const startUpdateButton = document.querySelector('#start-update-button');
    if (updateForm && startUpdateButton) {
        const modal = document.querySelector('#update-progress-modal');
        const progress = document.querySelector('#update-progress');
        const progressValue = document.querySelector('#update-progress-value');
        const progressStatus = document.querySelector('#update-progress-status');
        const closeButton = document.querySelector('#update-progress-close');
        const modalTitle = document.querySelector('#update-progress-title');
        let progressTimer = null;

        const setProgress = (value, status) => {
            progress.value = value;
            progressValue.textContent = value + '%';
            progressStatus.textContent = status;
        };

        startUpdateButton.addEventListener('click', () => {
            if (!window.confirm('Install the latest verified update now?')) return;
            modal.showModal();
            closeButton.disabled = true;
            modalTitle.textContent = 'Applying update';
            setProgress(8, 'Preparing update...');
            let value = 8;
            progressTimer = window.setInterval(() => {
                if (value < 82) {
                    value += value < 35 ? 4 : value < 65 ? 2 : 1;
                    const status = value < 35
                        ? 'Downloading verified package...'
                        : value < 65
                            ? 'Checking package checksum...'
                            : 'Installing update files...';
                    setProgress(value, status);
                }
            }, 500);

            const formData = new FormData(updateForm);
            fetch(updateForm.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            }).then(async (response) => {
                const result = await response.json().catch(() => ({
                    success: false,
                    message: 'The server returned an unreadable update response.',
                }));
                window.clearInterval(progressTimer);
                if (!response.ok || !result.success) throw new Error(result.message);
                setProgress(100, 'Update installed');
                modalTitle.textContent = 'Update complete';
                progressStatus.textContent = result.message;
                closeButton.disabled = false;
                closeButton.textContent = 'Reload page';
                closeButton.dataset.reload = 'true';
            }).catch((error) => {
                window.clearInterval(progressTimer);
                setProgress(progress.value, 'Update failed');
                modalTitle.textContent = 'Update failed';
                progressStatus.textContent = error.message;
                closeButton.disabled = false;
                closeButton.textContent = 'Close';
            });
        });

        closeButton.addEventListener('click', () => {
            modal.close();
            if (closeButton.dataset.reload === 'true') window.location.reload();
        });
    }
</script>

<dialog id="update-progress-modal" class="update-progress-modal" aria-labelledby="update-progress-title">
    <div class="update-progress-content">
        <h2 id="update-progress-title">Applying update</h2>
        <div class="update-progress-row">
            <progress id="update-progress" max="100" value="0"></progress>
            <strong id="update-progress-value">0%</strong>
        </div>
        <p id="update-progress-status" role="status" aria-live="polite">Preparing update...</p>
        <button type="button" id="update-progress-close" class="button" disabled>Close</button>
    </div>
</dialog>
