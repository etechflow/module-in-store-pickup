<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * No-op release marker for v2.0.0.
 *
 * Discipline established after the NDE v1.7.0 Keystation deploy incident
 * (site-down for ~20 minutes when `setup:upgrade` aborted on a
 * FilesystemIterator warning, never advanced `setup_module.data_version`,
 * and DbStatusValidator returned 500 on every request until rollback).
 *
 * Every ETechFlow module release now ships at least one data patch, even
 * when it has no actual data work to do. This guarantees `setup:upgrade`
 * always has SOMETHING to register in the `patch_list` table, surfacing
 * FS / permissions / DI errors during the patch phase (which retries
 * cleanly) instead of at the end of the upgrade (which doesn't).
 *
 * Without this discipline, a version bump that ships zero patches risks
 * the same site-down condition that hit NDE v1.7.0. v2.0.0 in particular
 * adds new declarative-schema columns to `quote`, `sales_order`, and
 * `sales_order_grid` — a heavyweight upgrade where reliably advancing
 * `data_version` matters most.
 *
 * Going forward, every release of this module ships at least one patch.
 * If a release has no real data migration to do, this template gets
 * copied/renamed (`V201ReleaseMarker`, `V210ReleaseMarker`, etc).
 *
 * @see \ETechFlow\NextDayEligibility\Setup\Patch\Data\V171ReleaseMarker
 * @see \ETechFlow\BackorderEtaDisplay\Setup\Patch\Data\V122ReleaseMarker
 */
class V200ReleaseMarker implements DataPatchInterface
{
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup
    ) {
    }

    public function apply(): self
    {
        // Intentionally no-op. Existence in `patch_list` is the only
        // side effect — that's the point. See class docblock.
        return $this;
    }

    public function getAliases(): array
    {
        return [];
    }

    public static function getDependencies(): array
    {
        return [];
    }
}
