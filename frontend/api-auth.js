/**
 * OAuth-параметры для REST API приложения (DOMAIN, AUTH_ID в query + body).
 */
(function (window) {
    'use strict';

    function authPayload() {
        var auth = (window.BX24 && typeof BX24.getAuth === 'function') ? (BX24.getAuth() || {}) : {};
        return {
            DOMAIN: auth.domain || auth.DOMAIN || '',
            AUTH_ID: auth.access_token || auth.AUTH_ID || '',
            REFRESH_ID: auth.refresh_token || auth.REFRESH_ID || '',
            AUTH_EXPIRES: auth.expires_in || auth.AUTH_EXPIRES || '',
            member_id: auth.member_id || auth.MEMBER_ID || ''
        };
    }

    function apiUrl(path) {
        var base = window.OX_CLOUD_API_BASE || '';
        if (!base || !path || /^https?:\/\//i.test(path)) {
            return path;
        }
        path = String(path).replace(/^\//, '');
        return base.replace(/\/$/, '') + '/' + path;
    }

    function appendAuthQuery(url, auth) {
        auth = auth || authPayload();
        var parts = ['DOMAIN=' + encodeURIComponent(auth.DOMAIN || '')];
        if (auth.AUTH_ID) {
            parts.push('AUTH_ID=' + encodeURIComponent(auth.AUTH_ID));
        }
        if (auth.REFRESH_ID) {
            parts.push('REFRESH_ID=' + encodeURIComponent(auth.REFRESH_ID));
        }
        if (auth.member_id) {
            parts.push('member_id=' + encodeURIComponent(auth.member_id));
        }
        var sep = url.indexOf('?') >= 0 ? '&' : '?';
        return url + sep + parts.join('&');
    }

    function apiFetch(url, options) {
        options = options || {};
        var auth = authPayload();
        var method = options.method || 'GET';
        url = apiUrl(url);
        url = appendAuthQuery(url, auth);

        if (method === 'GET') {
            return fetch(url, { headers: options.headers || {} });
        }

        var body = options.body;
        if (typeof body === 'object' && body !== null) {
            body = JSON.stringify(Object.assign({}, auth, body));
        }
        return fetch(url, {
            method: method,
            headers: Object.assign({ 'Content-Type': 'application/json' }, options.headers || {}),
            body: body
        });
    }

    window.OX_CLOUD_API = {
        authPayload: authPayload,
        apiUrl: apiUrl,
        appendAuthQuery: appendAuthQuery,
        apiFetch: apiFetch
    };
}(window));
