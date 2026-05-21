# Changelog — ETechFlow In-Store Pickup

All notable changes to this module. Adheres to [Semantic Versioning](https://semver.org/).

---

## [1.1.11] — 2026-05-22 — Pickup Window Overrides + Exception Days dynamicRows wrap/unwrap

Two `<dynamicRows>` fieldsets on the Store edit form ("Pickup Window Overrides" and "Exception Days") had been broken in opposite directions: new rows appeared to save (success toast) but were silently dropped, and saved rows existed in DB but rendered blank on reload. Both stem from the same Magento UI Component contract, only half-implemented.

### Fixed

- **New `window_overrides` / `exceptions` rows silently skipped on save.** POST payload from `dynamicRows` arrives nested under the component's dataScope: `{"window_overrides": {"window_overrides": [...rows]}}`. The Save controller iterated the outer dict as the row list, got the inner array as a "row", `$row['window_id']` / `$row['exception_date']` was undefined → 0 → the no-op skip in `WindowOverrideManager::replaceRows()` / `ExceptionManager::replaceRows()` silently dropped every row. **Fix:** `Store\Save::unwrapDynamicRows($data, $key)` strips one level of dataScope nesting before passing to the manager. If `$data[<key>]` exists and is an array, returns it; otherwise returns `$data` as-is. Used for both `exceptions` and `window_overrides`.

- **Saved `window_overrides` / `exceptions` rows invisible on reload.** The DataProvider returned rows as a flat array under their dataScope. The `dynamicRows` component's record-template adapter binds existing rows via a wrapped shape AND requires each row to have a unique `record_id` — without either, it sees data it doesn't recognise and renders zero rows even though the DB has them. **Fix:** `Ui\Component\Form\DataProvider::prepareDynamicRows($rows, $boolField)` reindexes the rows, assigns `record_id = $i` per row, and casts the bool column to int 0/1 (matches the select valueMap). Output is then wrapped: `$row['<scope>'] = ['<scope>' => $rows]`. Now the DataProvider-emit shape and Save-receive shape are reciprocal.

### Unchanged

- DB schema and the no-op skip logic in `WindowOverrideManager` / `ExceptionManager` (the skip was correct — it just wasn't seeing real rows). v1.1.10's amenity/tag multiselect string-cast and Open/Closed source model both still apply.

### Migration

```
composer update etechflow/module-in-store-pickup
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush
docker exec <php-fpm-container> kill -USR2 1   # restart php-fpm — clears OPcache
```

No schema changes. No data migration. Pure controller + DataProvider layer.

---

## [1.1.10] — 2026-05-22 — Open/Closed labels + amenity/tag multiselect highlight fix

Two related "saved but doesn't look saved" admin-form polish fixes, both rooted in Magento UI Component strict-equality semantics.

### Changed

- **`is_closed` dropdowns now show "Open / Closed" instead of "Yes / No".** v1.1.8 replaced the broken toggle sliders with Yes/No selects — that landed but Keystation flagged a UX issue: a "Yes / No" dropdown above the weekday rows reads ambiguously ("Yes" parses as a confirmation, not as "yes, closed"). Especially in the exception_days dynamicRows where rows have no row-level label. Introduced `ETechFlow\InStorePickup\Model\Source\OpenClosed` returning the same int 0/1 the column has always stored (value=0 → "Open", value=1 → "Closed"). Wired into the 9 controls that hold `is_closed`: 7 weekday selects + exception_days select in the Store form, the holiday Closed-All-Day select + listing column. Other Yes/No fields (is_active on store/amenity/tag, is_disabled on window_overrides, is_recurring on holiday) untouched — Yes/No is correct there. **DB column, save handler, imports/visibility links all unchanged — labels-only.**

### Fixed

- **Amenity / Tag multiselect: saved IDs not highlighted on reload.** Saving correctly persisted N amenity IDs, but reopening the Store form showed zero selections highlighted. Same strict-equality category as the v1.1.7 single-checkbox bug — opposite direction:
  - `AmenityOptions` / `TagOptions` returned `value=(int)$id`.
  - `DataProvider` returned the saved-IDs array as `int[]` (`AssignmentManager::getAssigned()` casts via `intval`).
  - But the multiselect compares the selected-IDs array against option values with `===`, and POST submits IDs as strings.
  - `int 1 !== string "1"` → nothing pre-selected on reload.
  - **Fix:** cast to string on BOTH sides. `AmenityOptions::toOptionArray()` and `TagOptions::toOptionArray()` now emit `value=(string)$id`; `Ui/Component/Form/DataProvider::getData()` wraps both `assigned_amenity_ids` / `assigned_tag_ids` with `array_map('strval', …)`. Docblocks on both Options classes flag the strict-equality reason so the cast doesn't get "cleaned up" later.

### Migration

```
composer update etechflow/module-in-store-pickup
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush
docker exec <php-fpm-container> kill -USR2 1   # or restart php-fpm — clears OPcache
```

No schema changes. No data migration. Pure UI / DataProvider layer.

---

## [1.0.1] — 2026-05-20 — Install-on-Hyvä hotfix

Two real bugs surfaced during the first install on a Hyvä client store. Both fixed.

### Fixed

- **`ShippingAddressAutofillPlugin::afterSetShippingMethod` strict return type.** The method signature used `: Address` strict return type and unconditionally returned `$result`. If any third-party plugin earlier in the plugin chain returned `null` / `false` / a non-`Address` value (legal under Magento's plugin contract), our plugin would emit a `TypeError` *mid-response*. Because the fatal happened after output buffering started, nginx saw a 200 status with malformed chunked output — and `var/log/exception.log` stayed empty because a TypeError isn't an Exception. Symptom: admin/category pages randomly broken with "Backend fetch failed" while no error appeared in logs.
  - **Fix:** removed the strict return type. Added an `instanceof Address` guard before our autofill logic runs. Non-`Address` `$result` values now pass through unchanged.
- **`magewirephp/magewire` was in `suggest` instead of `require`.** The `Magewire/Checkout/StorePicker.php` class extends `\Magewirephp\Magewire\Component`. On a store without Magewire installed (Hyvä Theme without Hyvä Checkout, or a clean Open Source install), `bin/magento setup:di:compile` would attempt to compile the class against the missing parent and either fail or produce broken interceptors in `generated/code/`. The CHANGELOG entry for v1.0.0 incorrectly claimed the file would "sit inert on disk" — di:compile scans every class regardless.
  - **Fix:** moved `magewirephp/magewire: ^1.0||^2.0` into `require`. Composer will now install it transitively (it's a tiny package — single PHP file plus a few helper classes — and it's the parent of every Hyvä Checkout component anyway).

### Migration

```
composer update etechflow/module-in-store-pickup
bin/magento setup:upgrade
bin/magento setup:di:compile
docker exec <php-fpm-container> kill -USR2 1   # or restart php-fpm — clears OPcache
```

If you're upgrading from v1.0.0 on a host with `opcache.validate_timestamps=0`, you MUST restart php-fpm after `setup:di:compile` — otherwise stale compiled interceptors stay in memory and you see the same "two workers returning different output sizes" symptom that masked Bug 1.

---

## [1.0.0] — 2026-05-20 — Click & Collect for Magento 2

First commercial release. Click & Collect / in-store pickup module with the differentiators competing modules can't ship: **auto-fill shipping address from the picked store** (kills the wrong-tax bug 8+ competitors all have) and a **standalone-first architecture** that gets richer when paired with the rest of the eTechFlow suite.

### The differentiator competitors can't match

Every existing Magento 2 C&C module (Amasty, MageWorx, WebKul, Magenest, Wyomind, Fooman, MageDelight, Mageants, Setubridge, FME, Meetanshi…) bolts onto Magento's shipping system, which requires a shipping address. So they all force the customer to type one when picking C&C. Customers type their home address, Magento charges tax based on it, the merchant has to manually fix every order.

**ETechFlow_InStorePickup solves this**: when the customer picks a pickup method, our plugin overwrites the shipping address with the store's address — silently. Tax calculation is then correct out of the box. No manual cleanup.

### Added

**Foundation**
- `registration.php`, `composer.json` (proprietary licence, soft-dependencies on NDE / DD / BED / Hyvä Checkout)
- `etc/module.xml` setup_version `1.0.0`
- **DB schema** (`etc/db_schema.xml`) — 11 tables: stores, hours, exceptions, holidays, store_holiday_exclusion, amenities, store_amenity, tags, store_tag, pickup_windows, store_pickup_windows. All FKs CASCADE on store delete. Indexes on hot-path columns.
- **Admin config** (`etc/adminhtml/system.xml`) — License section + General Settings + eTechFlow Suite Integrations + Notifications, all with plain-English tooltips.

**Licensing + Module Infrastructure**
- `Model/LicenseValidator` — per-domain HMAC + bundle key. `MODULE_ID = in-store-pickup`. Shares BUNDLE_SECRET with every eTechFlow module.
- `Model/Config` — license-aware `isEnabled()`. Soft-detection of optional integrations (NDE, DD, BED) via `class_exists`.
- `Model/Performance/Profiler` — Tideways span helper, tags `ETechFlow_ISP_*`.

**Entities + Service Contracts**
- Full Api/Data + Api/Repository contracts for **5 entities**: Store, Tag, Amenity, PickupWindow, Holiday. Every method has a docblock (per our standards).
- Model + ResourceModel + Collection + Repository implementations for all 5.

**Stores Admin (CRUD)**
- Magento UI Component listing grid (search, filters, sortable columns, bookmarks, mass actions: Delete / Enable / Disable, inline edit on name/active/sort_order)
- Tabbed edit form (General / Address / About & Contact)
- 9 controllers: Index, NewAction, Edit, Save, Delete, InlineEdit, MassDelete, MassEnable, MassDisable
- Form action buttons (Save / Save & Continue / Back / Delete), all license-aware

**Shipping Carrier**
- `Model/Carrier/InStorePickup` — registered as `etechflow_isp`. Returns one method per active store (`Pick up at <store name>` at £0).
- Standard `getAllowedMethods()` + `collectRates()` pattern. Safe-fails to "no rates" on any error so checkout never breaks.

**THE KILLER FEATURE**
- `Plugin/Quote/ShippingAddressAutofillPlugin` — `after`-plugin on `Magento\Quote\Model\Quote\Address::setShippingMethod`. When the method matches `etechflow_isp_<store_code>`, overwrites the customer's shipping address with the store's address. Forces tax recalculation. Solves the universal C&C wrong-tax bug.

**Notifications**
- `Model/PickupOrderDetector` — central helper for "is this order a pickup?" + "which store?"
- `Model/PickupCodeGenerator` — cryptographically random 4-8 digit pickup-verification codes (length configurable)
- `Model/Notification/StaffAlertSender` + `view/frontend/email/staff_alert.html` — emails the staff at the picked store when a new pickup order arrives.
- `Observer/StaffAlertObserver` on `sales_order_place_after` — fires the alert. Pickup orders only; non-pickup short-circuits at zero cost.

**Optional eTechFlow Suite Adapters**
- `Model/Adapter/NdeEligibilityAdapter` — when NDE is installed + admin opted in, ISP defers to NDE's stock-eligibility rules engine (drop-ship + supplier mode + force-standard overrides). Falls back to MSI source-stock when NDE isn't present.

**Verify CLI**
- `bin/magento etechflow:isp:verify` — 13 checks covering license, config, all 11 DB tables, all 5 repositories via DI, carrier instantiation, auto-fill plugin presence. Exit 0 on full pass, 1 on any failure.

### Standalone-first architecture

This module works **fully standalone**. The integrations with NDE / DD / BED are **opt-in enhancements** soft-detected via `class_exists` — if the sibling module isn't installed, ISP falls back to its own self-contained logic.

| Optional pairing | Standalone | With pairing |
|---|---|---|
| **+ NDE** | Reads MSI source stock directly | Uses NDE's rules engine (drop-ship + supplier mode + force-standard) |
| **+ DD** | Own simple pickup windows | Optional: reuse DD's time intervals (consolidated UX) |
| **+ BED** | Generic "available once restocked" | Per-product backorder ETA on pickup options |

### Compatibility

- Magento Open Source 2.4.4 – 2.4.8
- Adobe Commerce 2.4.4 – 2.4.8
- PHP 8.1 / 8.2 / 8.3 / 8.4
- Hyvä Theme + Hyvä Checkout (Magewire integration ships in v1.1)

### Known limitations (deferred to v1.1)

- **Tag / Amenity / Pickup Window / Holiday admin UIs** — entities exist; admin grids ship in v1.1. Configure these via direct DB seed or via the Holiday import CLI for v1.0.
- **Per-store Hours / Exception days editor as sub-tabs on Store form** — schema is in place; admin sub-tabs ship in v1.1. Configure via direct DB seed for v1.0.
- **Customer "Pickup Ready" email + admin "Mark Ready" button** — needs schema columns on `sales_order` for pickup_code + pickup_status; deferred to v1.1.
- **Hyvä Checkout Magewire-native store picker** — DD already has a Magewire date picker (v1.4.0); same pattern applies here in v1.1. v1.0 customers using Hyvä Checkout get the standard Magento checkout's shipping-method radio list, with one method per store.
- **Holiday import CLI** (`etechflow:isp:import-holidays --country=GB`) — deferred to v1.1.
- **Map view** (Leaflet, lazy-loaded) — v1.2.
