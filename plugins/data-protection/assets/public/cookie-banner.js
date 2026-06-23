/* Data Protection — cookie consent banner for the PUBLIC site (Ley 21.719).
 * Drop-in: add ONE line to your public template, e.g.
 *   <script src="/plugins/data-protection/assets/public/cookie-banner.js" defer></script>
 * It derives the endpoint from its own src, reads the configured text from the
 * CMS, shows the banner once, stores the choice in a cookie, and logs it.
 */
(function () {
    'use strict';

    var COOKIE = 'dp_cookie_consent';

    function getCookie(name) {
        return document.cookie.split('; ').reduce(function (acc, c) {
            var p = c.split('=');
            return p[0] === name ? decodeURIComponent(p.slice(1).join('=')) : acc;
        }, '');
    }
    function setCookie(name, value, days) {
        var d = new Date();
        d.setTime(d.getTime() + days * 864e5);
        document.cookie = name + '=' + encodeURIComponent(value) + '; expires=' + d.toUTCString() + '; path=/; SameSite=Lax';
    }

    if (getCookie(COOKIE)) { return; } // already decided

    var self = document.currentScript || (function () { var s = document.getElementsByTagName('script'); return s[s.length - 1]; })();
    var base = self.src.replace(/assets\/public\/cookie-banner\.js.*$/, '');
    var endpoint = base + 'public.php';

    function post(params) {
        var body = Object.keys(params).map(function (k) { return encodeURIComponent(k) + '=' + encodeURIComponent(params[k]); }).join('&');
        try {
            fetch(endpoint + '?action=record', {
                method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body, credentials: 'same-origin'
            });
        } catch (e) {}
    }

    function decide(choice, text) {
        setCookie(COOKIE, choice, 365);
        post({ purpose: 'cookies', status: choice === 'accepted' ? 'granted' : 'withdrawn', channel: 'cookie_banner', source: location.pathname, evidence: text });
        var el = document.getElementById('dp-cookie-banner');
        if (el) { el.parentNode.removeChild(el); }
    }

    function render(cfg) {
        if (!cfg || !cfg.enabled || cfg.text === '') { return; }
        var wrap = document.createElement('div');
        wrap.id = 'dp-cookie-banner';
        wrap.setAttribute('role', 'dialog');
        wrap.setAttribute('aria-label', 'Aviso de cookies');

        var msg = document.createElement('div');
        msg.className = 'dp-cb-text';
        msg.textContent = cfg.text + ' ';
        if (cfg.policy) {
            var a = document.createElement('a');
            a.href = cfg.policy; a.textContent = 'Política de privacidad'; a.target = '_blank'; a.rel = 'noopener';
            msg.appendChild(a);
        }

        var btns = document.createElement('div');
        btns.className = 'dp-cb-btns';
        var reject = document.createElement('button');
        reject.className = 'dp-cb-reject'; reject.type = 'button'; reject.textContent = cfg.reject || 'Rechazar';
        reject.addEventListener('click', function () { decide('rejected', cfg.text); });
        var accept = document.createElement('button');
        accept.className = 'dp-cb-accept'; accept.type = 'button'; accept.textContent = cfg.accept || 'Aceptar';
        accept.addEventListener('click', function () { decide('accepted', cfg.text); });
        btns.appendChild(reject); btns.appendChild(accept);

        wrap.appendChild(msg); wrap.appendChild(btns);

        // styles (self-contained, so the host page needs no extra CSS file)
        if (!document.getElementById('dp-cb-style')) {
            var st = document.createElement('style');
            st.id = 'dp-cb-style';
            st.textContent = '#dp-cookie-banner{position:fixed;left:1rem;right:1rem;bottom:1rem;z-index:99999;background:#1f2430;color:#fff;'
                + 'border-radius:12px;padding:1rem 1.25rem;display:flex;flex-wrap:wrap;gap:.75rem 1.5rem;align-items:center;justify-content:space-between;'
                + 'box-shadow:0 10px 30px rgba(0,0,0,.3);font-family:system-ui,sans-serif;font-size:.9rem;max-width:1100px;margin:0 auto;}'
                + '#dp-cookie-banner .dp-cb-text{flex:1 1 320px;line-height:1.4}'
                + '#dp-cookie-banner a{color:#9ec1ff;text-decoration:underline}'
                + '#dp-cookie-banner .dp-cb-btns{display:flex;gap:.5rem;flex:0 0 auto}'
                + '#dp-cookie-banner button{border:0;border-radius:8px;padding:.5rem 1.1rem;font-weight:600;cursor:pointer;font-size:.9rem}'
                + '#dp-cookie-banner .dp-cb-reject{background:#3a4150;color:#fff}'
                + '#dp-cookie-banner .dp-cb-accept{background:#3b82f6;color:#fff}';
            document.head.appendChild(st);
        }
        document.body.appendChild(wrap);
    }

    function init() {
        try {
            fetch(endpoint + '?action=settings', { credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(render)
                .catch(function () {});
        } catch (e) {}
    }

    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); }
    else { init(); }
})();
