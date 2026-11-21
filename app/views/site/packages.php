<?php
declare(strict_types=1);

defined('TCD_APP') || exit;

/**
 * Pricing cards of the Imprint Protection packages (site_package_cards()) inside /imprint-protection/.
 *
 * @var array<int,array<string,mixed>> $cards @var string $note
 */
if (!$cards) {
    return;
}
?>
          <div class="site-section" id="packages">
            <?= site_headline(__('Packages'), 'h3') ?>
            <div class="site-packages">
<?php foreach ($cards as $card) : ?>
              <div class="site-package<?= $card['badge'] !== '' ? ' site-package--featured' : '' ?>" id="package-<?= h($card['slug']) ?>">
<?php if ($card['badge'] !== '') : ?>
                <span class="site-package-badge"><?= h($card['badge']) ?></span>
<?php endif; ?>
                <h4 class="site-package-name"><?= h($card['name']) ?></h4>
<?php if ($card['tagline'] !== '') : ?>
                <p class="site-package-tagline"><?= h($card['tagline']) ?></p>
<?php endif; ?>
<?php if ($card['amount'] !== '') : ?>
                <p class="site-package-price"><span class="site-package-amount"><?= h($card['amount']) ?></span> <span class="site-package-per"><?= h($card['suffix']) ?></span></p>
<?php else : ?>
                <p class="site-package-price"><span class="site-package-per"><?= h(__('Price on request')) ?></span></p>
<?php endif; ?>
<?php if ($card['alt'] !== '') : ?>
                <p class="site-package-alt"><?= h($card['alt']) ?></p>
<?php endif; ?>
<?php if ($card['features']) : ?>
                <ul class="site-package-features">
<?php foreach ($card['features'] as $f) : ?>
                  <li><?= h($f) ?></li>
<?php endforeach; ?>
                </ul>
<?php endif; ?>
<?php if ($card['extras']) : ?>
                <p class="site-package-extras-title"><?= h(__('Additional, depending on use')) ?></p>
                <ul class="site-package-extras">
<?php foreach ($card['extras'] as $f) : ?>
                  <li><?= h($f) ?></li>
<?php endforeach; ?>
                </ul>
<?php endif; ?>
                <p class="site-package-cta"><a class="button button-large" href="<?= h($card['href']) ?>"><?= h(sprintf(__('Choose %s'), $card['name'])) ?></a></p>
              </div>
<?php endforeach; ?>
            </div>
<?php if ($note !== '') : ?>
            <p class="site-packages-note"><?= h($note) ?> <a href="/agb/"><?= h(__('Terms and Conditions (AGB)')) ?></a></p>
<?php endif; ?>
          </div>
