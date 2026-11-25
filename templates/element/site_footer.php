<footer class="site-footer">
    <div class="site-footer-inner">
        <div>
            <p><?= h(\Cake\Core\Configure::read('App.siteName', 'SJHWiki')) ?> v<?= h(\App\Service\ApplicationUpdater::VERSION) ?> &copy; <?= h(gmdate('Y')) ?></p>
            <p><?= h(\Cake\Core\Configure::read('App.footerText', 'Created by Steven Houldey')) ?></p>
        </div>
    </div>
</footer>
