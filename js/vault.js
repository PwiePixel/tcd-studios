/*
 * Identity vault, browser side (admin panel only). No inline scripts, no libraries: WebCrypto + the DOM.
 *
 *  1. Key pair generator (admin/vault.php, [data-vault-generator]):
 *     RSA-OAEP 4096 bit, hash SHA-1 (PHP's openssl OAEP uses SHA-1). The PUBLIC key (SPKI PEM) is written into the form field;
 *     the PRIVATE key (PKCS#8 PEM) is only offered as a file download (id-vault-private-key.pem). It is never put into a form
 *     field, never sent anywhere and never stored.
 *  2. Decryption (admin/identity.php, [data-vault-decrypt-root]):
 *     the admin chooses the private key file (read into memory only), each ciphertext file is fetched from
 *     admin/identity-file.php, unwrapped (RSA-OAEP) and decrypted (AES-256-GCM) and shown through blob: URLs.
 *     Blob format: "TCDV1" | uint16 BE n | wrapped key (n bytes) | iv (12) | ciphertext | tag (16)
 *     (WebCrypto wants ciphertext||tag in one buffer, which is exactly the tail of the blob).
 */
(function () {
  'use strict';

  var subtle = (window.crypto && window.crypto.subtle) || null;
  var RSA = { name: 'RSA-OAEP', hash: 'SHA-1' };

  /* ------------------------------------------------------------ helpers */

  function VaultError(message, kind) {
    this.name = 'VaultError';
    this.message = message;
    this.kind = kind || 'error';
  }
  VaultError.prototype = Object.create(Error.prototype);
  VaultError.prototype.constructor = VaultError;

  function b64ToBytes(b64) {
    var bin = atob(b64);
    var out = new Uint8Array(bin.length);
    for (var i = 0; i < bin.length; i++) { out[i] = bin.charCodeAt(i); }
    return out;
  }

  function bytesToB64(bytes) {
    var s = '';
    for (var i = 0; i < bytes.length; i += 0x8000) {
      s += String.fromCharCode.apply(null, bytes.subarray(i, i + 0x8000));
    }
    return btoa(s);
  }

  function toPem(der, label) {
    var b64 = bytesToB64(der);
    var lines = b64.match(/.{1,64}/g) || [];
    return '-----BEGIN ' + label + '-----\n' + lines.join('\n') + '\n-----END ' + label + '-----\n';
  }

  /** SHA-256 of the SPKI DER in the same format as vault_fingerprint() in PHP ("abcd:ef01:..."). */
  async function fingerprintOf(spkiDer) {
    var hash = new Uint8Array(await subtle.digest('SHA-256', spkiDer));
    var hex = '';
    for (var i = 0; i < hash.length; i++) { hex += (hash[i] < 16 ? '0' : '') + hash[i].toString(16); }
    return hex.match(/.{1,4}/g).join(':');
  }

  function same(a, b) {
    if (a.length !== b.length) { return false; }
    for (var i = 0; i < a.length; i++) { if (a[i] !== b[i]) { return false; } }
    return true;
  }

  function show(el, visible) {
    if (el) { el.hidden = !visible; }
  }

  /* ----------------------------------------------------- key generation */

  function initGenerator(root) {
    var btn = root.querySelector('[data-vault-generate]');
    var statusEl = root.querySelector('[data-vault-status]');
    var result = root.querySelector('[data-vault-result]');
    var fpEl = root.querySelector('[data-vault-fp]');
    var link = root.querySelector('[data-vault-download]');
    var stored = root.querySelector('[data-vault-stored]');
    var area = document.querySelector('[data-vault-public-key]');
    var save = document.querySelector('[data-vault-save]');
    var nojs = root.querySelectorAll('[data-vault-nojs]');
    if (!btn || !statusEl || !result || !link || !stored || !area) { return; }

    if (!subtle) {
      statusEl.textContent = 'This browser does not offer WebCrypto here (it needs an https:// page or localhost). Use the openssl commands above on a trusted computer instead.';
      return;
    }
    for (var i = 0; i < nojs.length; i++) { show(nojs[i], false); }
    btn.disabled = false;

    var downloadUrl = null;
    var generatedPem = '';
    var downloaded = false;

    function gate() {
      if (save && generatedPem !== '') { save.disabled = !(downloaded && stored.checked); }
    }
    function reset() {
      if (downloadUrl) { URL.revokeObjectURL(downloadUrl); downloadUrl = null; }
      link.removeAttribute('href');
      generatedPem = '';
      downloaded = false;
      stored.checked = false;
      show(result, false);
      if (save) { save.disabled = false; }
    }

    link.addEventListener('click', function () {
      downloaded = true;
      gate();
    });
    stored.addEventListener('change', gate);
    // Editing the public key by hand means the generated pair is no longer the one that will be saved.
    area.addEventListener('input', function () {
      if (generatedPem !== '' && area.value.trim() !== generatedPem) {
        reset();
        statusEl.textContent = 'The public key was edited; the generated private key is no longer offered.';
      }
    });
    window.addEventListener('pagehide', function () { if (downloadUrl) { URL.revokeObjectURL(downloadUrl); } });

    btn.addEventListener('click', async function () {
      reset();
      btn.disabled = true;
      statusEl.textContent = 'Generating a 4096-bit RSA key pair in this browser. This can take up to a minute ...';
      try {
        var pair = await subtle.generateKey(
          { name: 'RSA-OAEP', modulusLength: 4096, publicExponent: new Uint8Array([1, 0, 1]), hash: 'SHA-1' },
          true, ['encrypt', 'decrypt']
        );
        var spki = new Uint8Array(await subtle.exportKey('spki', pair.publicKey));
        var pkcs8 = new Uint8Array(await subtle.exportKey('pkcs8', pair.privateKey));

        // Self test: the pair must round-trip a random AES-sized secret.
        var secret = window.crypto.getRandomValues(new Uint8Array(32));
        var wrapped = await subtle.encrypt({ name: 'RSA-OAEP' }, pair.publicKey, secret);
        var back = new Uint8Array(await subtle.decrypt({ name: 'RSA-OAEP' }, pair.privateKey, wrapped));
        if (!same(secret, back)) { throw new Error('self test failed'); }

        generatedPem = toPem(spki, 'PUBLIC KEY').trim();
        area.value = generatedPem + '\n';
        generatedPem = area.value.trim();
        fpEl.textContent = await fingerprintOf(spki);
        var blob = new Blob([toPem(pkcs8, 'PRIVATE KEY')], { type: 'application/x-pem-file' });
        pkcs8.fill(0);
        downloadUrl = URL.createObjectURL(blob);
        link.href = downloadUrl;
        show(result, true);
        statusEl.textContent = 'Done. Download the private key now and store it offline. Then tick the box and save the public key below.';
        if (save) { save.disabled = true; }
      } catch (e) {
        reset();
        statusEl.textContent = 'The key pair could not be generated (' + (e && e.message ? e.message : 'unknown error') + '). Use the openssl commands above instead.';
      } finally {
        btn.disabled = false;
      }
    });
  }

  /* ----------------------------------------------------------- decryption */

  async function importPrivateKey(text) {
    text = text.replace(/\r/g, '');
    if (text.indexOf('-----BEGIN ENCRYPTED PRIVATE KEY-----') !== -1) {
      throw new VaultError('This private key is protected by a password, which this page cannot use. On a trusted offline computer run: openssl pkcs8 -in KEY.pem -out id-vault-private-key.pem -nocrypt', 'key');
    }
    if (text.indexOf('-----BEGIN RSA PRIVATE KEY-----') !== -1) {
      throw new VaultError('This is an old-style (PKCS#1) private key. On a trusted offline computer convert it: openssl pkcs8 -topk8 -nocrypt -in OLD.pem -out id-vault-private-key.pem', 'key');
    }
    if (/-----BEGIN (RSA )?PUBLIC KEY-----/.test(text)) {
      throw new VaultError('This is a PUBLIC key. Please choose the PRIVATE key file (id-vault-private-key.pem).', 'key');
    }
    var m = /-----BEGIN PRIVATE KEY-----([A-Za-z0-9+\/=\s]+)-----END PRIVATE KEY-----/.exec(text);
    if (!m) {
      throw new VaultError('This file does not contain a PEM private key ("-----BEGIN PRIVATE KEY-----"). Did you choose the right file?', 'key');
    }
    try {
      var der = b64ToBytes(m[1].replace(/\s+/g, ''));
      return await subtle.importKey('pkcs8', der, RSA, false, ['decrypt']);
    } catch (e) {
      throw new VaultError('The private key file is damaged or is not an RSA key, so it cannot be used.', 'key');
    }
  }

  /** Decrypt one TCDV1 blob (ArrayBuffer) with the private CryptoKey. @returns {Promise<Uint8Array>} */
  async function decryptBlob(buf, privateKey) {
    var b = new Uint8Array(buf);
    var magic = String.fromCharCode.apply(null, b.subarray(0, 5));
    if (b.length < 5 + 2 + 12 + 16 + 1 || magic !== 'TCDV1') {
      throw new VaultError('This is not a valid encrypted ID file (bad header). The stored file is corrupted.', 'corrupt');
    }
    var n = (b[5] << 8) | b[6];
    if (n < 128 || 7 + n + 12 + 16 + 1 > b.length) {
      throw new VaultError('The encrypted file is truncated or damaged (bad length).', 'corrupt');
    }
    var wrapped = b.subarray(7, 7 + n);
    var iv = b.subarray(7 + n, 7 + n + 12);
    var sealed = b.subarray(7 + n + 12);       // ciphertext || tag

    var raw;
    try {
      raw = new Uint8Array(await subtle.decrypt({ name: 'RSA-OAEP' }, privateKey, wrapped));
    } catch (e) {
      throw new VaultError('This private key does not fit the file (wrong key). Use the key pair that belongs to the public key the file was encrypted for.', 'wrongkey');
    }
    if (raw.length !== 32) {
      throw new VaultError('The encrypted file is damaged (unexpected key size).', 'corrupt');
    }
    var aes = await subtle.importKey('raw', raw, 'AES-GCM', false, ['decrypt']);
    raw.fill(0);
    var plain;
    try {
      plain = new Uint8Array(await subtle.decrypt({ name: 'AES-GCM', iv: iv, tagLength: 128 }, aes, sealed));
    } catch (e) {
      throw new VaultError('The file failed its integrity check: it is corrupted or was modified. Do not trust it.', 'corrupt');
    }
    return plain;
  }

  function initDecrypt(root) {
    var fileInput = root.querySelector('[data-vault-keyfile]');
    var go = root.querySelector('[data-vault-decrypt]');
    var forget = root.querySelector('[data-vault-forget]');
    var statusEl = root.querySelector('[data-vault-status]');
    var figures = root.querySelectorAll('[data-vault-file]');
    if (!fileInput || !go || !statusEl) { return; }

    if (!subtle) {
      statusEl.textContent = 'This browser does not offer WebCrypto on this page (it needs https:// or localhost), so the photos cannot be decrypted here.';
      statusEl.className = 'pf-error-text';
      return;
    }
    go.disabled = false;

    var privateKey = null;      // non-extractable CryptoKey, kept in memory only
    var urls = [];
    var busy = false;

    function setStatus(text, isError) {
      statusEl.textContent = text;
      statusEl.className = isError ? 'pf-error-text' : 'pf-muted';
    }
    function clearImages() {
      for (var i = 0; i < urls.length; i++) { URL.revokeObjectURL(urls[i]); }
      urls = [];
      for (var j = 0; j < figures.length; j++) {
        var slot = figures[j].querySelector('[data-vault-slot]');
        if (slot) { slot.textContent = ''; }
        figures[j].classList.remove('is-zoomed');
        figures[j].removeAttribute('data-state');
      }
    }
    function wipe() {
      clearImages();
      privateKey = null;
      fileInput.value = '';
      show(forget, false);
      root.removeAttribute('data-state');
    }

    function readKeyFile(file) {
      return new Promise(function (resolve, reject) {
        if (file.size > 20000) { reject(new VaultError('This file is too large to be a private key.', 'key')); return; }
        var r = new FileReader();
        r.onload = function () { resolve(String(r.result)); };
        r.onerror = function () { reject(new VaultError('The key file could not be read.', 'key')); };
        r.readAsText(file);
      });
    }

    async function fetchBlob(url) {
      var res;
      try {
        res = await fetch(url, { credentials: 'same-origin', cache: 'no-store', redirect: 'manual', headers: { 'Accept': 'application/octet-stream' } });
      } catch (e) {
        throw new VaultError('The encrypted file could not be loaded (network error).', 'fetch');
      }
      if (res.type === 'opaqueredirect' || res.status === 401 || res.status === 403) {
        throw new VaultError('Your admin session has expired. Sign in again and reopen this page.', 'fetch');
      }
      if (res.status === 404) { throw new VaultError('The encrypted file no longer exists on the server.', 'fetch'); }
      if (res.status === 429) { throw new VaultError('Too many requests. Please wait a few minutes and try again.', 'fetch'); }
      if (!res.ok) { throw new VaultError('The server could not deliver the encrypted file (HTTP ' + res.status + ').', 'fetch'); }
      return res.arrayBuffer();
    }

    function showImage(fig, bytes) {
      var isJpeg = bytes.length > 3 && bytes[0] === 0xFF && bytes[1] === 0xD8 && bytes[2] === 0xFF;
      if (!isJpeg) { throw new VaultError('The decrypted data is not an image. The file is corrupted.', 'corrupt'); }
      var url = URL.createObjectURL(new Blob([bytes], { type: 'image/jpeg' }));
      urls.push(url);
      var slot = fig.querySelector('[data-vault-slot]');
      slot.textContent = '';
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'pf-zoom';
      btn.setAttribute('aria-label', 'Zoom: ' + (fig.getAttribute('data-label') || 'ID document'));
      var img = document.createElement('img');
      img.className = 'pf-idimg';
      img.alt = (fig.getAttribute('data-label') || 'ID document') + ' of the identity document';
      img.src = url;
      btn.appendChild(img);
      btn.addEventListener('click', function () { fig.classList.toggle('is-zoomed'); });
      slot.appendChild(btn);
    }

    async function run() {
      if (busy) { return; }
      busy = true;
      go.disabled = true;
      root.setAttribute('data-state', 'working');
      try {
        var chosen = fileInput.files && fileInput.files[0];
        if (chosen) {
          setStatus('Reading the private key ...', false);
          privateKey = null;
          privateKey = await importPrivateKey(await readKeyFile(chosen));
          fileInput.value = '';              // drop the reference to the file; the key lives only in memory now
        } else if (!privateKey) {
          throw new VaultError('Please choose your private key file first.', 'key');
        }
        clearImages();
        show(forget, true);
        var ok = 0;
        var firstError = '';
        for (var i = 0; i < figures.length; i++) {
          var fig = figures[i];
          fig.setAttribute('data-state', 'loading');
          var slot = fig.querySelector('[data-vault-slot]');
          setStatus('Decrypting ' + (fig.getAttribute('data-label') || 'file') + ' ...', false);
          try {
            var plain = await decryptBlob(await fetchBlob(fig.getAttribute('data-url')), privateKey);
            showImage(fig, plain);
            fig.setAttribute('data-state', 'done');
            ok++;
          } catch (e) {
            var msg = e instanceof VaultError ? e.message : 'The file could not be decrypted (' + (e && e.message ? e.message : 'unknown error') + ').';
            fig.setAttribute('data-state', 'error');
            slot.textContent = '';
            var p = document.createElement('p');
            p.className = 'pf-error-text';
            p.setAttribute('role', 'alert');
            p.textContent = msg;
            slot.appendChild(p);
            if (firstError === '') { firstError = msg; }
            if (e instanceof VaultError && e.kind === 'wrongkey') { privateKey = null; }
          }
        }
        if (ok === figures.length) {
          setStatus('Done. The images exist only in this browser tab. Close the page when you are finished.', false);
          root.setAttribute('data-state', 'done');
        } else {
          setStatus(ok + ' of ' + figures.length + ' file(s) decrypted. ' + firstError, true);
          root.setAttribute('data-state', 'error');
        }
      } catch (e) {
        setStatus(e instanceof VaultError ? e.message : 'Unexpected error: ' + (e && e.message ? e.message : e), true);
        root.setAttribute('data-state', 'error');
        if (e instanceof VaultError && e.kind === 'key') { privateKey = null; }
      } finally {
        busy = false;
        go.disabled = false;
      }
    }

    go.addEventListener('click', run);
    if (forget) {
      forget.addEventListener('click', function () {
        wipe();
        setStatus('The key and all images were removed from this page.', false);
      });
    }
    document.addEventListener('keydown', function (ev) {
      if (ev.key === 'Escape') {
        for (var i = 0; i < figures.length; i++) { figures[i].classList.remove('is-zoomed'); }
      }
    });
    window.addEventListener('pagehide', clearImages);
  }

  /* ----------------------------------------------------------------- boot */

  var gen = document.querySelector('[data-vault-generator]');
  if (gen) { initGenerator(gen); }
  var dec = document.querySelector('[data-vault-decrypt-root]');
  if (dec) { initDecrypt(dec); }
})();
