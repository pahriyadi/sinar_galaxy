/**
 * Universal Page Preloader (Glassmorphism Paper White)
 * Sinar Galaxy Travel — v3.0
 */
(function () {
    'use strict';

    function getPreloader() {
        return document.getElementById('page-preloader');
    }

    /** Sembunyikan preloader dengan animasi fade out */
    function hidePreloader() {
        var preloader = getPreloader();
        if (!preloader) return;
        preloader.classList.add('fade-out');
        setTimeout(function () {
            preloader.style.display = 'none';
        }, 300);
    }

    /** Tampilkan preloader saat navigasi atau submit */
    function showPreloader() {
        var preloader = getPreloader();
        if (preloader) {
            preloader.classList.remove('fade-out');
            preloader.style.display = 'flex';
            preloader.offsetHeight; // trigger reflow
        }
    }

    // Sembunyikan saat halaman selesai load
    if (document.readyState === 'complete') {
        hidePreloader();
    } else {
        window.addEventListener('load', hidePreloader);
    }

    // Failsafe: paksa sembunyi setelah 4 detik agar user tidak stuck
    setTimeout(hidePreloader, 4000);

    // BFCache (Back/Forward Cache browser)
    window.addEventListener('pageshow', function (e) {
        if (e.persisted) {
            hidePreloader();
        }
    });

    // Tampilkan preloader saat form submit (valid)
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!form) return;
        if (e.defaultPrevented) return;
        if (form.target === '_blank') return;
        if (form.classList.contains('no-preloader')) return;
        if (typeof form.checkValidity === 'function' && !form.checkValidity()) return;

        showPreloader();
    });

    // Tampilkan preloader saat klik link navigasi internal
    document.addEventListener('click', function (e) {
        var link = e.target.closest('a');
        if (!link) return;

        var href = link.getAttribute('href');
        var target = link.getAttribute('target');

        if (!href || href.trim() === '' ||
            href.startsWith('#') ||
            href.startsWith('javascript:') ||
            target === '_blank' ||
            link.hasAttribute('download') ||
            link.classList.contains('no-preloader') ||
            link.classList.contains('dropdown-toggle') ||
            link.hasAttribute('data-widget') ||
            link.hasAttribute('data-toggle') ||
            link.hasAttribute('data-dismiss') ||
            link.closest('.dt-buttons') ||
            link.closest('.paginate_button')
        ) {
            return;
        }

        showPreloader();
    });

    // API publik
    window.SGTPreloader = {
        show: showPreloader,
        hide: hidePreloader
    };
})();
