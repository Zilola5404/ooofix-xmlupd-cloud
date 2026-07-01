/**
 * Документы и логи — общая загрузка данных через REST API приложения.
 */
(function (window) {
    'use strict';

    function esc(text) {
        var d = document.createElement('div');
        d.textContent = text == null ? '' : String(text);
        return d.innerHTML;
    }

    function withAuth(url) {
        var auth = BX24.getAuth();
        var sep = url.indexOf('?') >= 0 ? '&' : '?';
        return url + sep + 'DOMAIN=' + encodeURIComponent(auth.domain);
    }

    function ensureMenuTitle() {
        if (!window.OX_CLOUD_API || typeof OX_CLOUD_API.apiFetch !== 'function') {
            return;
        }
        try {
            if (sessionStorage.getItem('ox_cloud_menu_title') === '1') {
                return;
            }
        } catch (e) {
            return;
        }
        OX_CLOUD_API.apiFetch('api/sync.php?action=menu', { method: 'POST', body: {} })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (res.success) {
                    try {
                        sessionStorage.setItem('ox_cloud_menu_title', '1');
                    } catch (e) { /* ignore */ }
                }
            })
            .catch(function () { /* ignore */ });
    }

    function initTable(apiPath, tbodySelector, rowRenderer, emptyColspan) {
        var boot = function () {
            try {
                if (!BX24.getAuth() || !BX24.getAuth().access_token) {
                    return;
                }
            } catch (e) {
                return;
            }

            if (typeof BX24.setTitle === 'function') {
                BX24.setTitle(window.OX_CLOUD_APP_TITLE || 'Генерация XML (УПД)');
            }
            try {
                document.title = window.OX_CLOUD_APP_TITLE || 'Генерация XML (УПД)';
            } catch (e) { /* ignore */ }

            ensureMenuTitle();

            fetch(withAuth(apiPath), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    var tbody = document.querySelector(tbodySelector);
                    if (!tbody) {
                        return;
                    }
                    var rows = rowRenderer(data) || [];
                    tbody.innerHTML = '';
                    if (!rows.length) {
                        tbody.innerHTML = '<tr><td colspan="' + emptyColspan + '" class="ox-xml-grid__empty">Нет записей</td></tr>';
                        return;
                    }
                    rows.forEach(function (html) {
                        var tr = document.createElement('tr');
                        tr.innerHTML = html;
                        tbody.appendChild(tr);
                    });
                    if (typeof BX24.fitWindow === 'function') {
                        BX24.fitWindow();
                    }
                })
                .catch(function () {
                    var tbody = document.querySelector(tbodySelector);
                    if (tbody) {
                        tbody.innerHTML = '<tr><td colspan="' + emptyColspan + '" class="ox-xml-grid__empty">Ошибка загрузки</td></tr>';
                    }
                });
        };

        if (window.OX_CLOUD_SHELL && typeof OX_CLOUD_SHELL.init === 'function') {
            OX_CLOUD_SHELL.init(boot);
        } else {
            BX24.init(boot);
        }
    }

    window.OX_CLOUD_PAGES = {
        initDocuments: function () {
            initTable('api/documents.php', '#docs-table tbody', function (data) {
                return (data.documents || []).map(function (row) {
                    return '<td>' + esc(row.ID) + '</td>'
                        + '<td>' + esc(row.ENTITY_TYPE) + '</td>'
                        + '<td>' + esc(row.ENTITY_ID) + '</td>'
                        + '<td>' + esc(row.FILE_NAME) + '</td>'
                        + '<td>' + esc(row.VERSION) + '</td>'
                        + '<td>' + esc(row.CREATED_AT) + '</td>';
                });
            }, 6);
        },
        initLogs: function () {
            initTable('api/logs.php', '#logs-table tbody', function (data) {
                return (data.logs || []).map(function (row) {
                    return '<td>' + esc(row.ID) + '</td>'
                        + '<td>' + esc(row.ENTITY_TYPE) + '</td>'
                        + '<td>' + esc(row.ENTITY_ID) + '</td>'
                        + '<td>' + esc(row.STATUS) + '</td>'
                        + '<td>' + esc(row.MESSAGE || '') + '</td>'
                        + '<td>' + esc(row.CREATED_AT) + '</td>';
                });
            }, 6);
        }
    };
}(window));
