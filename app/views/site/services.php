<?php
declare(strict_types=1);

defined('TCD_APP') || exit;

/** @var array<int,array<string,string>> $cards @var bool $unavailable */
?>
        <?= site_headline('Our Services', 'h2') ?>

        <div class="ed-element ed-text custom-theme site-page">
          <p class="site-lead">
            Imprint Protection for all creators, and shops on request.
          </p>

<?php if ($unavailable) : ?>
          <div class="site-notice site-notice--error">
            <p>The list of services is temporarily unavailable. Please try again later.</p>
          </div>
<?php elseif (!$cards) : ?>
          <div class="site-notice">
            <p>We are preparing our service catalogue. Please check back soon.</p>
          </div>
<?php else : ?>
          <div class="site-grid">
<?php foreach ($cards as $card) : ?>
            <div class="site-card">
              <span class="site-badge site-badge--<?= h($card['availability']) ?>"><?= h($card['badge']) ?></span>
              <h3><?= h($card['name']) ?></h3>
<?php if ($card['summary'] !== '') : ?>
              <p><?= h($card['summary']) ?></p>
<?php endif; ?>
<?php if ($card['details'] !== '') : ?>
              <p><?= h($card['details']) ?></p>
<?php endif; ?>
              <p class="site-card-cta"><a class="button" href="<?= h($card['href']) ?>"><?= h($card['label']) ?></a></p>
            </div>
<?php endforeach; ?>
          </div>
<?php endif; ?>

          <p class="site-center">
            Not sure what fits? <a href="/#ed-713620122">Get in touch</a> and we will find out together.
          </p>
        </div>
