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

## Troubleshooting

### PDP "Click & Collect available" block appears in the wrong place

The module's default layout ships:

```xml
<referenceBlock name="product.info.main">
    <block class="ETechFlow\InStorePickup\Block\Catalog\Product\PickupAvailability"
           name="etechflow.isp.pdp.availability"
           template="ETechFlow_InStorePickup::catalog/product/pickup-availability.phtml"
           after="product.info.price"/>
</referenceBlock>
```

This targets the canonical Magento PDP info container (`product.info.main`) and places the availability block immediately after the price block — i.e. directly under the buy box. On stock Luma + most Hyvä installs this is correct out of the box.

**Symptom:** on stores running a custom theme, the block can render in an unrelated/broken location (e.g. far down the page near the footer, or inside a hidden template fragment).

**Cause:** the custom theme's PDP template renders `product.info.main` in a non-standard slot, or doesn't render that container at all and uses its own buy-box markup. The module's `<referenceBlock>` target then either lands in a dead slot or gets pushed to an unexpected position by the theme's own layout updates.

**Fix lives in the theme, not the module.** Edit your custom theme's `Magento_Catalog/layout/catalog_product_view.xml` (and the buy-box template if your theme overrides it) to reference the block by name and move it into the correct buy-box column. Typical pattern:

```xml
<!-- in your custom theme's catalog_product_view.xml -->
<move element="etechflow.isp.pdp.availability"
      destination="<your-theme-buy-box-container>"
      after="<your-theme-add-to-cart-block>"/>
```

If your theme replaces the buy box with a custom `.phtml` fragment, you can also render the block by name directly inside that template:

```php
<?= $block->getLayout()->getBlock('etechflow.isp.pdp.availability')?->toHtml() ?>
```

Either approach keeps the module unchanged and respects your theme conventions. Shipping a "fix" in the module itself would either no-op on themes that override the layout (theme wins) or break placement on stock themes (where the module default is correct).

### Block is in the right place but shows nothing

That's a behaviour, not a bug. The block renders only when:

- The module is enabled in admin
- At least one ISP store is active
- The current product is purchasable from that store

Verify with `bin/magento etechflow:isp:verify` — if all checks pass and the block still doesn't render, the gating is correct and there's nothing to fix.

## License

Proprietary — see `LICENSE.txt`. Commercial licenses available at <https://etechflow.com>.
