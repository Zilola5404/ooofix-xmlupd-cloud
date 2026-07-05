/**
 * Документы и логи — загрузка данных через REST API приложения.
 */
(function (window) {
    'use strict';

    function esc(text) {
        var d = document.createElement('div');
        d.textContent = text == null ? '' : String(text);
        return d.innerHTML;
    }

    function field(row, upper, lower) {
        if (row && row[upper] != null && row[upper] !== '') {
            return row[upper];
        }
        if (row && row[lower] != null && row[lower] !== '') {
            return row[lower];
        }
        return '';
    }

    function fetchApi(url) {
        if (window.OX_CLOUD_API && typeof OX_CLOUD_API.apiFetch === 'function') {
            return OX_CLOUD_API.apiFetch(url);
        }

        var auth = BX24.getAuth() || {};
        var sep = url.indexOf('?') >= 0 ? '&' : '?';
        url += sep + 'DOMAIN=' + encodeURIComponent(auth.domain || '');
        if (auth.access_token) {
            url += '&AUTH_ID=' + encodeURIComponent(auth.access_token);
        }
        if (auth.refresh_token) {
            url += '&REFRESH_ID=' + encodeURIComponent(auth.refresh_token);
        }
        if (auth.member_id) {
            url += '&member_id=' + encodeURIComponent(auth.member_id);
        }

        return fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    }

    function openCrmPath(path) {
        if (!path) {
            return;
        }
        if (typeof BX24 !== 'undefined' && typeof BX24.openPath === 'function') {
            BX24.openPath(path);
            return;
        }
        window.open(path, '_blank');
    }

    function openFileUrl(url, fileId) {
        fileId = parseInt(fileId, 10) || 0;

        if (url) {
            try {
                if (typeof BX24 !== 'undefined' && typeof BX24.openPath === 'function') {
                    if (url.charAt(0) === '/') {
                        BX24.openPath(url);
                        return;
                    }
                    if (/\/disk\/file\//i.test(url)) {
                        var pathMatch = url.match(/(\/disk\/file\/[^?#]+)/i);
                        if (pathMatch && pathMatch[1]) {
                            BX24.openPath(pathMatch[1]);
                            return;
                        }
                    }
                }
            } catch (e) {
                // fallback ниже
            }
            window.open(url, '_blank');
            return;
        }

        if (fileId > 0 && typeof BX24 !== 'undefined' && typeof BX24.callMethod === 'function') {
            BX24.callMethod('disk.file.get', { id: fileId }, function (res) {
                if (res.error()) {
                    return;
                }
                var file = res.data() || {};
                openFileUrl(file.DETAIL_URL || file.DOWNLOAD_URL || '', 0);
            });
        }
    }

    function crmLink(path, label) {
        label = label == null ? '' : String(label);
        if (!path || label === '') {
            return esc(label);
        }

        return '<a href="#" class="ox-xml-grid__link" data-crm-path="' + esc(path) + '">' + esc(label) + '</a>';
    }

    function fileLink(url, fileId, label) {
        label = label == null ? '' : String(label);
        fileId = parseInt(fileId, 10) || 0;
        if (label === '') {
            return '';
        }
        if (!url && fileId <= 0) {
            return esc(label);
        }

        return '<a href="#" class="ox-xml-grid__link" data-file-url="' + esc(url) + '" data-file-id="' + esc(fileId) + '">' + esc(label) + '</a>';
    }

    function bindGridLinks(root) {
        if (!root) {
            return;
        }

        root.querySelectorAll('[data-crm-path]').forEach(function (link) {
            link.addEventListener('click', function (event) {
                event.preventDefault();
                openCrmPath(link.getAttribute('data-crm-path') || '');
            });
        });

        root.querySelectorAll('[data-file-url]').forEach(function (link) {
            link.addEventListener('click', function (event) {
                event.preventDefault();
                openFileUrl(
                    link.getAttribute('data-file-url') || '',
                    link.getAttribute('data-file-id') || 0
                );
            });
        });
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

            fetchApi(apiPath)
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
                    bindGridLinks(tbody);
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
                    var docId = field(row, 'ID', 'id');
                    var entityId = field(row, 'ENTITY_ID', 'entity_id');
                    var crmPath = field(row, 'CRM_PATH', 'crm_path');
                    var fileName = field(row, 'FILE_NAME', 'file_name');
                    var fileUrl = field(row, 'FILE_URL', 'file_url');
                    var fileId = field(row, 'FILE_ID', 'file_id');
                    var createdAt = field(row, 'CREATED_AT', 'created_at');

                    return '<td>' + esc(fileId || docId) + '</td>'
                        + '<td>' + esc(field(row, 'ENTITY_TYPE', 'entity_type')) + '</td>'
                        + '<td>' + crmLink(crmPath, entityId) + '</td>'
                        + '<td>' + fileLink(fileUrl, fileId, fileName) + '</td>'
                        + '<td>' + esc(field(row, 'VERSION', 'version')) + '</td>'
                        + '<td>' + esc(createdAt) + '</td>';
                });
            }, 6);
        },
        initLogs: function () {
            initTable('api/logs.php', '#logs-table tbody', function (data) {
                return (data.logs || []).map(function (row) {
                    var logId = field(row, 'ID', 'id');
                    var entityId = field(row, 'ENTITY_ID', 'entity_id');
                    var crmPath = field(row, 'CRM_PATH', 'crm_path');
                    var statusLabel = field(row, 'STATUS_LABEL', 'status_label')
                        || field(row, 'STATUS', 'status');
                    var createdAt = field(row, 'CREATED_AT', 'created_at');

                    return '<td>' + esc(logId) + '</td>'
                        + '<td>' + esc(field(row, 'ENTITY_TYPE', 'entity_type')) + '</td>'
                        + '<td>' + crmLink(crmPath, entityId) + '</td>'
                        + '<td>' + esc(statusLabel) + '</td>'
                        + '<td>' + esc(field(row, 'MESSAGE', 'message')) + '</td>'
                        + '<td>' + esc(createdAt) + '</td>';
                });
            }, 6);
        }
    };
}(window));
