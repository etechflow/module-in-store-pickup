# ETechFlow_InStorePickup

**Click & Collect for Magento 2.** Multi-store admin (stores, holidays, tags, amenities, pickup windows). Hyvä Checkout Magewire-native. **Auto-fills shipping address from the picked store** — the killer feature every other C&C module misses.

> *Status: v1.0.0 Phase 1 (foundation only — admin CRUD ships in Phase 2-4).*

## Why this exists

Every existing Magento 2 C&C module shares the same structural bug: when a customer picks "in-store collection", they're still required to fill in a shipping address. They type their home address, Magento charges tax based on it, the merchant has to manually fix every order. We searched the market and 8+ vendors all have this same problem.

ETechFlow_InStorePickup solves it: when the customer picks a store, the shipping address auto-fills with the store's address. Tax calculation works. Order export works. Refund routing works. No manual cleanup.

## Features

| | |
|---|---|
| Multi-store admin (stores, hours, holidays, tags, amenities, pickup windows) | Phase 2-4 |
| Auto-fill shipping address from picked store | Phase 6 |
| Hyvä Checkout Magewire-native store picker | Phase 7 |
| Hyvä Theme + Luma fallback templates | Phase 7 |
| Per-installation HMAC license + bundle key | ✅ Phase 1 |
| Verify CLI (`etechflow:isp:verify`) | ✅ Phase 1 |
| Tideways profiler instrumentation (`ETechFlow_ISP_*` spans) | ✅ Phase 1 |
| Pickup-ready email + 6-digit verification code | Phase 8 |
| Optional NDE / DD / BED integrations | Phase 9 |
| Holiday import CLI (`etechflow:isp:import-holidays`) | Phase 10 |

## Standalone — and better when paired

This module works fully standalone. It does NOT require any other eTechFlow module.

When the sibling modules ARE installed, soft-detection kicks in:

| Optional pairing | Standalone | With pairing |
|---|---|---|
| **+ NDE** | Reads MSI stock | Uses NDE's eligibility engine (drop-ship + supplier rules) |
| **+ DD** | Own pickup windows | Optional: reuse DD's time intervals (consolidated UX) |
| **+ BED** | Generic "available once restocked" | Per-product backorder ETA shown on pickup options |

## Compatibility

| Platform | Status |
|---|---|
| Magento Open Source 2.4.4 – 2.4.8 | ✓ |
| Adobe Commerce 2.4.4 – 2.4.8 | ✓ |
| Hyvä Theme + Hyvä Checkout | ✓ Magewire-native |
| PHP 8.1 / 8.2 / 8.3 / 8.4 | ✓ |

## Installation

```bash
composer require etechflow/module-in-store-pickup:^1.0
bin/magento module:enable ETechFlow_InStorePickup
bin/magento setup:upgrade
bin/magento setup:di:compile      # production mode only
bin/magento cache:flush
```

`setup:upgrade` creates 11 tables prefixed `etechflow_isp_`.

## Smoke test

```bash
bin/magento etechflow:isp:verify
```

Phase 1 verify confirms license + config + all 11 tables exist.

## License

Proprietary — see `LICENSE.txt`. Commercial licenses available at <https://etechflow.com>.
