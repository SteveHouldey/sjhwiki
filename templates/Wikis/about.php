<?php $this->assign('title', 'About'); ?>
<section class="landing-hero">
    <div>
        <p class="eyebrow">ABOUT THIS WIKI</p>
        <h1>Knowledge is better when it is shared.</h1>
        <p>Bring documentation, ideas and practical guides together in one accessible place.</p>
        <div class="actions">
            <?= $this->Html->link('Search wikis', '/search', ['class' => 'button']) ?>
            <?php if ($currentUser): ?>
                <?= $this->Html->link('Go to dashboard', '/', ['class' => 'button button-outline']) ?>
            <?php else: ?>
                <?= $this->Html->link('Sign in', '/login', ['class' => 'button button-outline']) ?>
            <?php endif; ?>
        </div>
    </div>
    <div class="landing-illustration" aria-hidden="true">
        <div class="landing-document">
            <span class="landing-document-icon">W</span>
            <div class="landing-line landing-line-title"></div>
            <div class="landing-line"></div>
            <div class="landing-line"></div>
            <div class="landing-block"></div>
            <div class="landing-line landing-line-short"></div>
        </div>
    </div>
</section>
<section class="landing-features">
    <div><h2>Write together</h2><p>Create rich pages with code, tables, links and images.</p></div>
    <div><h2>Choose what to share</h2><p>Publish public wikis or restrict access with custom roles.</p></div>
    <div><h2>Keep a record</h2><p>Review and restore previous revisions, with optional Git synchronization.</p></div>
</section>
