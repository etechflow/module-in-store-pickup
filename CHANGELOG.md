# Changelog — ETechFlow In-Store Pickup

All notable changes to this module. Adheres to [Semantic Versioning](https://semver.org/).

---

## [1.3.0] — 2026-05-22 — Drop Magewire; render store picker via Block + Alpine.js (architectural fix)

The v1.2.1 container-name fix (`checkout.shipping.before` → `checkout.shipping.methods.before`) was necessary but not sufficient. The picker still didn't render on Hyvä Checkout because of an architectural mismatch: `StorePicker` extended `\Magewirephp\Magewire\Component` directly, but Hyvä Checkout has its own Magewire bootstrap pipeline that only hydrates components implementing its form-abstraction interfaces. Even with the container right, Hyvä's JS never bound to the block.

The "proper Magewire fix" is a meaningful research project (reading Hyvä Checkout's vendor source, implementing the right form interface, testing against Hyvä's bootstrap pipeline). v1.3.0 sidesteps that entirely by dropping Magewire — the picker doesn't actually need server-state reactivity, only visual selection-highlighting, which Alpine.js handles natively without framework lock-in.

### Changed

- **New picker implementation: `Block/Checkout/PickupStorePicker.php` + `view/frontend/templates/checkout/pickup-store-picker.phtml`.** Plain Magento block (no Magewire base class) renders the active-store card list server-side. Alpine.js `x-data` tracks the visually-selected card. Clicking a card calls `pick(code)`, which (1) sets `selected` for visual highlighting, and (2) finds the matching shipping-method radio (`input[type=radio][value="etechflow_isp_<code>"]`) and triggers its `click()` + `change` event. Standard Magento shipping commit fires, which runs `ShippingAddressAutofillPlugin`, which overwrites the shipping address with the picked store's address (the tax-bug-kill differentiator).

- **Layout update: `view/frontend/layout/hyva_checkout_components.xml`** now mounts the new block class instead of the Magewire component. Container (`checkout.shipping.methods.before`, retained from v1.2.1) is unchanged.

- **Deprecated: `Magewire/Checkout/StorePicker.php`.** Marked `@deprecated` in the class docblock; left on disk for backwards-compatibility with any integrator referencing it. Safe to delete in a future major release.

### Why this is the right fix, not a workaround

- The picker is decorative state — Magewire's server round-trip was overkill even when it worked.
- Alpine.js works on every Magento checkout flavour that ships Alpine (Hyvä Checkout, Hyvä Theme, Luma installs with Alpine). No Hyvä-Checkout-specific bootstrap required.
- No regression to the carrier / autofill / order placement path — those continue using Magento's standard shipping-method mechanism. The picker click just simulates a radio click.

### Migration

```
composer update etechflow/module-in-store-pickup
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento setup:static-content:deploy -f
bin/magento cache:flush
docker restart <php-fpm-container>
```

No data migration. On next checkout, customers will see the rich card UI for the first time since v1.0.0. The bare-radio fallback path is unchanged for non-Hyvä-Checkout flows.

---

## [1.2.1] — 2026-05-22 — CRITICAL: Magewire store picker has never rendered on Hyvä Checkout (wrong container name)

### Fixed

- **Rich Magewire store-picker card UI was silently failing to mount on every Hyvä Checkout install since v1.0.0.** The layout file `view/frontend/layout/hyva_checkout_components.xml` referenced container ID `checkout.shipping.before`, which **doesn't exist** in Hyvä Checkout — it's a stock Magento Checkout name. Hyvä Checkout has its own container vocabulary; the real shipping-area container is `checkout.shipping.methods.before`. Magento's layout merger treats a `<block>` under a non-existent `<referenceContainer>` as a no-op — block created but never bound, never rendered, no error logged. Customers fell back to the plain Magento shipping-method radio list (one radio per active store: "Pick up at Keystation Maldon Store · £0.00"). The carrier still worked, orders still completed, PINs still issued — the bug was purely UX (customers saw a bare radio instead of styled card list with selected-state highlighting, instructions panel, accessibility roles).

  **Fix:** one-line change in `hyva_checkout_components.xml`:
  ```
  - <referenceContainer name="checkout.shipping.before">
  + <referenceContainer name="checkout.shipping.methods.before">
  ```

  Real Hyvä Checkout shipping-area containers (verified on Keystation 2.4.8 + Hyva Commerce v1.4.5):
  - `checkout.shipping.methods.before` / `.after`
  - `checkout.shipping.section`
  - `checkout.shipping-details.before` / `.section`

### Migration

```
composer update etechflow/module-in-store-pickup
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento setup:static-content:deploy -f
bin/magento cache:flush
docker restart <php-fpm-container>   # OPcache
```

No data migration. Existing orders, stores, holidays, all preserved. On next checkout, customers on Hyvä Checkout will see the rich Magewire card picker (with name + address + phone + instructions + "Selected" badge + accessible roles) instead of the bare radio fallback.

**Stores not on Hyvä Checkout** (plain Hyvä Theme, Luma) — unaffected. The `hyva_checkout_components.xml` layout handle only triggers when Hyvä Checkout is installed and active.

---

## [1.2.0] — 2026-05-22 — Product-page "Click & Collect available" widget

Closes the visible feature gap between checkout-only ISP and competitor modules (Amasty Store Pickup, MageWorx, Wyomind) which all surface Click & Collect on the product page itself. Until now ISP only showed up at checkout — customers had no way to know C&C was available before committing to buy.

### Added

- **New PDP block** `ETechFlow\InStorePickup\Block\Catalog\Product\PickupAvailability` mounted under `product.info.price` via `view/frontend/layout/catalog_product_view.xml`. Renders a small "Click & Collect available" panel with two configurable modes:

  **Mode A — Simple notice** (matches what most cheaper competitor modules ship):
  ```
  🏪 Click & Collect available
     Pick up at any of our 3 shops at checkout. Free, same-day where stock allows.
  ```

  **Mode B — Per-store list with live MSI stock** (default — matches Amasty's headline PDP feature):
  ```
  🏪 Click & Collect available
     ✓ Maldon       (3 in stock)
     ✓ Chelmsford   (1 in stock)
     ✗ Witham       (out of stock)
     Pick a shop at checkout.
  ```

  Mode B iterates active pickup stores; for each store with an `msi_source_code` set (configurable on each store's admin form), queries `Magento_InventoryApi/GetSourceItemsBySkuInterface` for the current product's per-source quantity. Stores without an MSI mapping still appear in the list, just without a stock badge. Magento_InventoryApi is soft-detected so the module survives on stripped builds where MSI is absent — Mode B silently degrades to "available at: X stores" with no stock counts.

- **Admin config** under `Stores → Configuration → eTechFlow → In-Store Pickup → Product Page Widget`:
  - `Enable` (default: Yes) — turn the PDP block on/off
  - `Display Mode` (default: Per-store list with stock) — switch between Mode A and Mode B

### Notes

This release intentionally does NOT include:
- Pre-selecting the store from PDP through to checkout (would need quote-level `pickup_preferred_store_id` storage; v1.3.0 candidate)
- Cart-page Click & Collect banner (v1.3.0 candidate)
- Catalog list filter "available at my store" (heavy; deferred unless a customer asks)
- Map-based store locator (Amasty sells that as a separate paid add-on; we'd do the same)

### Migration

```
composer update etechflow/module-in-store-pickup
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento setup:static-content:deploy -f
bin/magento cache:flush
```

No schema changes. Pure additive — existing stores with `msi_source_code` unset still work, just don't show stock numbers on PDP.

---

## [1.1.15] — 2026-05-22 — sortOrder 88 → 68 (park above Amasty)

### Fixed

- **eTechFlow sidebar entry not next to other vendor extensions.** v1.1.14 picked `sortOrder=88` expecting that to cluster with paid-extension vendors. Verified on Keystation's Hyva Commerce admin that Amasty is actually at `sortOrder=69`, so 88 landed between System (80) and Find Partners (100+) — not adjacent. **Fix:** dropped to `sortOrder=68` so eTechFlow sits directly above Amasty, matching the convention paid-extension vendors follow (cluster just above Stores in the same visual band).

### Migration

```
composer update etechflow/module-in-store-pickup
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento setup:static-content:deploy -f
bin/magento cache:flush
```

Pure menu position change — no schema, no behaviour, no admin route changes.

---

## [1.1.14] — 2026-05-22 — Fix eTechFlow sidebar position + Configuration column grouping

Two corrections to the v1.1.13 mega-menu pilot after live-test on Keystation Hyva Commerce admin.

### Fixed

- **Sidebar position parked at the bottom instead of "near Stores".** v1.1.13 used `sortOrder=305` expecting that to land just after Magento's Stores (typical core sortOrder ~300). Hyva Commerce admin's stock entries cap around 100, so 305 sent us to the very bottom of the sidebar under "Find Partners & Extensions". **Fix:** dropped `sortOrder` to `88` — clusters with the established vendor extensions (Amasty, Magefan, typically 85–89) just above Magento's Stores. Verified live: 305 → bottom; 88 → next to Amasty.

- **Configuration leaf dangling at the bottom of the panel with a big vertical gap.** v1.1.13 declared `eTechFlow::configuration` as a direct child of `eTechFlow::root`. Magento's mega-menu lays out parent-with-children entries first (as columns), then dangles leaves separately at the bottom — so Configuration appeared orphaned below "Pickup Windows" instead of grouped. **Fix:** introduced `eTechFlow::settings` as a column header (`parent=eTechFlow::root`, `sortOrder=500`); Configuration now lives inside it (`parent=eTechFlow::settings`, `sortOrder=10`). The mega-menu renders Settings as its own column, matching how Magento's own Stores mega-menu groups its leaves under a "Settings" column rather than letting them dangle.

When other eTechFlow modules ship the same pattern, the shared `eTechFlow::settings` id merges into one column and each module's Configuration entry collapses into a single leaf inside it.

### Migration

```
composer update etechflow/module-in-store-pickup
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento setup:static-content:deploy -f
bin/magento cache:flush
```

Hyva-admin requires `setup:static-content:deploy -f` between `di:compile` and `cache:flush` on production-mode installs — otherwise admin pages 500 with a missing `view_preprocessed/root.phtml`.

No schema changes. No data migration. Pure menu-layout adjustment.

---

## [1.1.13] — 2026-05-22 — Dedicated "eTechFlow" top-level sidebar entry (pilot)

Until now, ISP's admin pages lived inside Magento's stock Stores menu (Stores → Settings → In-Store Pickup → …). Visually indistinguishable from core menu entries — merchants couldn't tell at a glance which items were paid eTechFlow extensions vs stock Magento. Established vendors (Amasty, Magefan, MageWorx) all anchor their modules under a dedicated top-level sidebar group with their brand name. This patch starts that pattern for eTechFlow with ISP as the pilot module.

### Changed

- **New top-level "eTechFlow" sidebar entry.** ISP's admin pages now live under a dedicated `eTechFlow::root` menu node (sortOrder 305 — sits just after Magento's Stores, before System). The mega-menu panel opens on hover with ISP's pages (Stores / Holidays / Store Tags / Amenities / Pickup Windows) as a column. When other eTechFlow modules ship the same pattern, each contributes one column to the same panel — Magento merges entries with identical ids, so the group exists once when any eTechFlow module is installed.
- **`ETechFlow_InStorePickup::isp_root` reparented** from `Magento_Backend::stores_settings` → `eTechFlow::root`. The five submenu children (stores, holidays, tags, amenities, pickup_windows) stay parented under isp_root unchanged. No URL routes changed; only the sidebar location.
- **New "Configuration" leaf** at the bottom of the eTechFlow sidebar group, opens `Stores → Configuration` with the eTechFlow tab pre-expanded (points to ISP's own section under `<tab id="etechflow">`). Visible only to users with `Magento_Config::config` permission.

### Migration

```
composer update etechflow/module-in-store-pickup
bin/magento setup:upgrade
bin/magento cache:flush
```

No schema changes. No data migration. Pure menu-routing change — existing admin URLs (`/admin/etechflow_isp/store/index/` etc.) still work identically; only the sidebar location changed.

### Notes

- No inter-module dependency added — ISP remains standalone. When you install only ISP, the eTechFlow group exists with just ISP's column. When you install all eTechFlow modules, each contributes its own column via the same shared `eTechFlow::root` id.
- This is the pilot module; the remaining ETechFlow modules (Delivery Date, Back-in-Stock Notifications, Shipping Table Rates, Order Email Editor, Image Optimizer, Page Speed Optimizer) will follow the same pattern in their next releases.

---

## [1.1.12] — 2026-05-22 — Exception Day date picker fallback to typed text

### Fixed

- **Exception Day date field unusable on Hyva admin.** The Exception Days dynamicRows table used Magento's standard `formElement="date"` widget for the Date column. Works in standalone fieldsets (e.g. the Holiday form's date field), but on the Hyva Commerce admin theme the picker failed to initialize INSIDE the dynamicRows container — clicking the input or the calendar icon did nothing, no widget rendered, no JS error in console. Admins literally could not add an exception day. Tried `dataType=text` → `dataType=date`, adding `storeLocale` option, cache flush + static-content:deploy — none of it landed.

  **Fix:** swapped `formElement="date"` → `formElement="input"` + `dataType=text` with a `YYYY-MM-DD` placeholder and notice. Kept `required-entry`; dropped `validate-date` (the rule needs date-element metadata that text inputs don't carry → JS errors when it tries to validate). The underlying `exception_date` column is unchanged (still stores `YYYY-MM-DD` strings). `ExceptionManager::replaceRows()` handles them the same way regardless of widget. Round-trip verified live: typed `2026-12-25` + Closed=Yes + Reason="Christmas Day", saved, hard-reload, row reappeared.

### Migration

```
composer update etechflow/module-in-store-pickup
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush
docker exec <php-fpm-container> kill -USR2 1   # restart php-fpm — clears OPcache
```

No schema changes. Pure UI Component XML adjustment.

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
