/**
 * Site behaviour for tcd-studio.de
 *
 *  - Menu: mobile toggle, smooth scrolling, active-link highlighting, sticky header
 *  - Info bar: dismissible banner at the top of the page (remembered for 12 hours)
 *
 * Depends on jQuery (`$`) and the Webcard runtime (`viewport`), which are both
 * provided by /webcard/static/app.bundle.*.js and must be loaded first.
 */
(function ($) {
  'use strict';

  var MENU_OPEN_CLASSES = 'open open-menu';
  var STICKY_CLASS = 'sticky';
  var ACTIVE_CLASS = 'active';
  var INFO_BAR_COOKIE = 'cookieBar';
  var INFO_BAR_HOURS = 12;

  // Make :active pseudo classes work on iOS.
  document.addEventListener('touchstart', function () {}, false);

  $(function () {
    $('.menu-wrapper').each(function () {
      initMenu($(this));
    });
    initInfoBar();
  });

  /* ------------------------------------------------------------------ */
  /* Menu                                                                */
  /* ------------------------------------------------------------------ */

  function initMenu($menuWrapper) {
    var $body = $('body');
    var $menu = $('.ed-menu', $menuWrapper);
    var $menuLinks = $('a', $menu);
    var $menuTrigger = $('.menu-trigger', $menuWrapper);
    var $banner = $('.banner').first();

    // `open` is also set on the menu itself for backward compatibility.
    toggleClassOnClick($body.add($menu), $menuTrigger, MENU_OPEN_CLASSES);
    activateSmoothScroll($menuLinks.add($('.scroll a')));
    addClassOnVisibleLinkTargets($menuLinks, ACTIVE_CLASS, 2 / 3);
    handleSticky($menuWrapper, STICKY_CLASS, $banner);
  }

  /**
   * Calls `callback` whenever the height of `elm` changes.
   *
   * @param {HTMLElement} elm
   * @param {function} callback
   */
  function observeHeightChange(elm, callback) {
    if (!('ResizeObserver' in window) || elm == null) return;

    new ResizeObserver(callback).observe(elm);
  }

  /**
   * Toggles a CSS class on `$target` (and `$trigger`) when `$trigger` is clicked
   * and removes it again when a link inside `$target` is clicked.
   *
   * @param {jQuery} $target  Element(s) the CSS class is applied to
   * @param {jQuery} $trigger Element that toggles the class
   * @param {string} cssClass Class name(s) to toggle
   */
  function toggleClassOnClick($target, $trigger, cssClass) {
    // Reset in case the class was saved accidentally.
    $target.removeClass(cssClass);
    $trigger.removeClass(cssClass);

    $trigger.off('.toggle').on('click.toggle', function () {
      $(this).toggleClass(cssClass);
      $target.toggleClass(cssClass);
    });

    $target.find('a').click(function () {
      $target.removeClass(cssClass);
      $trigger.removeClass(cssClass);
    });
  }

  /**
   * Smooth-scrolls to the target of in-page links.
   * `#!next` scrolls to the element following the link's own element.
   *
   * @param {jQuery} $scrollLinks
   */
  function activateSmoothScroll($scrollLinks) {
    $scrollLinks.click(function (e) {
      var $target = this.hash === '#!next' ? $(this).closest('.ed-element').next() : $(this.hash);
      if (!$target.length) return;

      e.preventDefault();
      viewport.scrollTo($target, 'top', 500, 0);
    });
  }

  /**
   * The sticky mode is chosen in the theme CSS and handed to JavaScript through the
   * `fill` property of the menu wrapper (a colour acts as the enum value).
   *
   * @param {jQuery} $element
   * @returns {string}
   */
  function getStickyMode($element) {
    switch (getComputedStyle($element[0]).fill) {
      case 'rgb(255, 0, 0)':
        return 'sticky_banner';
      case 'rgb(0, 255, 0)':
        return 'sticky_menu';
      case 'rgb(0, 0, 255)':
        return 'sticky_instant';
      case 'rgb(255, 255, 255)':
        return 'sticky_reverse';
      default:
        return 'sticky_none';
    }
  }

  /**
   * Adds `cssClass` to `$element` once the page is scrolled past a trigger position.
   *
   * @param {jQuery} $element  The element to make sticky (the menu wrapper)
   * @param {string} cssClass  Class applied while the element is stuck
   * @param {jQuery} $banner   Banner used as the reference for the "sticky_banner" mode
   */
  function handleSticky($element, cssClass, $banner) {
    var triggerPos = 0;
    var offset = 0;
    var menuWrapperHeight = $element.outerHeight();
    var mode;
    var prevScroll = 0;

    $element.removeClass(cssClass);

    function toggleSpacer(toggle) {
      document.body.style.setProperty('--spacer-height', toggle ? menuWrapperHeight + 'px' : '');
    }

    function handleScroll() {
      if (!$element.length || mode === 'sticky_none') return;

      var isReverse = mode === 'sticky_reverse';
      var curScroll = viewport.getScrollTop();

      if (triggerPos <= curScroll && (!isReverse || prevScroll > curScroll)) {
        $element.addClass(cssClass);
        toggleSpacer(true);
      } else {
        $element.removeClass(cssClass);
        toggleSpacer(false);
      }

      prevScroll = curScroll;
    }

    function updateOffset() {
      mode = getStickyMode($element);
      menuWrapperHeight = $element.outerHeight();

      if (!$element.hasClass(cssClass)) {
        offset = $element.offset().top;
      }
      if (mode === 'sticky_banner' && !$banner.length) {
        mode = 'sticky_menu';
      }
      if (mode === 'sticky_banner') {
        triggerPos = $banner.offset().top + $banner.outerHeight();
      }
      if (mode === 'sticky_menu' || mode === 'sticky_reverse') {
        triggerPos = offset + $element.outerHeight();
      }
      if (mode === 'sticky_instant') {
        triggerPos = offset;
      }

      handleScroll();
    }

    viewport.observe('resize', updateOffset);
    viewport.observe('animation.end', updateOffset);
    observeHeightChange($element[0], updateOffset);
    updateOffset();

    viewport.observe('scroll', handleScroll);
    handleScroll();
  }

  /**
   * Adds `cssClass` to the link whose in-page target is currently in the viewport.
   *
   * @param {jQuery} $links                 Links to observe
   * @param {string} cssClass               Class applied to the active link
   * @param {number} [sectionViewportRatio] Share of the viewport the section must have entered
   */
  function addClassOnVisibleLinkTargets($links, cssClass, sectionViewportRatio) {
    if (typeof sectionViewportRatio === 'undefined') {
      sectionViewportRatio = 1 / 2;
    }

    var menuTargets = [];
    var activeLink = $links.filter('.active:not(.wv-link-elm)').eq(0);

    var links = $links.filter(function () {
      var $target = $(this.hash);
      if (!$target.length) return false;

      // Cache the offset to keep scrolling cheap; refresh it on resize/animation end.
      function updateOffset() {
        $target.data('offset', $target.offset().top);
      }

      viewport.observe('resize', updateOffset);
      viewport.observe('animation.end', updateOffset);
      updateOffset();

      menuTargets.push($target);
      return true;
    });

    // No in-page links found, nothing to highlight.
    if (!links.length) return;

    function checkVisibility() {
      $links.removeClass(ACTIVE_CLASS);

      // Check the sections from the bottom up: the last one that has been reached wins.
      for (var i = menuTargets.length - 1; i >= 0; i--) {
        var desiredScrollPosition = menuTargets[i].data('offset') - viewport.getHeight() * (1 - sectionViewportRatio);
        if (viewport.getScrollTop() >= desiredScrollPosition && menuTargets[i][0].offsetParent !== null) {
          links.eq(i).addClass(cssClass);
          return;
        }
      }

      // Fall back to the link that was active in the markup.
      activeLink.addClass(cssClass);
    }

    viewport.observe('scroll', checkVisibility);
    checkVisibility();
  }

  /* ------------------------------------------------------------------ */
  /* Info bar (top banner with a dismiss button)                         */
  /* ------------------------------------------------------------------ */

  function initInfoBar() {
    var $bar = $('.cookie-bar');

    $('.cookie-close').click(function (e) {
      e.preventDefault();
      $bar.hide();
      writeCookie(INFO_BAR_COOKIE, true, INFO_BAR_HOURS / 24);
    });

    if (readCookie(INFO_BAR_COOKIE) !== null) {
      $bar.hide();
    } else {
      $bar.show();
    }
  }

  function readCookie(name) {
    var prefix = name + '=';
    var cookies = document.cookie.split(';');

    for (var i = 0; i < cookies.length; i++) {
      var cookie = cookies[i].replace(/^ +/, '');
      if (cookie.indexOf(prefix) === 0) return cookie.substring(prefix.length);
    }

    return null;
  }

  function writeCookie(name, value, days) {
    var expires = '';

    if (days) {
      expires = '; expires=' + new Date(Date.now() + days * 24 * 60 * 60 * 1000).toUTCString();
    }

    document.cookie = name + '=' + value + expires + '; path=/';
  }
})(jQuery);
