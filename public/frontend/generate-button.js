/**
 * Генерация УПД из placement в карточке CRM — сразу запуск и открытие XML.
 */
(function () {
    'use strict';

    var BUILD = window.OX_GEN_BUILD || 'dev';
    var entityType = window.OX_GEN_ENTITY || (new URLSearchParams(window.location.search).get('entity') || 'deal');
    var handlerUrl = window.OX_GEN_HANDLER_URL || '../handler/button.php';
    var REQUEST_TIMEOUT_MS = 180000;

    function parseEntityId() {
        var placement = (BX24.placement && BX24.placement.info) ? BX24.placement.info() : {};
        var opts = placement.options || {};
        if (opts.ID) {
            return parseInt(opts.ID, 10);
        }
        if (opts.id) {
            return parseInt(opts.id, 10);
        }
        if (opts.ENTITY_ID) {
            return parseInt(opts.ENTITY_ID, 10);
        }
        if (opts.entityId) {
            return parseInt(opts.entityId, 10);
        }
        var match = (placement.placement || '').match(/(\d+)/);
        return match ? parseInt(match[1], 10) : 0;
    }

    function setMessage(text, isError) {
        var el = document.getElementById('msg');
        if (!el) {
            return;
        }
        el.textContent = text;
        el.className = 'ox-gen-msg ' + (isError ? 'ox-gen-msg--error' : 'ox-gen-msg--ok');
    }

    function fitWindow() {
        if (typeof BX24.fitWindow === 'function') {
            BX24.fitWindow();
        }
    }

    function notifyUser(text, isError) {
        try {
            var topWin = window.top || window;
            if (topWin.BX && topWin.BX.UI && topWin.BX.UI.Notification && topWin.BX.UI.Notification.Center) {
                topWin.BX.UI.Notification.Center.notify({
                    content: text,
                    position: 'top-right',
                    autoHideDelay: isError ? 8000 : 4000
                });
            }
        } catch (e) {
            // ignore
        }
    }

    function closePlacement(delayMs) {
        setTimeout(function () {
            if (typeof BX24.closeApplication === 'function') {
                BX24.closeApplication();
            }
        }, delayMs || 0);
    }

    function callMethod(method, params) {
        return new Promise(function (resolve, reject) {
            BX24.callMethod(method, params, function (res) {
                if (res.error()) {
                    reject(new Error(res.error()));
                    return;
                }
                resolve(res.data());
            });
        });
    }

    function findXmlFolderId(children) {
        if (!Array.isArray(children)) {
            return 0;
        }
        for (var i = 0; i < children.length; i++) {
            var item = children[i] || {};
            if (item.TYPE === 'folder' && item.NAME === 'XML') {
                return parseInt(item.ID, 10) || 0;
            }
        }
        return 0;
    }

    function ensureXmlFolder() {
        return callMethod('disk.storage.getforapp', {}).then(function (storage) {
            var storageId = parseInt(storage.ID, 10);
            if (!storageId) {
                throw new Error('Не найдено хранилище Диска приложения');
            }
            return callMethod('disk.storage.getchildren', { id: storageId }).then(function (children) {
                var folderId = findXmlFolderId(children);
                if (folderId > 0) {
                    return folderId;
                }
                return callMethod('disk.storage.addfolder', {
                    id: storageId,
                    data: { NAME: 'XML' }
                }).then(function (created) {
                    return parseInt(created.ID, 10) || 0;
                }).catch(function () {
                    return callMethod('disk.storage.getchildren', { id: storageId }).then(function (retryChildren) {
                        var retryId = findXmlFolderId(retryChildren);
                        if (retryId > 0) {
                            return retryId;
                        }
                        throw new Error('Не удалось создать папку /XML/ на Диске');
                    });
                });
            });
        });
    }

    function attachFileToEntity(data, fileId) {
        var resolvedType = data.EntityType || data.entityType || entityType;
        var resolvedId = parseInt(data.EntityId || data.entityId || 0, 10);
        var docNumber = data.DocNumber || data.docNumber || '';
        var entityTypeId = parseInt(data.EntityTypeId || data.entityTypeId || 0, 10);

        if (resolvedType === 'deal' || resolvedType === 'DEAL') {
            var fields = { UF_UPD_FILE: ['disk_file_' + fileId] };
            if (docNumber) {
                fields.UF_UPD_NUMBER = docNumber;
            }
            return callMethod('crm.deal.update', { id: resolvedId, fields: fields });
        }

        var fileKey = 'ufCrm_UPD_FILE';
        var numberKey = 'ufCrm_UPD_NUMBER';
        var updateFields = {};
        updateFields[fileKey] = ['disk_file_' + fileId];
        if (docNumber) {
            updateFields[numberKey] = docNumber;
        }
        return callMethod('crm.item.update', {
            entityTypeId: entityTypeId,
            id: resolvedId,
            fields: updateFields
        });
    }

    function uploadXmlToDisk(data) {
        var fileName = data.StorageFileName || data.storageFileName || 'UPD.xml';
        var xmlBase64 = data.XmlBase64 || data.xmlBase64 || '';
        if (!xmlBase64) {
            return Promise.reject(new Error('Пустой XML для загрузки на Диск'));
        }

        return ensureXmlFolder().then(function (folderId) {
            return callMethod('disk.folder.uploadfile', {
                id: folderId,
                data: { NAME: fileName },
                fileContent: [fileName, xmlBase64],
                generateUniqueName: true
            });
        }).then(function (uploaded) {
            var fileId = parseInt(uploaded.ID || (uploaded.FILE && uploaded.FILE.ID) || 0, 10);
            if (fileId <= 0) {
                throw new Error('Не удалось загрузить файл на Диск B24');
            }
            return attachFileToEntity(data, fileId).then(function () {
                return {
                    FileId: fileId,
                    fileId: fileId,
                    DownloadUrl: uploaded.DOWNLOAD_URL || '',
                    downloadUrl: uploaded.DOWNLOAD_URL || '',
                    DetailUrl: uploaded.DETAIL_URL || '',
                    detailUrl: uploaded.DETAIL_URL || '',
                    FileName: data.FileName || data.fileName || fileName,
                    fileName: data.FileName || data.fileName || fileName,
                    Success: true,
                    success: true
                };
            });
        });
    }

    function pickFileUrl(data) {
        return data.DownloadUrl || data.downloadUrl || data.FileUrl || data.fileUrl || data.DetailUrl || data.detailUrl || '';
    }

    function openUrl(url) {
        if (!url) {
            return false;
        }
        if (window.OX_CLOUD_API && typeof OX_CLOUD_API.appendAuthQuery === 'function'
            && /\/api\/download\.php/i.test(url)) {
            url = OX_CLOUD_API.appendAuthQuery(url);
        }
        try {
            if (typeof BX24.openPath === 'function' && /\/disk\/file\//i.test(url)) {
                BX24.openPath(url);
                return true;
            }
        } catch (e) {
            // fallback
        }
        var win = window.open(url, '_blank');
        return !!win;
    }

    function openGeneratedFile(data, callback) {
        var directUrl = pickFileUrl(data);
        if (directUrl && openUrl(directUrl)) {
            callback(true);
            return;
        }

        var fileId = parseInt(data.FileId || data.fileId || 0, 10);
        if (fileId > 0 && typeof BX24.callMethod === 'function') {
            BX24.callMethod('disk.file.get', { id: fileId }, function (res) {
                if (res.error()) {
                    callback(false);
                    return;
                }
                var file = res.data() || {};
                var url = file.DOWNLOAD_URL || file.DETAIL_URL || '';
                callback(openUrl(url));
            });
            return;
        }

        callback(false);
    }

    function handleSuccess(data) {
        var fileName = data.FileName || data.fileName || 'УПД.xml';

        openGeneratedFile(data, function (opened) {
            var okText = opened
                ? 'Открыт файл: ' + fileName
                : 'УПД сформирован: ' + fileName;
            setMessage(okText, false);
            notifyUser(okText, false);
            fitWindow();
            closePlacement(opened ? 400 : 1200);
        });
    }

    function postGenerate(entityId) {
        setMessage('Формирование XML…', false);
        fitWindow();

        var payload = {
            entityType: entityType,
            entityId: entityId,
            USER_ID: (BX24.getAuth() || {}).user_id || 0
        };

        var controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
        var timeoutId = controller
            ? window.setTimeout(function () { controller.abort(); }, REQUEST_TIMEOUT_MS)
            : null;

        var fetchFn = window.OX_CLOUD_API && OX_CLOUD_API.apiFetch
            ? OX_CLOUD_API.apiFetch(handlerUrl, { method: 'POST', body: payload })
            : fetch(handlerUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(Object.assign({}, (OX_CLOUD_API && OX_CLOUD_API.authPayload()) || {}, payload)),
                signal: controller ? controller.signal : undefined
            });

        fetchFn
            .then(function (r) {
                return r.text().then(function (text) {
                    var data = null;
                    try {
                        data = text ? JSON.parse(text) : null;
                    } catch (e) {
                        throw new Error('Некорректный ответ сервера: ' + text.slice(0, 200));
                    }
                    if (!r.ok) {
                        throw new Error((data && (data.Message || data.message)) || ('HTTP ' + r.status));
                    }
                    return data;
                });
            })
            .then(function (data) {
                if (timeoutId) {
                    window.clearTimeout(timeoutId);
                }
                if (!data || !(data.Success || data.success)) {
                    var errText = (data && (data.Message || data.message)) || 'Ошибка генерации';
                    setMessage(errText, true);
                    notifyUser(errText, true);
                    fitWindow();
                    return;
                }

                if (data.ClientDiskUpload || data.clientDiskUpload) {
                    setMessage('Сохранение на Диск B24…', false);
                    return uploadXmlToDisk(data).then(function (uploaded) {
                        return Object.assign({}, data, uploaded);
                    });
                }

                return data;
            })
            .then(function (data) {
                if (!data) {
                    return;
                }
                if (data.Success || data.success) {
                    handleSuccess(data);
                }
            })
            .catch(function (e) {
                if (timeoutId) {
                    window.clearTimeout(timeoutId);
                }
                var errText = e.name === 'AbortError'
                    ? 'Превышено время ожидания (' + (REQUEST_TIMEOUT_MS / 1000) + ' с)'
                    : (e.message || 'Сетевая ошибка');
                setMessage(errText + ' [build ' + BUILD + ']', true);
                notifyUser(errText, true);
                fitWindow();
            });
    }

    function boot() {
        setMessage('Подключение к Bitrix24…', false);

        if (typeof BX24 === 'undefined') {
            setMessage('Не загружен BX24 SDK. Проверьте интернет и обновите страницу. [build ' + BUILD + ']', true);
            return;
        }

        BX24.init(function () {
            if (typeof BX24.setTitle === 'function') {
                BX24.setTitle('Сформировать УПД');
            }

            var entityId = parseEntityId();
            if (!entityId) {
                setMessage('Не удалось определить ID сделки в карточке CRM', true);
                fitWindow();
                return;
            }

            postGenerate(entityId);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
