{{-- First paint: the interface style and the theme, before anything is drawn (no flash of the wrong theme).
     Family contract: cookie `hop_theme` (light|dark, on .hel.ink) -> the old `helink-theme` key -> prefers-color-scheme.
     `window.hopSetTheme(t)` is the one writer; every theme control calls it. --}}
<script nonce="{{ Vite::cspNonce() }}">
    (function () {
        var root = document.documentElement;
        window.hopSetTheme = function (t) {
            var d = /(^|\.)hel\.ink$/.test(location.hostname) ? '; domain=.hel.ink; Secure' : '';
            t = t === 'dark' ? 'dark' : 'light';
            try { document.cookie = 'hop_theme=' + t + '; path=/; max-age=31536000; SameSite=Lax' + d; } catch (e) {}
            try { localStorage.setItem('helink-theme', t); } catch (e) {}
        };
        try {
            var m = document.cookie.match(/(?:^|; )hop_theme=(light|dark)(?:;|$)/);
            var t = m ? m[1] : null;
            if (!t) {
                var old = null;
                try { old = localStorage.getItem('helink-theme'); } catch (e) {}
                if (old === 'light' || old === 'dark') {
                    t = old;
                    window.hopSetTheme(old);
                }
            }
            if (/(?:^|; )hop_ui=clean(?:;|$)/.test(document.cookie)) root.dataset.ui = 'clean';
            if (t === 'dark' || (!t && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                root.classList.add('dark');
            }
        } catch (e) {}
    })();
</script>
