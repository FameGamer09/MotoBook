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
        open: function (drawerId, data) {
            var d = drawerId && typeof drawerId === 'string'
                ? document.getElementById(drawerId)
                : document.getElementById('itemDrawer');
            if (!d) { console.warn('Drawer not found'); return; }
            document.body.classList.add('drawer-open');
            MB.drawer._current = d;
            if (data && typeof data === 'object' && window.MB._drawerHydrate) window.MB._drawerHydrate(d, data);
            var first = d.querySelector('input,select,textarea,button');
            if (first) setTimeout(function () { first.focus(); }, 260);
        },
        close: function () {
            document.body.classList.remove('drawer-open');
            MB.drawer._current = null;
        },
        init: function () {
            var closeBtn = document.querySelectorAll('.drawer-close, [data-drawer-close]');
            closeBtn.forEach(function (b) {
                b.addEventListener('click', function (e) { e.preventDefault(); MB.drawer.close(); });
            });
            var overlay = document.querySelector('.drawer-overlay');
            if (overlay) overlay.addEventListener('click', function () { MB.drawer.close(); });
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && document.body.classList.contains('drawer-open')) MB.drawer.close();
            });
            document.querySelectorAll('[data-drawer-open]').forEach(function (btn) {
                btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    var id = btn.getAttribute('data-drawer-open');
                    var itemId = btn.getAttribute('data-item-id') || null;
                    var payload = {};
                    if (itemId) {
                        payload.item = { id: itemId };
                        // Hydrate via embedded JSON or inline data attrs
                        var raw = btn.getAttribute('data-item-json');
                        if (raw) { try { payload.item = JSON.parse(raw); } catch (_) {} }
                    }
                    MB.drawer.open(id, payload);
                });
            });
        }
    };

    // 5. Option builder dynamic group/suboption rows
    MB.optionBuilder = {
        init: function (formEl) {
            formEl = formEl || document.querySelector('#itemDrawer form, form[data-option-builder]');
            if (!formEl) return;
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
                    if (card && formEl.querySelectorAll('.option-group-card').length > 1) card.parentNode.removeChild(card);
                    else if (card) { MB.flashToast('At least one group is recommended; delete options instead', 'error'); }
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
        },
        addGroup: function (formEl, preset) {
            preset = preset || {};
            var container = formEl.querySelector('#optionGroupsContainer, .option-groups-container');
            if (!container) return;
            var idx = container.querySelectorAll('.option-group-card').length;
            var tmpl = MB.optionBuilder._groupTmpl(idx, preset);
            container.insertAdjacentHTML('beforeend', tmpl);
        },
        addOptionRow: function (groupEl, opt) {
            opt = opt || {};
            var list = groupEl.querySelector('.option-list tbody, .option-list');
            if (!list) return;
            var tr = document.createElement('tr');
            tr.className = 'option-row';
            tr.innerHTML =
                '<td><input type="text" class="form-control opt-name" data-f="option_name" value="' + (opt.option_name ? MB.esc(opt.option_name) : '') + '" placeholder="e.g. Extra Rice" required></td>' +
                '<td><div class="input-prefix">₱<input type="number" step="0.01" min="0" class="form-control opt-delta" data-f="price_delta" value="' + (opt.price_delta || '0.00') + '"></div></td>' +
                '<td style="text-align:center"><label class="switch" style="display:inline-flex"><input type="checkbox" class="opt-avail" data-f="is_available" ' + (opt.is_available !== 0 ? 'checked' : '') + '><span class="slider round"></span></label></td>' +
                '<td><button type="button" class="btn btn-danger btn-sm" data-remove-option title="Remove option">×</button></td>';
            list.appendChild(tr);
        },
        _groupTmpl: function (idx, g) {
            g = g || { options: [] };
            var selT = g.selection_type || 'radio';
            var min = g.min_select || 1;
            var max = g.max_select || 1;
            var req = g.is_required ? 1 : 0;
            var optsRows = '';
            (g.options || [{ option_name: '', price_delta: '0.00', is_available: 1 }]).forEach(function (o) {
                optsRows +=
                    '<tr class="option-row">' +
                    '<td><input type="text" class="form-control opt-name" data-f="option_name" value="' + (o.option_name ? MB.esc(o.option_name) : '') + '" placeholder="e.g. Fries" required></td>' +
                    '<td><div class="input-prefix">₱<input type="number" step="0.01" min="0" class="form-control opt-delta" data-f="price_delta" value="' + (o.price_delta ?? '0.00') + '"></div></td>' +
                    '<td style="text-align:center"><label class="switch" style="display:inline-flex"><input type="checkbox" class="opt-avail" data-f="is_available" ' + (o.is_available !== 0 ? 'checked' : '') + '><span class="slider round"></span></label></td>' +
                    '<td><button type="button" class="btn btn-danger btn-sm" data-remove-option title="Remove option">×</button></td>' +
                    '</tr>';
            });
            return '<div class="option-group-card" data-group-idx="' + idx + '">' +
                '<div class="option-group-head">' +
                '<input type="text" class="form-control group-name" data-f="group_name" value="' + (g.group_name ? MB.esc(g.group_name) : 'Choice ' + (idx + 1) + ': Sides') + '" placeholder="Group name e.g. Choice A: Sides" style="flex:1">' +
                '<select class="form-control group-seltype" data-f="selection_type" style="max-width:160px">' +
                '<option value="radio"' + (selT === 'radio' ? ' selected' : '') + '>Radio (Pick 1)</option>' +
                '<option value="checkbox"' + (selT === 'checkbox' ? ' selected' : '') + '>Checkbox (Multi)</option>' +
                '</select>' +
                '<button type="button" class="btn btn-danger btn-sm" data-remove-group title="Remove group">×</button>' +
                '</div>' +
                '<div class="form-row" style="margin-top:.5rem">' +
                '<div class="form-group" style="max-width:140px"><label>Min</label><input type="number" min="0" class="form-control group-min" data-f="min_select" value="' + min + '"></div>' +
                '<div class="form-group" style="max-width:140px"><label>Max</label><input type="number" min="1" class="form-control group-max" data-f="max_select" value="' + max + '"></div>' +
                '<div class="form-group" style="max-width:180px"><label>Required</label><br><label class="switch" style="display:inline-flex;vertical-align:middle"><input type="checkbox" class="group-req" data-f="is_required" ' + (req ? 'checked' : '') + '><span class="slider round"></span></label> <small class="text-muted" style="vertical-align:middle">Customer must select</small></div>' +
                '</div>' +
                '<table class="option-list" style="width:100%;margin-top:.5rem">' +
                '<thead><tr><th style="width:44%">Sub-item</th><th style="width:26%">+₱ Delta</th><th style="width:14%;text-align:center">Avail</th><th style="width:16%"></th></tr></thead>' +
                '<tbody>' + optsRows + '</tbody></table>' +
                '<button type="button" class="btn btn-outline btn-sm" style="margin-top:.4rem" data-add-option>+ Add Sub-Option</button>' +
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
                            // Add hidden 0 for unchecked
                            var hidName = 'groups[' + i + '][options][' + j + '][' + f + ']';
                            var hidden = inp.parentNode.querySelector('input[type=hidden][name="' + hidName + '"]');
                            if (!hidden) {
                                var h = document.createElement('input'); h.type = 'hidden'; h.name = hidName; h.value = '0';
                                inp.parentNode.insertBefore(h, inp);
                            }
                        } else if (inp.type === 'checkbox' && inp.checked) {
                            // remove 0-hidden if any
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
            // Reset basic fields
            ['item_name', 'price', 'category', 'description'].forEach(function (k) {
                var inp = formEl.querySelector('[name="item[' + k + ']"]');
                if (inp) inp.value = (item && item[k] != null) ? item[k] : '';
            });
            var imgPreview = formEl.querySelector('#drawerImgPreview');
            if (imgPreview) {
                if (item && item.image_path) imgPreview.src = item.image_path;
                else imgPreview.src = '';
            }
            var hdnId = formEl.querySelector('[name="item[id]"]');
            if (hdnId) hdnId.value = (item && item.id) ? item.id : '';
            // Reset groups container
            var container = formEl.querySelector('#optionGroupsContainer, .option-groups-container');
            if (!container) return;
            container.innerHTML = '';
            var groups = item && item.groups && item.groups.length ? item.groups : [];
            if (!groups.length) {
                MB.optionBuilder.addGroup(formEl, {
                    group_name: 'Choice 1: Sides', selection_type: 'radio',
                    min_select: 1, max_select: 1, is_required: 1,
                    options: [{ option_name: '', price_delta: '0.00', is_available: 1 }]
                });
            } else {
                groups.forEach(function (g) { MB.optionBuilder.addGroup(formEl, g); });
            }
            MB.optionBuilder.reindex(formEl);
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
        if (!form || !data || !data.item) return;
        if (data.item.id) {
            // If we only have id, fetch from server via data-item-url attribute on drawer
            var url = form.getAttribute('data-fetch-url');
            if (url) {
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
