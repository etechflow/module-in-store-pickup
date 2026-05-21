/**
 * ETechFlow In-Store Pickup — Admin Autofill (v1.1)
 *
 * Adds two convenience buttons to the store-edit form:
 *   1. "Find Address" next to the Postcode field — UK postcode lookup
 *      via getAddress.io (proxied through our admin AJAX endpoint so
 *      the API key never reaches the browser)
 *   2. "Copy from Source" next to the MSI Source Code field — fetches
 *      name + address details from the Magento Inventory source
 *
 * Plus: pre-fills Country to the admin-configured default (GB) on new
 * pickup-store creation.
 *
 * Vanilla JS — no jQuery dependency. Hooks into Magento's UI Component
 * form via DOM polling (the form renders async via Knockout; we wait
 * for the fields to exist, then attach our buttons).
 */
(function () {
    'use strict';

    var config = window.etechflowIspAutofillConfig || {};
    if (!config.postcodeLookupUrl) {
        return;
    }

    // ─── Wait-for-field helper ────────────────────────────────────
    function waitForField(dataScope, callback, timeoutMs) {
        var start = Date.now();
        var timeout = timeoutMs || 8000;
        var poll = setInterval(function () {
            var input = document.querySelector(
                '[name="' + dataScope + '"], input[name="' + dataScope + '"], select[name="' + dataScope + '"]'
            );
            if (input) {
                clearInterval(poll);
                callback(input);
                return;
            }
            if (Date.now() - start > timeout) {
                clearInterval(poll);
            }
        }, 100);
    }

    // ─── Postcode lookup ──────────────────────────────────────────
    function setupPostcodeLookup() {
        if (!config.hasGetAddressKey) {
            return;
        }
        waitForField('postcode', function (postcodeInput) {
            var wrapper = document.createElement('div');
            wrapper.className = 'etechflow-isp-autofill-postcode';
            wrapper.style.cssText = 'display:inline-block; margin-left:8px; vertical-align:middle;';

            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'action-default scalable';
            btn.style.cssText = 'padding:5px 14px; vertical-align:middle;';
            btn.textContent = 'Find Address';

            var dropdown = document.createElement('div');
            dropdown.style.cssText = 'display:none; position:absolute; z-index:1000; background:#fff; border:1px solid #adadad; border-radius:3px; max-height:280px; overflow-y:auto; min-width:340px; box-shadow:0 2px 8px rgba(0,0,0,0.15); margin-top:4px;';

            wrapper.appendChild(btn);
            wrapper.appendChild(dropdown);
            postcodeInput.parentNode.insertBefore(wrapper, postcodeInput.nextSibling);

            btn.addEventListener('click', function () {
                var postcode = postcodeInput.value.trim();
                if (postcode === '') {
                    showDropdownMessage(dropdown, 'Enter a postcode first.');
                    return;
                }
                btn.disabled = true;
                var origText = btn.textContent;
                btn.textContent = 'Looking up…';

                var fd = new FormData();
                fd.append('form_key', config.formKey);
                fd.append('postcode', postcode);
                fetch(config.postcodeLookupUrl, {
                    method: 'POST',
                    body: fd,
                    credentials: 'same-origin',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    btn.disabled = false;
                    btn.textContent = origText;
                    if (!data.ok) {
                        showDropdownMessage(dropdown, data.error || 'Lookup failed');
                        return;
                    }
                    if (!data.addresses || data.addresses.length === 0) {
                        showDropdownMessage(dropdown, data.message || 'No addresses found.');
                        return;
                    }
                    renderAddressDropdown(dropdown, data.addresses);
                })
                .catch(function (err) {
                    btn.disabled = false;
                    btn.textContent = origText;
                    showDropdownMessage(dropdown, 'Network error: ' + err.message);
                });
            });

            // Click outside → close dropdown
            document.addEventListener('click', function (e) {
                if (!wrapper.contains(e.target)) {
                    dropdown.style.display = 'none';
                }
            });
        });
    }

    function showDropdownMessage(dropdown, msg) {
        dropdown.innerHTML = '<div style="padding:10px 14px; color:#777; font-size:0.9em;">' +
            escapeHtml(msg) + '</div>';
        dropdown.style.display = 'block';
    }

    function renderAddressDropdown(dropdown, addresses) {
        dropdown.innerHTML = '';
        addresses.forEach(function (addr) {
            var item = document.createElement('div');
            item.className = 'etechflow-isp-autofill-address-item';
            item.style.cssText = 'padding:8px 14px; cursor:pointer; border-bottom:1px solid #eee; font-size:0.9em;';
            item.textContent = addr.label;
            item.addEventListener('mouseenter', function () { item.style.background = '#f0f6ff'; });
            item.addEventListener('mouseleave', function () { item.style.background = '#fff'; });
            item.addEventListener('click', function () {
                applyAddress(addr);
                dropdown.style.display = 'none';
            });
            dropdown.appendChild(item);
        });
        dropdown.style.display = 'block';
    }

    function applyAddress(addr) {
        // Magento UI Components are Knockout-bound — setting .value alone
        // doesn't propagate. We dispatch a 'change' event to trigger
        // Knockout to sync.
        var streetField = document.querySelector('[name="street"], textarea[name="street"]');
        if (streetField) {
            var combined = addr.line1;
            if (addr.line2) combined += '\n' + addr.line2;
            streetField.value = combined;
            triggerChange(streetField);
        }
        setFieldValue('city', addr.city);
        setFieldValue('region', addr.county);
        setFieldValue('postcode', addr.postcode);
    }

    function setFieldValue(name, value) {
        var f = document.querySelector('[name="' + name + '"]');
        if (f) {
            f.value = value;
            triggerChange(f);
        }
    }

    function triggerChange(el) {
        el.dispatchEvent(new Event('input', { bubbles: true }));
        el.dispatchEvent(new Event('change', { bubbles: true }));
    }

    // ─── MSI Source copy ──────────────────────────────────────────
    function setupMsiSourceCopy() {
        waitForField('msi_source_code', function (msiInput) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'action-default scalable';
            btn.style.cssText = 'padding:5px 14px; margin-left:8px; vertical-align:middle;';
            btn.textContent = 'Copy from Source';
            msiInput.parentNode.insertBefore(btn, msiInput.nextSibling);

            btn.addEventListener('click', function () {
                var sourceCode = msiInput.value.trim();
                if (sourceCode === '') {
                    showFloatingMessage(btn, 'Enter an MSI Source Code first.');
                    return;
                }
                btn.disabled = true;
                var origText = btn.textContent;
                btn.textContent = 'Fetching…';

                var fd = new FormData();
                fd.append('form_key', config.formKey);
                fd.append('source_code', sourceCode);
                fetch(config.msiSourceCopyUrl, {
                    method: 'POST',
                    body: fd,
                    credentials: 'same-origin',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    btn.disabled = false;
                    btn.textContent = origText;
                    if (!data.ok) {
                        showFloatingMessage(btn, data.error || 'Fetch failed.', '#a02818');
                        return;
                    }
                    applyMsiSource(data.source);
                    showFloatingMessage(btn, 'Filled from "' + data.source.name + '"', '#1f7a1f');
                })
                .catch(function (err) {
                    btn.disabled = false;
                    btn.textContent = origText;
                    showFloatingMessage(btn, 'Network error', '#a02818');
                });
            });
        });
    }

    function applyMsiSource(s) {
        if (s.name)       setFieldValue('name', s.name);
        if (s.street1) {
            var streetField = document.querySelector('[name="street"], textarea[name="street"]');
            if (streetField) {
                streetField.value = s.street1 + (s.street2 ? '\n' + s.street2 : '');
                triggerChange(streetField);
            }
        }
        if (s.city)       setFieldValue('city', s.city);
        if (s.region)     setFieldValue('region', s.region);
        if (s.postcode)   setFieldValue('postcode', s.postcode);
        if (s.country_id) setFieldValue('country_code', s.country_id);
        if (s.phone)      setFieldValue('phone', s.phone);
        if (s.email)      setFieldValue('email', s.email);
        if (s.latitude !== undefined && s.latitude !== null) setFieldValue('latitude', s.latitude);
        if (s.longitude !== undefined && s.longitude !== null) setFieldValue('longitude', s.longitude);
    }

    function showFloatingMessage(anchor, msg, colour) {
        colour = colour || '#444';
        var note = document.createElement('span');
        note.style.cssText = 'display:inline-block; margin-left:10px; font-size:0.85em; color:' + colour + '; vertical-align:middle;';
        note.textContent = msg;
        anchor.parentNode.appendChild(note);
        setTimeout(function () { note.remove(); }, 4500);
    }

    // ─── Country default ──────────────────────────────────────────
    function setDefaultCountry() {
        if (!config.defaultCountry) return;
        waitForField('country_code', function (countryField) {
            // Only set if currently empty AND this is a new-store form
            // (not editing — preserve existing value)
            if (countryField.value === '' || countryField.value === null) {
                countryField.value = config.defaultCountry;
                triggerChange(countryField);
            }
        });
    }

    // ─── Utility ──────────────────────────────────────────────────
    function escapeHtml(s) {
        var d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
    }

    // ─── Boot ─────────────────────────────────────────────────────
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
    function boot() {
        setupPostcodeLookup();
        setupMsiSourceCopy();
        setDefaultCountry();
    }
})();
