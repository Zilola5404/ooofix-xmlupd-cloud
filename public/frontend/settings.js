/**
 * Настройки облачного приложения — UI как в коробочном модуле ooofix.xmlupd
 */
(function (window, document) {
    'use strict';

    var SKIP_FIELDS = { crm_adapter: true, cloud_rest_webhook: true };
    var PRIMARY_SECTION = 'crm';

    function esc(text) {
        var d = document.createElement('div');
        d.textContent = text == null ? '' : String(text);
        return d.innerHTML;
    }

    function notify(message, isError) {
        if (window.BX24 && typeof BX24.fitWindow === 'function') {
            try { BX24.fitWindow(); } catch (e) { /* ignore */ }
        }
        var root = document.getElementById('settings-root');
        if (!root) {
            window.alert(message);
            return;
        }
        var flash = root.querySelector('.ox-upd-settings__flash');
        if (!flash) {
            flash = document.createElement('div');
            flash.className = 'ui-alert ox-upd-settings__flash';
            root.insertBefore(flash, root.firstChild);
        }
        flash.className = 'ui-alert ox-upd-settings__flash ' + (isError ? 'ui-alert-danger' : 'ui-alert-success');
        flash.innerHTML = '<span class="ui-alert-message">' + esc(message) + '</span>';
        if (!isError) {
            window.setTimeout(function () {
                if (flash.parentNode) {
                    flash.parentNode.removeChild(flash);
                }
            }, 4000);
        }
    }

    function authPayload() {
        if (window.OX_CLOUD_API && typeof OX_CLOUD_API.authPayload === 'function') {
            return OX_CLOUD_API.authPayload();
        }
        var auth = BX24.getAuth() || {};
        return {
            DOMAIN: auth.domain || auth.DOMAIN || '',
            AUTH_ID: auth.access_token || auth.AUTH_ID || '',
            REFRESH_ID: auth.refresh_token || auth.REFRESH_ID || '',
            AUTH_EXPIRES: auth.expires_in || auth.AUTH_EXPIRES || '',
            member_id: auth.member_id || auth.MEMBER_ID || ''
        };
    }

    function appendAuthQuery(url, auth) {
        if (window.OX_CLOUD_API && typeof OX_CLOUD_API.appendAuthQuery === 'function') {
            return OX_CLOUD_API.appendAuthQuery(url, auth);
        }
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
        if (window.OX_CLOUD_API && typeof OX_CLOUD_API.apiFetch === 'function') {
            return OX_CLOUD_API.apiFetch(url, options);
        }
        options = options || {};
        var auth = authPayload();
        var method = options.method || 'GET';
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

    var SECTION_HINTS = {
        seller: 'Реквизиты организации, подписант и адреса для XML.',
        crm: 'Смарт-процесс «Счета», публикация в таймлайн.',
        document: 'Функция документа и режим расчёта сумм.',
        xml: 'Версия формата, XSD и кодировка файла.'
    };

    function bindFieldFocus(root) {
        root.addEventListener('focusin', function (e) {
            var field = e.target.closest('.ox-upd-settings__field');
            if (!field || !root.contains(field)) {
                return;
            }
            root.querySelectorAll('.ox-upd-settings__field--focused').forEach(function (node) {
                if (node !== field) {
                    node.classList.remove('ox-upd-settings__field--focused');
                }
            });
            field.classList.add('ox-upd-settings__field--focused');
        });
        root.addEventListener('focusout', function (e) {
            var field = e.target.closest('.ox-upd-settings__field');
            if (field && !field.contains(e.relatedTarget)) {
                field.classList.remove('ox-upd-settings__field--focused');
            }
        });
    }

    function sortedSections(sections) {
        return Object.keys(sections || {}).map(function (id) {
            return { id: id, title: sections[id].title || id, sort: sections[id].sort || 0 };
        }).sort(function (a, b) { return a.sort - b.sort; });
    }

    function fieldsForSection(fields, sectionId) {
        return Object.keys(fields || {}).filter(function (code) {
            if (SKIP_FIELDS[code]) {
                return false;
            }
            return (fields[code].section || '') === sectionId;
        });
    }

    function updateUserDisplay(root, code, value, label) {
        var display = root.querySelector('[data-display-for="' + code + '"]');
        if (!display) {
            return;
        }
        var textNode = display.querySelector('.ox-upd-settings__crm-chip-text') || display;
        textNode.textContent = label || (value && value !== '0' ? value : '—');
        var clearBtn = root.querySelector('[data-crm-clear="' + code + '"]');
        if (clearBtn) {
            if (value && value !== '0') {
                clearBtn.removeAttribute('hidden');
            } else {
                clearBtn.setAttribute('hidden', 'hidden');
            }
        }
    }

    function openUserPicker(btn, root, code) {
        var input = root.querySelector('[name="' + code + '"]');
        if (!input) {
            return;
        }
        if (typeof BX24.selectUsers === 'function') {
            BX24.selectUsers(function (users) {
                if (!users || !users.length) {
                    return;
                }
                var u = users[0];
                var id = String(u.id || u.ID || '');
                var fio = [u.last_name || u.LAST_NAME, u.name || u.NAME, u.second_name || u.SECOND_NAME]
                    .map(function (part) { return String(part || '').trim(); })
                    .filter(Boolean)
                    .join(' ');
                var name = fio || u.name || u.NAME || ('ID ' + id);
                input.value = id;
                updateUserDisplay(root, code, id, name + ' [' + id + ']');
                if (code === 'signatory_user_id') {
                    var nameInput = root.querySelector('[name="signatory_user_name"]');
                    if (nameInput) {
                        nameInput.value = fio || name.replace(/\s*\[\d+\]\s*$/, '').trim();
                    }
                }
            });
            return;
        }
        var manual = window.prompt('ID пользователя Bitrix24:', input.value || '');
        if (manual !== null) {
            input.value = manual;
            updateUserDisplay(root, code, manual, manual ? 'ID ' + manual : '—');
        }
    }

    function renderSelect(code, field, value) {
        var options = field.options || {};
        var html = '<div class="ui-ctl ui-ctl-dropdown ui-ctl-w100 ox-upd-settings__select">';
        html += '<select class="ui-ctl-element" name="' + esc(code) + '" id="ox-field-' + esc(code) + '">';
        Object.keys(options).forEach(function (optKey) {
            html += '<option value="' + esc(optKey) + '"' + (String(value) === String(optKey) ? ' selected' : '') + '>'
                + esc(options[optKey]) + '</option>';
        });
        html += '</select></div>';
        return html;
    }

    function renderCheckbox(code, field, value) {
        var checked = String(value || field.default || '') === 'Y';
        return '<label class="ox-upd-settings__checkbox">'
            + '<input type="checkbox" class="ox-upd-settings__checkbox-input" name="' + esc(code) + '" value="Y"'
            + (checked ? ' checked' : '') + '>'
            + '<span class="ox-upd-settings__checkbox-box" aria-hidden="true"></span>'
            + '<span class="ox-upd-settings__checkbox-text">' + esc(field.label) + '</span>'
            + '</label>';
    }

    function renderUserField(code, field, value, displayLabel) {
        var display = displayLabel || (value && value !== '0' ? 'ID ' + value : '—');
        var html = '<div class="ox-upd-settings__crm-row" data-signatory-user-block="Y">'
            + '<input type="hidden" name="' + esc(code) + '" value="' + esc(value || '') + '">'
            + '<div class="ox-upd-settings__crm-chip" data-display-for="' + esc(code) + '">'
            + '<span class="ox-upd-settings__crm-chip-text">' + esc(display) + '</span></div>'
            + '<div class="ox-upd-settings__crm-actions">'
            + '<button type="button" class="ui-btn ui-btn-primary ui-btn-xs ox-upd-settings__crm-select ox-upd-settings__btn-primary" data-crm-select="' + esc(code) + '">Изменить</button>'
            + '<button type="button" class="ui-btn ui-btn-link ui-btn-xs ox-upd-settings__crm-clear ox-upd-settings__btn-ghost" data-crm-clear="' + esc(code) + '"'
            + (value && value !== '0' ? '' : ' hidden') + '>Очистить</button>'
            + '</div></div>';
        return html;
    }

    function renderInput(code, field, value) {
        var type = field.type === 'password' ? 'password' : (field.type === 'integer' ? 'number' : 'text');
        var placeholder = field.placeholder ? ' placeholder="' + esc(field.placeholder) + '"' : '';
        var minAttr = field.type === 'integer' ? ' min="0" step="1"' : '';
        var inputVal = value || '';
        if (field.type === 'integer' && (!inputVal || parseInt(inputVal, 10) <= 0)) {
            inputVal = '';
        }
        return '<div class="ui-ctl ui-ctl-textbox ui-ctl-w100">'
            + '<input type="' + type + '" class="ui-ctl-element" name="' + esc(code) + '" id="ox-field-' + esc(code) + '"'
            + ' value="' + esc(inputVal) + '"' + placeholder + minAttr + '></div>';
    }

    function renderField(code, field, value, displayLabels, allOptions) {
        var type = field.type || 'string';
        var wide = type === 'user' || type === 'crm_dynamic_type';
        var html = '<div class="ox-upd-settings__field ox-upd-settings__form-group' + (wide ? ' ox-upd-settings__field--wide' : '') + '" data-field="' + esc(code) + '">';

        if (type !== 'checkbox') {
            html += '<label class="ox-upd-settings__label" for="ox-field-' + esc(code) + '">' + esc(field.label) + '</label>';
        }

        if (type === 'select') {
            html += renderSelect(code, field, value);
        } else if (type === 'checkbox') {
            html += renderCheckbox(code, field, value);
        } else if (type === 'user') {
            html += renderUserField(code, field, value, displayLabels[code]);
        } else {
            html += renderInput(code, field, value);
        }

        if (field.hint) {
            html += '<p class="ox-upd-settings__hint">' + esc(field.hint) + '</p>';
        }
        html += '<p class="ox-upd-settings__field-error" data-error-for="' + esc(code) + '"></p></div>';
        return html;
    }

    function toggleSignatoryBlock(root) {
        var mode = root.querySelector('[name="signatory_mode"]');
        var block = root.querySelector('[data-signatory-user-block]');
        if (!mode || !block) {
            return;
        }
        block.style.display = mode.value === 'settings' ? '' : 'none';
    }

    function collectFormData(form) {
        var data = {};
        Array.prototype.forEach.call(form.elements, function (el) {
            if (!el.name) {
                return;
            }
            if (el.type === 'checkbox') {
                data[el.name] = el.checked ? 'Y' : 'N';
            } else if (el.type !== 'button' && el.type !== 'submit') {
                data[el.name] = el.value;
            }
        });
        return data;
    }

    function bindForm(root, form) {
        form.addEventListener('click', function (e) {
            var selectBtn = e.target.closest('[data-crm-select]');
            if (selectBtn && root.contains(selectBtn)) {
                e.preventDefault();
                openUserPicker(selectBtn, root, selectBtn.getAttribute('data-crm-select'));
                return;
            }
            var clearBtn = e.target.closest('[data-crm-clear]');
            if (clearBtn && root.contains(clearBtn)) {
                e.preventDefault();
                var code = clearBtn.getAttribute('data-crm-clear');
                var input = root.querySelector('[name="' + code + '"]');
                if (input) {
                    input.value = '';
                    updateUserDisplay(root, code, '', '—');
                    if (code === 'signatory_user_id') {
                        var nameInput = root.querySelector('[name="signatory_user_name"]');
                        if (nameInput) {
                            nameInput.value = '';
                        }
                    }
                }
            }
        });

        var mode = root.querySelector('[name="signatory_mode"]');
        if (mode) {
            mode.addEventListener('change', function () { toggleSignatoryBlock(root); });
            toggleSignatoryBlock(root);
        }

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var submit = form.querySelector('.ox-upd-settings__submit');
            if (submit) {
                submit.disabled = true;
            }
            apiFetch('api/settings.php', {
                method: 'POST',
                body: { options: collectFormData(form) }
            })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (res.success) {
                        notify('Настройки сохранены', false);
                    } else {
                        notify(res.message || 'Ошибка сохранения', true);
                    }
                })
                .catch(function () {
                    notify('Ошибка сети при сохранении', true);
                })
                .finally(function () {
                    if (submit) {
                        submit.disabled = false;
                    }
                });
        });
    }

    function buildSettings(data) {
        var mount = document.getElementById('settings-root');
        var options = data.options || {};
        var fields = data.fields || {};
        var sections = sortedSections(data.sections || {});
        var displayLabels = data.display_labels || {};

        var html = '<div class="ox-upd-settings ox-upd-settings--portal ox-upd-settings--sidepanel" id="ox-upd-settings">';
        html += '<form class="ox-upd-settings__form settings-form" id="ox-upd-settings-form">';
        html += '<div class="ox-upd-settings__form-body"><div class="ox-upd-settings__grid">';

        sections.forEach(function (section) {
            if (fieldsForSection(fields, section.id).length === 0) {
                return;
            }
            var cardClass = 'ox-upd-settings__card';
            if (section.id === PRIMARY_SECTION) {
                cardClass += ' ox-upd-settings__card--primary';
            }
            html += '<section class="' + cardClass + '" data-section="' + esc(section.id) + '">';
            html += '<h2 class="ox-upd-settings__card-title">' + esc(section.title) + '</h2>';
            if (SECTION_HINTS[section.id]) {
                html += '<p class="ox-upd-settings__card-desc">' + esc(SECTION_HINTS[section.id]) + '</p>';
            }
            html += '<div class="ox-upd-settings__card-fields">';
            fieldsForSection(fields, section.id).forEach(function (code) {
                html += renderField(code, fields[code], options[code] || fields[code].default || '', displayLabels, options);
            });
            html += '</div></section>';
        });

        html += '</div></div>';
        html += '<div class="ox-upd-settings__form-footer">';
        html += '<div class="ox-upd-settings__form-footer-actions">';
        html += '<button type="submit" class="ui-btn ui-btn-primary ui-btn-md ox-upd-settings__submit">Сохранить</button>';
        html += '</div></div></form></div>';

        mount.innerHTML = html;
        bindForm(mount, document.getElementById('ox-upd-settings-form'));
        bindFieldFocus(mount);
    }

    function ensureMenuTitle() {
        try {
            if (sessionStorage.getItem('ox_cloud_menu_title') === '1') {
                return;
            }
        } catch (e) {
            return;
        }
        apiFetch('api/sync.php?action=menu', { method: 'POST', body: {} })
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

    function refreshPlacements() {
        apiFetch('api/sync.php?action=placements', { method: 'POST', body: {} })
            .catch(function () { /* ignore */ });
    }

    function boot() {
        refreshPlacements();
        var mount = document.getElementById('settings-root');
        if (!mount) {
            return;
        }
        apiFetch('api/settings.php')
            .then(function (r) {
                return r.json().then(function (data) {
                    if (!r.ok && (!data || !data.message)) {
                        throw new Error('HTTP ' + r.status);
                    }
                    return data;
                });
            })
            .then(function (data) {
                if (!data.success) {
                    throw new Error(data.message || 'load failed');
                }
                buildSettings(data);
                ensureMenuTitle();
            })
            .catch(function (err) {
                var msg = (err && err.message) ? err.message : 'Не удалось загрузить настройки';
                mount.innerHTML = '<p class="ox-upd-error">' + esc(msg) + '</p>';
            });
    }

    BX24.init(function () {
        try {
            if (typeof BX24.setTitle === 'function') {
                BX24.setTitle(window.OX_CLOUD_APP_TITLE || 'Генерация XML (УПД)');
            }
            try {
                document.title = window.OX_CLOUD_APP_TITLE || 'Генерация XML (УПД)';
            } catch (e) { /* ignore */ }
            if (!BX24.getAuth() || !BX24.getAuth().access_token) {
                document.getElementById('settings-root').innerHTML =
                    '<p class="ox-upd-error">Откройте приложение из меню Bitrix24</p>';
                return;
            }
            boot();
            if (typeof BX24.fitWindow === 'function') {
                BX24.fitWindow();
            }
        } catch (e) {
            document.getElementById('settings-root').innerHTML =
                '<p class="ox-upd-error">Нет авторизации Bitrix24</p>';
        }
    });
}(window, document));
