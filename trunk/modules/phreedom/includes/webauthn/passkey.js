// +-----------------------------------------------------------------+
// |                          FreeBooks ERP                          |
// +-----------------------------------------------------------------+
// | Copyright(c) 2026 Bill Jones                                    |
// +-----------------------------------------------------------------+
// | This program is free software: you can redistribute it and/or   |
// | modify it under the terms of the GNU General Public License as  |
// | published by the Free Software Foundation, either version 3 of  |
// | the License, or any later version.                              |
// |                                                                 |
// | This program is distributed in the hope that it will be useful, |
// | but WITHOUT ANY WARRANTY; without even the implied warranty of  |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the   |
// | GNU General Public License for more details.                    |
// +-----------------------------------------------------------------+
//  Path: /modules/phreedom/includes/webauthn/passkey.js
//
// Shared browser-side helper for both the login screen (this file) and the
// "My Passkeys" management card (profile page). Talks JSON to the
// pk_login_begin/pk_login_finish actions and the passkey_* ajax endpoints;
// window.PK_STRINGS (set by each page's js_include.php) supplies the
// user-facing text so this file needs no language strings of its own.
(function () {
  'use strict';

  function b64urlToBuf(b64url) {
    var pad = '='.repeat((4 - (b64url.length % 4)) % 4);
    var b64 = (b64url + pad).replace(/-/g, '+').replace(/_/g, '/');
    var bin = atob(b64);
    var buf = new Uint8Array(bin.length);
    for (var i = 0; i < bin.length; i++) buf[i] = bin.charCodeAt(i);
    return buf.buffer;
  }

  function bufToB64url(buf) {
    var bytes = new Uint8Array(buf);
    var bin = '';
    for (var i = 0; i < bytes.byteLength; i++) bin += String.fromCharCode(bytes[i]);
    return btoa(bin).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
  }

  function csrfToken() {
    var meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
  }

  function baseParams(extra) {
    var params = new URLSearchParams();
    params.append('csrf_token', csrfToken());
    var companyEl  = document.querySelector('[name="company"]');
    var languageEl = document.querySelector('[name="language"]');
    if (companyEl)  params.append('company', companyEl.value);
    if (languageEl) params.append('language', languageEl.value);
    for (var key in extra) if (Object.prototype.hasOwnProperty.call(extra, key)) params.append(key, extra[key]);
    return params;
  }

  // Posts to index.php the same way the rest of the app does (module/page/action
  // in the body), so these calls go through the normal CSRF and routing checks.
  // Used for the pre-login ceremony steps (pk_login_begin/pk_login_finish),
  // which run through modules/phreedom/pages/main/pre_process.php.
  function postAction(action, extra) {
    var params = baseParams(extra);
    params.append('module', 'phreedom');
    params.append('page', 'main');
    params.append('action', action);
    return fetch('index.php', { method: 'POST', body: params, credentials: 'same-origin' })
      .then(function (r) { return r.json(); });
  }

  // Posts to one of the authenticated modules/phreedom/ajax/passkey_*.php
  // endpoints, used once a session already exists (registration, rename, delete).
  function postAjax(op, extra) {
    var params = baseParams(extra);
    return fetch('index.php?module=phreedom&page=ajax&op=' + encodeURIComponent(op),
      { method: 'POST', body: params, credentials: 'same-origin' })
      .then(function (r) { return r.json(); });
  }

  function friendlyError(err) {
    if (err && err.name === 'NotAllowedError') return PK_STRINGS.cancelled;
    return PK_STRINGS.failed;
  }

  /**
   * Runs the two-step passkey login ceremony. onStatus(text, isError) is
   * called with progress/result messages; on success the caller is expected
   * to navigate to the returned redirect (this function does that itself).
   */
  function passkeyLogin(onStatus) {
    if (!window.PublicKeyCredential) {
      onStatus(PK_STRINGS.unsupported, true);
      return;
    }
    onStatus(PK_STRINGS.waiting, false);
    postAction('pk_login_begin', {}).then(function (data) {
      if (!data.ok) { onStatus(data.message || PK_STRINGS.failed, true); return; }
      var pk = data.publicKey;
      var options = {
        challenge: b64urlToBuf(pk.challenge),
        timeout: pk.timeout,
        rpId: pk.rpId,
        userVerification: pk.userVerification
      };
      return navigator.credentials.get({ publicKey: options }).then(function (cred) {
        return postAction('pk_login_finish', {
          cred_id: bufToB64url(cred.rawId),
          client_data: bufToB64url(cred.response.clientDataJSON),
          authenticator_data: bufToB64url(cred.response.authenticatorData),
          signature: bufToB64url(cred.response.signature),
          user_handle: cred.response.userHandle ? bufToB64url(cred.response.userHandle) : ''
        });
      });
    }).then(function (data) {
      if (!data) return;
      if (data.ok) {
        onStatus(PK_STRINGS.success, false);
        window.location.href = data.redirect;
      } else {
        onStatus(data.message || PK_STRINGS.failed, true);
      }
    }).catch(function (err) {
      onStatus(friendlyError(err), true);
    });
  }

  /**
   * Runs the two-step passkey registration ceremony for the current
   * (already logged-in) user. onStatus(text, isError) reports progress;
   * onDone(ok) fires once, after which the caller should refresh its list.
   */
  function passkeyRegister(label, onStatus, onDone) {
    if (!window.PublicKeyCredential) {
      onStatus(PK_STRINGS.unsupported, true);
      return;
    }
    onStatus(PK_STRINGS.waiting, false);
    postAjax('passkey_register_begin', {}).then(function (data) {
      if (!data.ok) { onStatus(data.message || PK_STRINGS.failed, true); onDone(false); return; }
      var pk = data.publicKey;
      var options = {
        challenge: b64urlToBuf(pk.challenge),
        rp: pk.rp,
        user: {
          id: b64urlToBuf(pk.user.id),
          name: pk.user.name,
          displayName: pk.user.displayName
        },
        pubKeyCredParams: pk.pubKeyCredParams,
        authenticatorSelection: pk.authenticatorSelection,
        attestation: pk.attestation,
        timeout: pk.timeout,
        excludeCredentials: (pk.excludeCredentials || []).map(function (c) {
          return { id: b64urlToBuf(c.id), type: c.type, transports: c.transports };
        })
      };
      return navigator.credentials.create({ publicKey: options }).then(function (cred) {
        return postAjax('passkey_register_finish', {
          label: label || '',
          client_data: bufToB64url(cred.response.clientDataJSON),
          attestation_object: bufToB64url(cred.response.attestationObject)
        });
      });
    }).then(function (data) {
      if (!data) return;
      if (data.ok) {
        onStatus(PK_STRINGS.success, false);
        onDone(true);
      } else {
        onStatus(data.message || PK_STRINGS.failed, true);
        onDone(false);
      }
    }).catch(function (err) {
      onStatus(friendlyError(err), true);
      onDone(false);
    });
  }

  function initLoginButton() {
    var btn = document.getElementById('pk-login-btn');
    if (!btn) return;
    var msg = document.getElementById('pk-login-msg');
    btn.addEventListener('click', function () {
      passkeyLogin(function (text, isError) {
        if (!msg) return;
        msg.textContent = text;
        msg.style.color = isError ? '#c0392b' : '#555';
      });
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initLoginButton);
  } else {
    initLoginButton();
  }

  // Exposed so the profile page's "Add a passkey" button can reuse the same
  // encode/decode/fetch plumbing for the registration ceremony.
  window.FbPasskey = {
    b64urlToBuf: b64urlToBuf,
    bufToB64url: bufToB64url,
    postAction: postAction,
    postAjax: postAjax,
    passkeyLogin: passkeyLogin,
    passkeyRegister: passkeyRegister
  };
})();
