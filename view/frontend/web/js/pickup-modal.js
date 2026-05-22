/**
 * ETechFlow In-Store Pickup v2.0 — Modal controller.
 *
 * Listens for clicks on any shipping-method radio whose value contains
 * `etechflow_isp`. On click → opens the 3-step modal (location → date
 * → slot) → POSTs to /etechflow_isp/pickup/select → triggers a shipping
 * rate re-collection so the radio label updates to reflect the choice.
 *
 * Plain Alpine.js-free JS (vanilla fetch + DOM) so it works on Hyvä
 * Checkout (Magewire) and Luma (Knockout) and any other frontend that
 * renders Magento standard shipping-method radios.
 *
 * No innerHTML on user data — all rows built via createElement/textContent.
 */
(function () {
    'use strict';

    function init() {
        var modal = document.getElementById('ks-isp-modal');
        if (!modal || modal.dataset.ksIspInited) return;
        modal.dataset.ksIspInited = '1';

        var STORES_URL = modal.dataset.storesUrl;
        var DATES_URL = modal.dataset.datesUrl;
        var SLOTS_URL = modal.dataset.slotsUrl;
        var SELECT_URL = modal.dataset.selectUrl;
        var FORM_KEY = readFormKey();

        var stepLoc = modal.querySelector('.ks-isp-step-loc');
        var stepDate = modal.querySelector('.ks-isp-step-date');
        var stepSlot = modal.querySelector('.ks-isp-step-slot');
        var storesList = modal.querySelector('.ks-isp-stores-list');
        var datesList = modal.querySelector('.ks-isp-dates-list');
        var slotsList = modal.querySelector('.ks-isp-slots-list');
        var statusEl = modal.querySelector('.ks-isp-modal-status');
        var backBtn = modal.querySelector('.ks-isp-back');
        var confirmBtn = modal.querySelector('.ks-isp-confirm');
        var closeBtn = modal.querySelector('.ks-isp-modal-close');

        var state = {
            storeId: null,
            storeName: null,
            date: null,
            dateLabel: null,
            slotIso: null,
            slotLabel: null
        };

        function show(el) { if (el) { el.removeAttribute('hidden'); el.style.display = ''; } }
        function hide(el) { if (el) { el.setAttribute('hidden', ''); el.style.display = 'none'; } }

        function setStatus(text, isError) {
            statusEl.textContent = text || '';
            statusEl.classList.toggle('is-error', !!isError);
        }

        function gotoStep(step) {
            hide(stepLoc); hide(stepDate); hide(stepSlot);
            if (step === 'loc') { show(stepLoc); hide(backBtn); }
            else if (step === 'date') { show(stepDate); show(backBtn); backBtn.dataset.target = 'loc'; }
            else if (step === 'slot') { show(stepSlot); show(backBtn); backBtn.dataset.target = 'date'; }
            updateConfirmEnabled();
        }

        function updateConfirmEnabled() {
            confirmBtn.disabled = !(state.storeId && state.date && state.slotIso);
        }

        function openModal() {
            modal.removeAttribute('hidden');
            modal.style.display = 'flex';
            document.documentElement.style.overflow = 'hidden';
            gotoStep('loc');
            loadStores();
        }

        function closeModal(opts) {
            modal.setAttribute('hidden', '');
            modal.style.display = 'none';
            document.documentElement.style.overflow = '';
            if (opts && opts.unselect) {
                var radio = document.querySelector('input[name="ko_unique_1"][value*="etechflow_isp"]')
                         || document.querySelector('input[type="radio"][value*="etechflow_isp"]');
                if (radio) radio.checked = false;
            }
        }

        // ------------------------------------------------------------
        // Loaders
        // ------------------------------------------------------------
        function loadStores() {
            empty(storesList);
            appendLoading(storesList, 'Loading locations…');
            fetch(STORES_URL, { credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    empty(storesList);
                    var stores = (data && data.stores) || [];
                    if (!stores.length) {
                        var msg = document.createElement('p');
                        msg.className = 'ks-isp-empty';
                        msg.textContent = 'No pickup locations available right now.';
                        storesList.appendChild(msg);
                        return;
                    }
                    stores.forEach(function (s) { storesList.appendChild(buildStoreRow(s)); });
                })
                .catch(function (err) {
                    setStatus('Failed to load locations: ' + err.message, true);
                });
        }

        function buildStoreRow(s) {
            var row = document.createElement('label');
            row.className = 'ks-isp-row ks-isp-row-store';
            var input = document.createElement('input');
            input.type = 'radio';
            input.name = 'ks-isp-store';
            input.value = String(s.id);
            input.addEventListener('change', function () {
                state.storeId = s.id;
                state.storeName = s.name;
                state.date = null; state.dateLabel = null;
                state.slotIso = null; state.slotLabel = null;
                gotoStep('date');
                loadDates(s.id);
            });
            var meta = document.createElement('span');
            meta.className = 'ks-isp-row-meta';
            var name = document.createElement('strong');
            name.textContent = s.name;
            var addr = document.createElement('span');
            addr.className = 'ks-isp-row-addr';
            addr.textContent = [s.street, s.city, s.postcode].filter(Boolean).join(', ');
            meta.appendChild(name);
            meta.appendChild(addr);
            if (s.phone) {
                var phone = document.createElement('span');
                phone.className = 'ks-isp-row-phone';
                phone.textContent = s.phone;
                meta.appendChild(phone);
            }
            row.appendChild(input);
            row.appendChild(meta);
            return row;
        }

        function loadDates(storeId) {
            empty(datesList);
            appendLoading(datesList, 'Loading dates…');
            fetch(DATES_URL + '?store_id=' + encodeURIComponent(storeId), { credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    empty(datesList);
                    var dates = (data && data.dates) || [];
                    if (!dates.length) {
                        var msg = document.createElement('p');
                        msg.className = 'ks-isp-empty';
                        msg.textContent = 'No bookable dates in the next 14 days.';
                        datesList.appendChild(msg);
                        return;
                    }
                    dates.forEach(function (d) { datesList.appendChild(buildDateRow(d)); });
                })
                .catch(function (err) {
                    setStatus('Failed to load dates: ' + err.message, true);
                });
        }

        function buildDateRow(d) {
            var row = document.createElement('label');
            row.className = 'ks-isp-row ks-isp-row-date';
            var input = document.createElement('input');
            input.type = 'radio';
            input.name = 'ks-isp-date';
            input.value = d.iso;
            input.addEventListener('change', function () {
                state.date = d.iso;
                state.dateLabel = d.label;
                state.slotIso = null;
                state.slotLabel = null;
                gotoStep('slot');
                loadSlots(state.storeId, d.iso);
            });
            var meta = document.createElement('span');
            meta.className = 'ks-isp-row-meta';
            var label = document.createElement('strong');
            label.textContent = d.label;
            meta.appendChild(label);
            row.appendChild(input);
            row.appendChild(meta);
            return row;
        }

        function loadSlots(storeId, date) {
            empty(slotsList);
            appendLoading(slotsList, 'Loading times…');
            var u = SLOTS_URL + '?store_id=' + encodeURIComponent(storeId) + '&date=' + encodeURIComponent(date);
            fetch(u, { credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    empty(slotsList);
                    var slots = (data && data.slots) || [];
                    if (!slots.length) {
                        var msg = document.createElement('p');
                        msg.className = 'ks-isp-empty';
                        msg.textContent = 'No time slots available on this date.';
                        slotsList.appendChild(msg);
                        return;
                    }
                    slots.forEach(function (s) { slotsList.appendChild(buildSlotRow(s)); });
                })
                .catch(function (err) {
                    setStatus('Failed to load times: ' + err.message, true);
                });
        }

        function buildSlotRow(s) {
            var row = document.createElement('label');
            row.className = 'ks-isp-row ks-isp-row-slot';
            if (!s.available) row.classList.add('is-full');
            var input = document.createElement('input');
            input.type = 'radio';
            input.name = 'ks-isp-slot';
            input.value = s.iso;
            input.disabled = !s.available;
            input.addEventListener('change', function () {
                state.slotIso = s.iso;
                state.slotLabel = s.start + '–' + s.end;
                updateConfirmEnabled();
            });
            var meta = document.createElement('span');
            meta.className = 'ks-isp-row-meta';
            var label = document.createElement('strong');
            label.textContent = s.start + ' – ' + s.end;
            meta.appendChild(label);
            if (!s.available) {
                var full = document.createElement('span');
                full.className = 'ks-isp-row-full';
                full.textContent = 'Full';
                meta.appendChild(full);
            } else if (typeof s.remaining === 'number' && s.remaining <= 3) {
                var left = document.createElement('span');
                left.className = 'ks-isp-row-left';
                left.textContent = s.remaining + ' left';
                meta.appendChild(left);
            }
            row.appendChild(input);
            row.appendChild(meta);
            return row;
        }

        // ------------------------------------------------------------
        // Confirm + select
        // ------------------------------------------------------------
        confirmBtn.addEventListener('click', function () {
            if (confirmBtn.disabled) return;
            setStatus('Saving your pickup…', false);
            confirmBtn.disabled = true;
            var pickupAt = state.slotIso.replace('T', ' ');
            if (pickupAt.length === 16) pickupAt += ':00';

            var fd = new FormData();
            fd.append('store_id', String(state.storeId));
            fd.append('pickup_at', pickupAt);
            fd.append('form_key', FORM_KEY);
            fetch(SELECT_URL, { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function (r) {
                    return r.json().then(function (j) { return { ok: r.ok, body: j }; });
                })
                .then(function (res) {
                    confirmBtn.disabled = false;
                    if (res.ok && res.body && res.body.ok) {
                        setStatus('', false);
                        notifyCheckoutToRefresh();
                        closeModal();
                    } else {
                        var err = (res.body && res.body.error) || 'unknown';
                        setStatus('Save failed: ' + err, true);
                    }
                })
                .catch(function (err) {
                    confirmBtn.disabled = false;
                    setStatus('Save failed: ' + err.message, true);
                });
        });

        backBtn.addEventListener('click', function () {
            var target = backBtn.dataset.target;
            if (target) gotoStep(target);
        });
        closeBtn.addEventListener('click', function () { closeModal({ unselect: true }); });
        modal.addEventListener('click', function (e) {
            if (e.target === modal) closeModal({ unselect: true });
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !modal.hasAttribute('hidden')) closeModal({ unselect: true });
        });

        // ------------------------------------------------------------
        // Open trigger: any click on an ISP shipping-method radio
        // ------------------------------------------------------------
        document.addEventListener('change', function (e) {
            var t = e.target;
            if (!t || t.type !== 'radio') return;
            var v = (t.value || '').toLowerCase();
            if (v.indexOf('etechflow_isp') === -1) return;
            // Defer one frame so the radio's selected state is committed first.
            setTimeout(openModal, 50);
        }, true);

        // ------------------------------------------------------------
        // Helpers
        // ------------------------------------------------------------
        function empty(el) { while (el.firstChild) el.removeChild(el.firstChild); }
        function appendLoading(el, text) {
            var s = document.createElement('span');
            s.className = 'ks-isp-loading';
            s.textContent = text;
            el.appendChild(s);
        }
        function readFormKey() {
            // Magento sets window.FORM_KEY (admin) but on frontend we use the cookie.
            var m = document.cookie.match(/(?:^|;\s*)form_key=([^;]+)/);
            if (m) return decodeURIComponent(m[1]);
            var inp = document.querySelector('input[name="form_key"]');
            return inp ? inp.value : '';
        }
        function notifyCheckoutToRefresh() {
            // Custom event the checkout can listen on; if nothing listens,
            // a soft reload after 250ms ensures the carrier label refreshes.
            document.dispatchEvent(new CustomEvent('ksIspPickupSelected'));
            setTimeout(function () { location.reload(); }, 250);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
