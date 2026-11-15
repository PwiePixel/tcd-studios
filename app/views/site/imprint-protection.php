<?php
declare(strict_types=1);

defined('TCD_APP') || exit;

/** @var string $imprintMail */
?>
        <?= site_headline('Imprint Protection', 'h2') ?>

        <div class="ed-element ed-text custom-theme site-page">
          <p class="site-lead">
            A legally sound imprint for creators, published on tcd-studio.de, with the management address of
            TCD-Studio/Agency instead of your private address.
          </p>

          <div class="site-actions">
            <a class="button button-large" href="/portal/register.php">Create account</a>
            <a class="button button-large site-btn-ghost" href="/portal/login.php">Sign in</a>
          </div>

          <div class="site-section">
            <?= site_headline('What is Imprint Protection?', 'h3') ?>
            <div class="site-intro">
              <p>
                Whoever publishes content online often has to provide an imprint (legal notice), and for many creators
                that means putting a private home address on the internet.
              </p>
              <p>
                With Imprint Protection your imprint lives on its own page at
                <strong>tcd-studio.de/legal/&lt;your-name&gt;/</strong>. It names you as the person responsible for the
                content and shows the management address of TCD-Studio/Agency instead of your private address. The
                personal data you give us for verification stays with us and is never published.
              </p>
            </div>
          </div>

          <div class="site-section">
            <?= site_headline('How it works', 'h3') ?>
            <div class="site-grid site-grid--4">
              <div class="site-card">
                <span class="site-step-number">1</span>
                <h4>Register</h4>
                <p>Create an account and confirm your e-mail address.</p>
              </div>
              <div class="site-card">
                <span class="site-step-number">2</span>
                <h4>Create your profile</h4>
                <p>Choose your page name and enter your details and links.</p>
              </div>
              <div class="site-card">
                <span class="site-step-number">3</span>
                <h4>We verify</h4>
                <p>We check your identity and approve your imprint.</p>
              </div>
              <div class="site-card">
                <span class="site-step-number">4</span>
                <h4>Go live</h4>
                <p>Your imprint page goes live on tcd-studio.de.</p>
              </div>
            </div>
          </div>

          <div class="site-section">
            <?= site_headline('What you control', 'h3') ?>
            <div class="site-grid">
              <div class="site-card">
                <h4>Your links</h4>
                <p>List the channels and websites that belong to your imprint, for example your stream, your social profiles or your shop.</p>
              </div>
              <div class="site-card">
                <h4>Contact visibility</h4>
                <p>Decide whether a direct contact e-mail address of yours is shown in the imprint or not.</p>
              </div>
              <div class="site-card">
                <h4>Your purpose text</h4>
                <p>Describe in your own words what you do. We suggest a text that you can adapt.</p>
              </div>
            </div>
            <p class="site-center">
              Every change goes through our team: nothing appears on your page before it has been approved. Until then,
              your last approved version stays online.
            </p>
          </div>

          <div class="site-section">
            <?= site_headline('What stays private', 'h3') ?>
            <div class="site-intro">
              <p>
                Your legal name, your address, your phone number, your VAT ID and your notes are only visible to you
                and to our team. They are used to verify your identity and are never shown in your imprint.
              </p>
            </div>
          </div>

          <div class="site-section">
            <?= site_headline('Who is it for?', 'h3') ?>
            <div class="site-grid">
              <div class="site-card">
                <h4>Creators</h4>
                <p>Video creators, artists and everybody who publishes content on the internet.</p>
              </div>
              <div class="site-card">
                <h4>Streamers</h4>
                <p>Streamers who want a proper imprint for their channel without publishing their home address.</p>
              </div>
              <div class="site-card">
                <h4>Shop operators</h4>
                <p>Later also for shops. Our <a href="/shops/">shop partnership</a> is available on request.</p>
              </div>
            </div>
          </div>

          <div class="site-section">
            <?= site_headline('Frequently asked questions', 'h3') ?>
            <div class="site-faq">
              <details>
                <summary>Is Imprint Protection available to me, and what does it cost?</summary>
                <p>
                  Availability and prices are available on request. Create an account or write to us at
                  <a href="mailto:<?= h($imprintMail) ?>"><?= h($imprintMail) ?></a> and we will get back to you.
                </p>
              </details>
              <details>
                <summary>Does this replace legal advice?</summary>
                <p>
                  No. Whether and how an imprint obligation applies to you depends on your situation and on the country
                  you operate from. We recommend that you read the legal basis yourself, for example Section 5 of the
                  Austrian E-Commerce Act (ECG) or Section 5 of the German Digitale-Dienste-Gesetz (DDG), and get legal
                  advice if you are unsure.
                </p>
              </details>
              <details>
                <summary>Who is responsible for the content of my imprint page?</summary>
                <p>
                  You provide the information, and we check it before it goes live. Please make sure that all details
                  you enter are correct, complete and up to date.
                </p>
              </details>
              <details>
                <summary>Which of my data is published?</summary>
                <p>
                  Only your public name, the type of your account, your purpose text, the links you add and, if you
                  choose so, a contact e-mail address. Your address and other identity details are not published.
                </p>
              </details>
              <details>
                <summary>How long does the review take?</summary>
                <p>We review every submission as soon as we can. We cannot promise a fixed time.</p>
              </details>
              <details>
                <summary>Can I change my imprint later?</summary>
                <p>
                  Yes. You can edit your profile at any time and submit the changes. They go live after our team has
                  approved them.
                </p>
              </details>
            </div>
          </div>

          <div class="site-section">
            <div class="site-notice">
              <p>
                <strong>Ready to start?</strong> Create your account and set up your imprint profile. You already have
                an account? Sign in to continue.
              </p>
            </div>
            <div class="site-actions">
              <a class="button button-large" href="/portal/register.php">Create account</a>
              <a class="button button-large site-btn-ghost" href="/portal/login.php">Sign in</a>
            </div>
          </div>
        </div>
