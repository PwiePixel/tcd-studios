<?php
declare(strict_types=1);

defined('TCD_APP') || exit;

/** @var string $headline @var string $text @var array{0:string,1:string}|null $link */
?>
        <?= site_headline($headline, 'h2') ?>

        <div class="ed-element ed-text custom-theme site-page">
          <p class="site-lead"><?= h($text) ?></p>
          <div class="site-actions">
<?php if ($link) : ?>
            <a class="button button-large" href="<?= h($link[0]) ?>"><?= h($link[1]) ?></a>
<?php endif; ?>
            <a class="button button-large site-btn-ghost" href="/">Back to start</a>
          </div>
        </div>
