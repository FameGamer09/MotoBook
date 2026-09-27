(function () {
    'use strict';
    window.MB = window.MB || {};

    // CSRF token
    MB._csrf = function () {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    };

    // 1. Shell / mode switcher
    MB.shell = {
        init: function () {
            document.querySelectorAll('.mode-tab[data-mode]').forEach(function (tab) {
                tab.addEventListener('click', function (e) {
                    var mode = tab.getAttribute('data-mode');
                    var url = tab.getAttribute('data-url');
                    if (url && url !== window.location.pathname + window.location.search) {
                        // Let the browser navigate — target page will render with correct mode class
                        return true;
                    }
                    // If same page, toggle class
                    document.body.classList.remove('app-mode-store', 'app-mode-dispatch');
                    document.body.classList.add(mode === 'store' ? 'app-mode-store' : 'app-mode-dispatch');
                    document.querySelectorAll('.mode-tab').forEach(function (t) { t.classList.remove('active'); });
                    tab.classList.add('active');
                    e.preventDefault();
                });
            });

            // Sidebar toggle (existing)
            var sidebar = document.getElementById('sidebar');
            var toggle = document.getElementById('sidebarToggle');
            if (toggle && sidebar) {
                toggle.addEventListener('click', function () { sidebar.classList.toggle('open'); });
                document.addEventListener('click', function (e) {
                    if (window.innerWidth <= 992 && sidebar.classList.contains('open')
                        && !sidebar.contains(e.target) && !toggle.contains(e.target)) {
                        sidebar.classList.remove('open');
                    }
                });
            }
        }
    };

    // 2. Generic AJAX helper. Returns Promise<{ok, data, flash}>
    MB.ajax = {
        postJSON: function (url, data) {
            var body = new FormData();
            if (data) {
                Object.keys(data).forEach(function (k) { body.append(k, data[k]); });
            }
            body.append('csrf_token', MB._csrf());
            return fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                body: body
            }).then(function (r) {
                var ct = r.headers.get('Content-Type') || '';
                return ct.indexOf('application/json') === 0 ? r.json() : r.text().then(function (t) {
                    try { return JSON.parse(t); } catch (_) { return { ok: r.ok, _raw: t }; }
                });
            }).then(function (out) { MB.flashFromResponse(out); return out; })
              .catch(function (err) { console.error('MB.ajax error:', err); throw err; });
        },
        submitForm: function (formEl) {
            var fd = new FormData(formEl);
            if (!fd.has('csrf_token')) fd.append('csrf_token', MB._csrf());
            return fetch(formEl.getAttribute('action') || window.location.href, {
                method: formEl.getAttribute('method') || 'POST',
                credentials: 'same-origin',
                body: fd
            }).then(function (r) {
                var ct = r.headers.get('Content-Type') || '';
                return ct.indexOf('application/json') === 0 ? r.json() : { ok: r.ok, _redirect: true, _raw: r };
            }).then(function (out) { MB.flashFromResponse(out); return out; });
        }
    };

    MB.flashFromResponse = function (res) {
        if (!res || typeof res !== 'object') return;
        if (res.flash_success) MB.flashToast(res.flash_success, 'success');
        if (res.flash_error) MB.flashToast(res.flash_error, 'error');
    };

    MB.flashToast = function (msg, type) {
        type = type || 'info';
        var el = document.createElement('div');
        el.className = 'toast toast-' + type;
        el.textContent = msg;
        var styles = 'position:fixed;top:88px;right:24px;z-index:500;padding:.75rem 1rem;border-radius:10px;font-weight:600;font-size:.88rem;box-shadow:0 6px 18px rgba(15,23,42,.18);max-width:340px;';
        if (type === 'success') styles += 'background:#d1fae5;color:#065f46;border:1px solid #6ee7b7';
        else if (type === 'error') styles += 'background:#fee2e2;color:#991b1b;border:1px solid #fca5a5';
        else styles += 'background:#cffafe;color:#155e75;border:1px solid #67e8f9';
        el.setAttribute('style', styles);
        document.body.appendChild(el);
        setTimeout(function () { el.style.transition = 'opacity .3s'; el.style.opacity = '0'; }, 3200);
        setTimeout(function () { if (el.parentNode) el.parentNode.removeChild(el); }, 3700);
    };

    // 3. Availability toggle via AJAX
    MB.toggleAvailability = function (itemId, cardEl, opts) {
        opts = opts || {};
        return MB.ajax.postJSON(opts.url || window.location.pathname + '?action=toggle_item', {
            item_id: String(itemId)
        }).then(function (res) {
            if (!res || !res.ok) {
                MB.flashToast(res && res.error || 'Toggle failed', 'error');
                if (opts.fallbackForm) opts.fallbackForm.submit();
                return false;
            }
            // Flip badge
            var badge = cardEl ? cardEl.querySelector('.avail-badge, .badge') : null;
            if (badge) {
                if (res.new_state === 0) {
                    badge.className = 'badge badge-muted avail-badge';
                    badge.textContent = 'OFF';
                } else {
                    badge.className = 'badge badge-success avail-badge';
                    badge.textContent = 'ON';
                }
            }
            var switchInput = cardEl ? cardEl.querySelector('.avail-toggle') : null;
            if (switchInput) switchInput.checked = !!res.new_state;
            if (res.message) MB.flashToast(res.message, 'success');
            return true;
        });
    };

    // 4. Drawer open/close
    MB.drawer = {
        _current: null,
        _lastOverflow: '',
        open: function (drawerId, data) {
            var d = drawerId && typeof drawerId === 'string'
                ? document.getElementById(drawerId)
                : document.getElementById('itemDrawer');
            if (!d) { console.warn('Drawer not found'); return; }
            var overlay = null;
            if (d.parentNode && d.parentNode.classList && d.parentNode.classList.contains('drawer-overlay')) {
                overlay = d.parentNode;
            }
            if (!overlay) {
                overlay = document.querySelector('[data-drawer-overlay="' + (drawerId || 'itemDrawer') + '"]')
                    || (d.previousElementSibling && d.previousElementSibling.classList && d.previousElementSibling.classList.contains('drawer-overlay') ? d.previousElementSibling : null);
            }
            MB.drawer._lastOverflow = document.documentElement.style.overflow || '';
            document.documentElement.style.overflow = 'hidden';
            document.body.style.overflow = 'hidden';
            document.body.classList.add('drawer-open');
            if (overlay) overlay.classList.add('drawer-open');
            MB.drawer._current = d;
            var form = d.querySelector('form, .drawer-body');
            if (form) { try { form.scrollTop = 0; } catch (_) {} }
            var title = d.querySelector('.drawer-title');
            if (data && typeof data === 'object' && data.item && data.item.id) {
                if (title) title.textContent = 'Edit Menu Item';
            } else if (title) {
                title.textContent = 'New Menu Item';
            }
            if (data && typeof data === 'object') {
                data._focusGroups = !!(data._focusGroups);
                if (window.MB._drawerHydrate) window.MB._drawerHydrate(d, data);
            }
            var first = d.querySelector('input,select,textarea,button');
            if (first) setTimeout(function () { try { first.focus(); } catch (_) {} }, 320);
            if (data && typeof data === 'object' && data._focusGroups) {
                var sec = d.querySelector('#sectionGroups');
                setTimeout(function () { if (sec && sec.scrollIntoView) sec.scrollIntoView({ behavior: 'smooth', block: 'start' }); }, 400);
            }
            try { document.body.setAttribute('data-drawer-state', 'open'); } catch (_) {}
        },
        close: function () {
            document.body.classList.remove('drawer-open');
            document.querySelectorAll('[data-drawer-overlay], .drawer-overlay').forEach(function (o) { o.classList.remove('drawer-open'); });
            try { document.body.removeAttribute('data-drawer-state'); } catch (_) {}
            document.documentElement.style.overflow = MB.drawer._lastOverflow || '';
            document.body.style.overflow = '';
            MB.drawer._current = null;
        },
        init: function () {
            var closeBtn = document.querySelectorAll('.drawer-close, [data-drawer-close]');
            closeBtn.forEach(function (b) {
                b.addEventListener('click', function (e) { e.preventDefault(); MB.drawer.close(); });
            });
            var overlays = document.querySelectorAll('.drawer-overlay, [data-drawer-overlay]');
            overlays.forEach(function (overlay) {
                overlay.addEventListener('click', function (e) {
                    if (e.target !== overlay) return;
                    MB.drawer.close();
                });
            });
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && document.body.classList.contains('drawer-open')) MB.drawer.close();
            });
            var attachOnce = {};
            function attachOpen(btn) {
                if (!btn || btn.dataset && btn.dataset._drawerBound === '1') return;
                try { btn.dataset._drawerBound = '1'; } catch (_) {}
                btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    var id = btn.getAttribute('data-drawer-open') || btn.getAttribute('data-drawer') || 'itemDrawer';
                    var itemId = btn.getAttribute('data-item-id') || null;
                    var focusGroups = btn.getAttribute('data-focus-groups') === '1';
                    var payload = { _focusGroups: focusGroups };
                    if (itemId) {
                        payload.item = { id: itemId };
                        var raw = btn.getAttribute('data-item-json');
                        if (raw) { try { payload.item = JSON.parse(raw); } catch (_) { try { payload._rawJsonErr = true; } catch (_) {} } }
                        var editEl = document.getElementById('menuEditData');
                        if (editEl && editEl.textContent) {
                            try {
                                var all = JSON.parse(editEl.textContent);
                                if (all && String(all.id) === String(itemId)) payload.item = all;
                            } catch (_) {}
                        }
                    } else {
                        payload._reset = true;
                    }
                    MB.drawer.open(id, payload);
                });
            }
            function bindAll() {
                document.querySelectorAll('[data-drawer-open], [data-drawer]').forEach(attachOpen);
            }
            bindAll();
            if (!window.MB.__drawerObserver) {
                try {
                    window.MB.__drawerObserver = new MutationObserver(function (muts) {
                        muts.forEach(function (m) { bindAll(); });
                    });
                    window.MB.__drawerObserver.observe(document.body, { childList: true, subtree: true });
                } catch (_) { setInterval(bindAll, 800); }
            }
        }
    };

    // 5. Option builder dynamic group/suboption rows
    MB.optionBuilder = {
        init: function (formEl) {
            formEl = formEl || document.querySelector('#itemDrawer form, form[data-option-builder]');
            if (!formEl) return;
            MB.optionBuilder._wireImageDrop(formEl);
            var addGroupBtn = formEl.querySelector('[data-add-group]');
            if (addGroupBtn) addGroupBtn.addEventListener('click', function (e) {
                e.preventDefault();
                MB.optionBuilder.addGroup(formEl);
                MB.optionBuilder.reindex(formEl);
            });
            formEl.addEventListener('click', function (e) {
                var t = e.target;
                if (t.matches('[data-remove-group]')) {
                    e.preventDefault();
                    var card = t.closest('.option-group-card');
                    if (card && formEl.querySelectorAll('.option-group-card').length > 0) card.parentNode.removeChild(card);
                    MB.optionBuilder.reindex(formEl);
                }
                if (t.matches('[data-add-option]')) {
                    e.preventDefault();
                    var grp = t.closest('.option-group-card');
                    if (!grp) return;
                    MB.optionBuilder.addOptionRow(grp, {});
                    MB.optionBuilder.reindex(formEl);
                }
                if (t.matches('[data-remove-option]')) {
                    e.preventDefault();
                    var row = t.closest('.option-row');
                    if (row) row.parentNode.removeChild(row);
                    MB.optionBuilder.reindex(formEl);
                }
            });
            formEl.addEventListener('change', function (e) {
                var t = e.target;
                if (t.matches('[data-selection-type]') || t.matches('.group-seltype') || t.matches('[data-f="selection_type"]')) {
                    var card = t.closest('.option-group-card');
                    if (!card) return;
                    var isRadio = (t.value || '') === 'radio';
                    var maxInp = card.querySelector('[data-f="max_select"], .group-max, [data-max-select]');
                    var minInp = card.querySelector('[data-f="min_select"], .group-min, [data-min-select]');
                    if (isRadio) {
                        if (maxInp) { maxInp.value = '1'; maxInp.setAttribute('readonly', 'readonly'); }
                        if (minInp) { minInp.value = Math.min(1, parseInt(minInp.value || '1')); minInp.setAttribute('max', '1'); }
                    } else {
                        if (maxInp) { maxInp.removeAttribute('readonly'); maxInp.removeAttribute('max'); }
                        if (minInp) minInp.removeAttribute('max');
                    }
                    MB.optionBuilder.reindex(formEl);
                }
            });
            formEl.addEventListener('submit', function (e) {
                if (!formEl.hasAttribute('data-option-builder')) return;
                var errors = MB.optionBuilder.validate(formEl);
                if (errors.length) {
                    e.preventDefault();
                    errors.forEach(function (msg) { MB.flashToast(msg, 'error'); });
                }
            });
        },
        _wireImageDrop: function (formEl) {
            var drop = formEl.querySelector('#imageDropZone, .image-drop');
            var fileInput = formEl.querySelector('#itemImageInput, input[name="item_image"]');
            var previewWrap = formEl.querySelector('#imageDropPreview, .image-drop-preview');
            var existingHdn = formEl.querySelector('#hfExistingImage, input[name="existing_image_path"]');
            if (!drop || !fileInput) return;
            drop.addEventListener('click', function () { fileInput.click(); });
            drop.addEventListener('dragover', function (e) { e.preventDefault(); drop.style.outline = '2px dashed var(--blue-500)'; });
            drop.addEventListener('dragleave', function () { drop.style.outline = ''; });
            drop.addEventListener('drop', function (e) {
                e.preventDefault();
                drop.style.outline = '';
                if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0]) {
                    fileInput.files = e.dataTransfer.files;
                    MB.optionBuilder._updateImagePreview(fileInput, previewWrap, existingHdn);
                }
            });
            fileInput.addEventListener('change', function () {
                MB.optionBuilder._updateImagePreview(fileInput, previewWrap, existingHdn);
            });
        },
        _updateImagePreview: function (fileInput, previewWrap, existingHdn) {
            if (!previewWrap) return;
            var file = fileInput && fileInput.files && fileInput.files[0];
            if (!file) {
                if (existingHdn && existingHdn.value) {
                    previewWrap.innerHTML = '<img src="' + existingHdn.value + '" style="max-width:100%;max-height:220px;object-fit:cover;border-radius:12px;" alt="Item preview">';
                }
                return;
            }
            if (!/^image\//.test(file.type)) return;
            var reader = new FileReader();
            reader.onload = function () {
                previewWrap.innerHTML = '<img src="' + reader.result + '" style="max-width:100%;max-height:220px;object-fit:cover;border-radius:12px;" alt="Item preview">';
            };
            reader.readAsDataURL(file);
        },
        addGroup: function (formEl, preset) {
            preset = preset || {};
            var container = formEl.querySelector('#optionGroupsContainer, .option-groups-container');
            if (!container) return;
            var idx = container.querySelectorAll('.option-group-card').length;
            var tmpl = MB.optionBuilder._groupTmpl(idx, preset);
            container.insertAdjacentHTML('beforeend', tmpl);
            var cards = container.querySelectorAll('.option-group-card');
            var card = cards.length ? cards[cards.length - 1] : null;
            if (card) {
                var sel = card.querySelector('[data-selection-type], .group-seltype');
                if (sel && sel.value === 'radio') {
                    var maxInp = card.querySelector('[data-f="max_select"], .group-max');
                    if (maxInp) { maxInp.value = '1'; maxInp.setAttribute('readonly', 'readonly'); }
                }
            }
        },
        addOptionRow: function (groupEl, opt) {
            opt = opt || {};
            var list = groupEl.querySelector('.option-list tbody, .option-tbody, .option-table tbody');
            if (!list) return;
            var tr = document.createElement('tr');
            tr.className = 'option-row';
            tr.innerHTML =
                '<td><input type="text" class="form-control opt-name" data-f="option_name" value="' + (opt.option_name ? MB.esc(opt.option_name) : '') + '" placeholder="e.g. Extra Rice" required></td>' +
                '<td><div class="input-prefix">₱<input type="number" step="0.01" min="0" class="form-control opt-delta" data-f="price_delta" value="' + (opt.price_delta || '0.00') + '"></div></td>' +
                '<td style="text-align:center"><label class="switch" style="display:inline-flex"><input type="checkbox" class="opt-avail" data-f="is_available" ' + (opt.is_available !== 0 ? 'checked' : '') + '><span class="slider round"></span></label></td>' +
                '<td><button type="button" class="btn btn-danger-outline btn-xs btn-remove-option" title="Remove option" data-remove-option>' +
                '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>' +
                '</button></td>';
            list.appendChild(tr);
        },
        _groupTmpl: function (idx, g) {
            g = g || { options: [] };
            var selT = g.selection_type || 'radio';
            var min = g.min_select != null ? Number(g.min_select) : 1;
            var max = g.max_select != null ? Number(g.max_select) : 1;
            var req = g.is_required ? 1 : 0;
            var optsRows = '';
            var seed = g.options && g.options.length ? g.options : [{ option_name: '', price_delta: '0.00', is_available: 1 }];
            seed.forEach(function (o) {
                optsRows +=
                    '<tr class="option-row">' +
                    '<td><input type="text" class="form-control opt-name" data-f="option_name" value="' + (o.option_name ? MB.esc(o.option_name) : '') + '" placeholder="e.g. Fries" required></td>' +
                    '<td><div class="input-prefix">₱<input type="number" step="0.01" min="0" class="form-control opt-delta" data-f="price_delta" value="' + (o.price_delta ?? '0.00') + '"></div></td>' +
                    '<td style="text-align:center"><label class="switch" style="display:inline-flex"><input type="checkbox" class="opt-avail" data-f="is_available" ' + (o.is_available !== 0 ? 'checked' : '') + '><span class="slider round"></span></label></td>' +
                    '<td><button type="button" class="btn btn-danger-outline btn-xs" data-remove-option title="Remove option">' +
                    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>' +
                    '</button></td>' +
                    '</tr>';
            });
            return '<div class="option-group-card" data-group-idx="' + idx + '">' +
                '<div class="option-group-head">' +
                '<input type="text" class="form-control group-name" data-f="group_name" value="' + (g.group_name ? MB.esc(g.group_name) : '') + '" placeholder="Group name (e.g. Choose 1 Drink)" style="flex:1" maxlength="100">' +
                '<select class="form-control group-seltype" data-f="selection_type" style="max-width:180px">' +
                '<option value="radio"' + (selT === 'radio' ? ' selected' : '') + '>Single (Radio)</option>' +
                '<option value="checkbox"' + (selT === 'checkbox' ? ' selected' : '') + '>Multi (Checkbox)</option>' +
                '</select>' +
                '<button type="button" class="btn btn-danger-outline btn-xs" data-remove-group title="Remove group">' +
                '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>' +
                '</button>' +
                '</div>' +
                '<div class="option-group-rules">' +
                '<label class="rule-item"><span>Min</span><input type="number" class="form-control group-min" data-f="min_select" min="0" value="' + min + '"></label>' +
                '<label class="rule-item"><span>Max</span><input type="number" class="form-control group-max" data-f="max_select" min="1" value="' + max + '"' + (selT === 'radio' ? ' readonly' : '') + '></label>' +
                '<label class="rule-item switch-row"><span>Required</span><label class="switch"><input type="checkbox" class="group-req" data-f="is_required" ' + (req ? 'checked' : '') + '><span class="slider round"></span></label>' +
                '<small class="text-muted">Customer must select</small></label>' +
                '</div>' +
                '<table class="option-table"><thead><tr><th>Sub-item Name</th><th style="width:140px">+ ₱ Delta</th><th style="width:80px">Active</th><th style="width:40px"></th></tr></thead>' +
                '<tbody class="option-tbody">' + optsRows + '</tbody>' +
                '<tfoot><tr><td colspan="4"><button type="button" class="btn btn-add-option" data-add-option>+ Add Sub-option</button></td></tr></tfoot>' +
                '</table>' +
                '</div>';
        },
        reindex: function (formEl) {
            var container = formEl.querySelector('#optionGroupsContainer, .option-groups-container');
            if (!container) return;
            container.querySelectorAll('.option-group-card').forEach(function (card, i) {
                card.setAttribute('data-group-idx', String(i));
                card.querySelectorAll('input,select').forEach(function (inp) {
                    var f = inp.getAttribute('data-f'); if (!f) return;
                    if (inp.classList.contains('opt-name') || inp.classList.contains('opt-delta') || inp.classList.contains('opt-avail')) {
                        var tr = inp.closest('.option-row');
                        if (!tr) return;
                        var tbody = tr.parentNode;
                        var rows = Array.prototype.slice.call(tbody.children);
                        var j = rows.indexOf(tr);
                        inp.setAttribute('name', 'groups[' + i + '][options][' + j + '][' + f + ']');
                        if (inp.type === 'checkbox' && !inp.checked) {
                            var hidName = 'groups[' + i + '][options][' + j + '][' + f + ']';
                            var hidden = inp.parentNode.querySelector('input[type=hidden][name="' + hidName + '"]');
                            if (!hidden) {
                                var h = document.createElement('input'); h.type = 'hidden'; h.name = hidName; h.value = '0';
                                inp.parentNode.insertBefore(h, inp);
                            }
                        } else if (inp.type === 'checkbox' && inp.checked) {
                            var hidName2 = inp.name;
                            var h2 = inp.parentNode.querySelector('input[type=hidden][name="' + hidName2 + '"]');
                            if (h2) h2.parentNode.removeChild(h2);
                        }
                        return;
                    }
                    inp.setAttribute('name', 'groups[' + i + '][' + f + ']');
                    if (inp.type === 'checkbox' && !inp.checked) {
                        var gName = 'groups[' + i + '][' + f + ']';
                        var h3 = inp.parentNode.querySelector('input[type=hidden][name="' + gName + '"]');
                        if (!h3) { var hh = document.createElement('input'); hh.type = 'hidden'; hh.name = gName; hh.value = '0'; inp.parentNode.insertBefore(hh, inp); }
                    } else if (inp.type === 'checkbox' && inp.checked) {
                        var gName2 = inp.name;
                        var h4 = inp.parentNode.querySelector('input[type=hidden][name="' + gName2 + '"]');
                        if (h4) h4.parentNode.removeChild(h4);
                    }
                });
            });
        },
        hydrate: function (formEl, item) {
            item = item || {};
            var fName = formEl.querySelector('#fItemName, [name="item_name"]');
            if (fName) fName.value = item.item_name || '';
            var fCat = formEl.querySelector('#fCategory, [name="category"]');
            if (fCat) {
                var v = item.category || 'Uncategorized';
                if (fCat.tagName === 'SELECT') {
                    var found = false;
                    for (var i = 0; i < fCat.options.length; i++) {
                        if (String(fCat.options[i].value) === String(v)) { fCat.selectedIndex = i; found = true; break; }
                    }
                    if (!found) {
                        var customInp = formEl.querySelector('#fCategoryCustom, [name="category_custom"]');
                        if (customInp) { customInp.value = v; fCat.value = '__custom__'; }
                        else fCat.value = v;
                    }
                } else {
                    fCat.value = v;
                }
            }
            var fPrice = formEl.querySelector('#fPrice, [name="price"]');
            if (fPrice) fPrice.value = item.price != null ? Number(item.price).toFixed(2) : '0.00';
            var fDesc = formEl.querySelector('#fDescription, [name="description"]');
            if (fDesc) fDesc.value = item.description || '';
            var fAvail = formEl.querySelector('#fIsAvailable, [name="is_available"]');
            if (fAvail) fAvail.checked = item.is_available === 0 ? false : true;
            var hdnId = formEl.querySelector('#hfItemId, [name="item_id"]');
            if (hdnId) hdnId.value = item.id ? String(item.id) : '0';
            var existingImg = formEl.querySelector('#hfExistingImage, input[name="existing_image_path"]');
            var imgPreview = formEl.querySelector('#imageDropPreview, .image-drop-preview');
            var fileInput = formEl.querySelector('#itemImageInput');
            var baseImgSrc = '';
            if (item.image_path) {
                var base = formEl.getAttribute('data-asset-base') || (window.APP_URL ? window.APP_URL + '/../admin/' : '');
                baseImgSrc = base.replace(/\/+$/, '') + '/' + String(item.image_path).replace(/^\/+/, '');
            }
            if (existingImg) existingImg.value = item.image_path || '';
            if (imgPreview) {
                if (baseImgSrc) {
                    imgPreview.innerHTML = '<img src="' + baseImgSrc + '" style="max-width:100%;max-height:220px;object-fit:cover;border-radius:12px;" alt="Item preview">';
                } else {
                    imgPreview.innerHTML = '<span class="image-drop-placeholder"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:32px;height:32px;margin-bottom:.5rem;opacity:.5;"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg><br><small>Drag image here or click to upload</small></span>';
                }
            }
            if (fileInput) fileInput.value = '';
            var container = formEl.querySelector('#optionGroupsContainer, .option-groups-container');
            if (!container) return;
            container.innerHTML = '';
            var groups = item.groups && item.groups.length ? item.groups : (item.groups_json && item.groups_json.length ? item.groups_json : []);
            if (!groups || !groups.length) {
                MB.optionBuilder.addGroup(formEl, {
                    group_name: 'Choice 1: Drink / Side', selection_type: 'radio',
                    min_select: 1, max_select: 1, is_required: 1,
                    options: [{ option_name: '', price_delta: '0.00', is_available: 1 }]
                });
            } else {
                groups.forEach(function (g) { MB.optionBuilder.addGroup(formEl, g); });
            }
            MB.optionBuilder.reindex(formEl);
        },
        validate: function (formEl) {
            var errors = [];
            var name = (formEl.querySelector('#fItemName, [name="item_name"]') || {}).value || '';
            var categorySel = formEl.querySelector('#fCategory, [name="category"]');
            var categoryVal = '';
            if (categorySel) {
                if (categorySel.tagName === 'SELECT' && categorySel.value === '__custom__') {
                    var cust = formEl.querySelector('#fCategoryCustom, [name="category_custom"]');
                    categoryVal = cust ? (cust.value || '').trim() : '';
                    if (categoryVal === '') errors.push('Enter or select a category.');
                } else {
                    categoryVal = (categorySel.value || '').trim();
                }
            }
            if (!categoryVal) errors.push('Category is required.');
            if (!name.trim()) errors.push('Item name is required.');
            var price = Number((formEl.querySelector('#fPrice, [name="price"]') || {}).value || 0);
            if (!(price >= 0)) errors.push('Base price is required and must be >= 0.');
            var container = formEl.querySelector('#optionGroupsContainer, .option-groups-container');
            if (container) {
                container.querySelectorAll('.option-group-card').forEach(function (card, i) {
                    var gn = card.querySelector('[data-f="group_name"], .group-name');
                    var hasName = gn && gn.value && gn.value.trim();
                    var tbody = card.querySelector('.option-tbody, tbody');
                    var rows = tbody ? tbody.querySelectorAll('.option-row') : [];
                    var validOpts = 0;
                    rows.forEach(function (r) {
                        var on = r.querySelector('[data-f="option_name"], .opt-name');
                        if (on && on.value && on.value.trim()) validOpts++;
                    });
                    if (hasName && validOpts === 0) errors.push('Group "' + gn.value.trim() + '" (#' + (i + 1) + ') needs at least one sub-option.');
                    if (!hasName && validOpts > 0) errors.push('Group #' + (i + 1) + ' is missing a group name.');
                });
            }
            return errors;
        }
    };

    MB.esc = function (s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    };

    // Hydrate drawer on open
    window.MB._drawerHydrate = function (d, data) {
        var form = d.querySelector('form[data-option-builder]');
        if (!form) return;
        var reset = !!(data && (data._reset));
        if (reset) {
            MB.optionBuilder.hydrate(form, {});
            return;
        }
        if (!data || !data.item) return;
        if (data.item.id) {
            var url = form.getAttribute('data-fetch-url');
            if (url && !(data.item && data.item.item_name)) {
                MB.ajax.postJSON(url, { item_id: String(data.item.id) }).then(function (res) {
                    if (res && res.item) MB.optionBuilder.hydrate(form, res.item);
                });
            } else {
                MB.optionBuilder.hydrate(form, data.item);
            }
        } else {
            MB.optionBuilder.hydrate(form, {});
        }
    };

    // 6. Order line picker (direct-order notebook)
    MB.orderPicker = {
        init: function (root) {
            root = root || document.querySelector('[data-order-notebook="1"]');
            if (!root) return;
            var addBtn = root.querySelector('[data-add-order-line]');
            if (addBtn) {
                addBtn.addEventListener('click', function (e) {
                    e.preventDefault();
                    MB.orderPicker.addLine(root);
                    MB.orderPicker.reindex(root);
                    MB.orderPicker.recalc(root);
                });
            }
            root.addEventListener('click', function (e) {
                var t = e.target;
                if (t.matches('[data-remove-line]')) {
                    e.preventDefault();
                    var row = t.closest('.order-line-row');
                    if (row) {
                        var siblings = root.querySelectorAll('.order-line-row');
                        if (siblings.length > 1) row.parentNode.removeChild(row);
                    }
                    MB.orderPicker.reindex(root);
                    MB.orderPicker.recalc(root);
                }
                if (t.matches('.qty-btn')) {
                    e.preventDefault();
                    var input = t.parentNode.querySelector('.qty-input');
                    if (!input) return;
                    var d = parseInt(t.getAttribute('data-d') || '0', 10);
                    var nv = Math.max(1, (parseInt(input.value, 10) || 1) + d);
                    input.value = String(nv);
                    MB.orderPicker.recalc(root);
                }
            });
            root.addEventListener('change', function () { MB.orderPicker.recalc(root); });
            root.addEventListener('input', function () { MB.orderPicker.recalc(root); });
        },
        addLine: function (root, preset) {
            preset = preset || {};
            var list = root.querySelector('#orderLinesList, .order-lines');
            if (!list) return;
            var itemSelOpts = root.getAttribute('data-items-json') || '[]';
            var items; try { items = JSON.parse(itemSelOpts); } catch (_) { items = []; }
            var selHtml = '<option value="">Pick an item…</option>';
            var curCat = '';
            items.forEach(function (it) {
                if (it.category !== curCat) { selHtml += '<optgroup label="' + MB.esc(it.category || 'Items') + '">'; curCat = it.category || ''; }
                selHtml += '<option value="' + it.id + '" data-price="' + (it.price || 0) + '" data-groups=\'' + (it.groups_json || '[]') + '\'>' + MB.esc(it.item_name || it.name) + ' (₱' + (it.price || 0) + ')</option>';
            });
            var tr = document.createElement('div');
            tr.className = 'order-line-row';
            tr.style.cssText = 'display:grid;grid-template-columns:2fr 100px 1fr 80px 40px;gap:.5rem;align-items:center;padding:.5rem 0;border-bottom:1px solid var(--gray-100)';
            tr.innerHTML =
                '<select class="form-control line-item" required>' + selHtml + '</select>' +
                '<div class="qty-stepper" style="display:flex;align-items:center;border:1px solid var(--gray-200);border-radius:8px;overflow:hidden">' +
                '<button type="button" class="qty-btn" data-d="-1" style="width:32px;height:32px;background:var(--gray-100);border:none;cursor:pointer;font-weight:700">−</button>' +
                '<input type="number" min="1" class="qty-input" value="1" style="border:none;width:48px;text-align:center;font-weight:700">' +
                '<button type="button" class="qty-btn" data-d="1" style="width:32px;height:32px;background:var(--gray-100);border:none;cursor:pointer;font-weight:700">+</button></div>' +
                '<span class="line-options" style="color:var(--gray-500);font-size:.8rem"></span>' +
                '<span class="line-total" style="font-weight:800;text-align:right">₱0.00</span>' +
                '<button type="button" class="btn btn-danger btn-sm" data-remove-line title="Remove line">×</button>';
            list.appendChild(tr);
        },
        reindex: function (root) {
            root.querySelectorAll('.order-line-row').forEach(function (row, i) {
                row.querySelectorAll('.line-item').forEach(function (sel) { sel.name = 'lines[' + i + '][item_id]'; });
                row.querySelectorAll('.qty-input').forEach(function (inp) { inp.name = 'lines[' + i + '][qty]'; });
                // Add hidden fields for price snapshot
            });
        },
        recalc: function (root) {
            var subtotal = 0;
            root.querySelectorAll('.order-line-row').forEach(function (row) {
                var sel = row.querySelector('.line-item');
                var qtyEl = row.querySelector('.qty-input');
                if (!sel || !qtyEl) return;
                var opt = sel.options[sel.selectedIndex];
                var unitP = opt ? parseFloat(opt.getAttribute('data-price') || '0') : 0;
                var qty = Math.max(1, parseInt(qtyEl.value, 10) || 1);
                // TODO: add selected option deltas
                var line = unitP * qty;
                var totEl = row.querySelector('.line-total');
                if (totEl) totEl.textContent = '₱' + line.toFixed(2);
                subtotal += line;
            });
            var feeEl = root.querySelector('[data-fee]');
            var fee = feeEl ? parseFloat(feeEl.getAttribute('data-fee') || '0') : 0;
            if (feeEl) feeEl.textContent = '₱' + fee.toFixed(2);
            var subEl = root.querySelector('[data-subtotal]');
            if (subEl) subEl.textContent = '₱' + subtotal.toFixed(2);
            var grandEl = root.querySelector('[data-grandtotal]');
            if (grandEl) grandEl.textContent = '₱' + (subtotal + fee).toFixed(2);
        }
    };

    // Bootstrap
    document.addEventListener('DOMContentLoaded', function () {
        MB.shell.init();
        MB.drawer.init();
        var optForm = document.querySelector('form[data-option-builder]');
        if (optForm) MB.optionBuilder.init(optForm);
        MB.orderPicker.init();

        // Attach toggle listeners
        document.querySelectorAll('.avail-toggle[data-item-id]').forEach(function (input) {
            input.addEventListener('change', function () {
                var id = input.getAttribute('data-item-id');
                var card = input.closest('.menu-card, tr');
                MB.toggleAvailability(id, card, {});
            });
        });
    });
})();
