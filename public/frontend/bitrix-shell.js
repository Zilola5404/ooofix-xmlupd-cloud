/**
 * Общая инициализация iframe Bitrix24: заголовок слайдера и fitWindow.
 */
(function (window) {
    'use strict';

    var DEFAULT_TITLE = 'Генерация XML (УПД)';

    function appTitle() {
        return window.OX_CLOUD_APP_TITLE || DEFAULT_TITLE;
    }

    function applyShell(callback) {
        if (typeof window.BX24 === 'undefined' || typeof BX24.init !== 'function') {
            if (typeof callback === 'function') {
                callback();
            }
            return;
        }

        BX24.init(function () {
            if (typeof BX24.setTitle === 'function') {
                BX24.setTitle(appTitle());
            }
            try {
                document.title = appTitle();
            } catch (e) { /* ignore */ }
            if (typeof callback === 'function') {
                callback();
            }
        });
    }

    function fitWindow() {
        if (typeof window.BX24 !== 'undefined' && typeof BX24.fitWindow === 'function') {
            try {
                BX24.fitWindow();
            } catch (e) {
                /* ignore */
            }
        }
    }

    window.OX_CLOUD_SHELL = {
        title: appTitle,
        init: applyShell,
        fitWindow: fitWindow
    };
}(window));
