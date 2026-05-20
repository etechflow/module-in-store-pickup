<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Api\Data;

/**
 * Service contract for a store tag (categorization).
 *
 * Tags drive admin grid filtering ("show me all Flagship stores") and
 * storefront filtering on the store locator. Maps to a row in
 * `etechflow_isp_tag`.
 */
interface TagInterface
{
    public const TAG_ID     = 'tag_id';
    public const CODE       = 'code';
    public const LABEL      = 'label';
    public const COLOUR     = 'colour';
    public const SORT_ORDER = 'sort_order';
    public const IS_ACTIVE  = 'is_active';

    /** @return int|null */
    public function getTagId(): ?int;

    /**
     * @param int|null $tagId
     * @return self
     */
    public function setTagId(?int $tagId): self;

    /** @return string Unique short identifier (URL + CLI safe). */
    public function getCode(): string;

    /**
     * @param string $code
     * @return self
     */
    public function setCode(string $code): self;

    /** @return string */
    public function getLabel(): string;

    /**
     * @param string $label
     * @return self
     */
    public function setLabel(string $label): self;

    /** @return string|null Hex / Tailwind colour for the badge. */
    public function getColour(): ?string;

    /**
     * @param string|null $colour
     * @return self
     */
    public function setColour(?string $colour): self;

    /** @return int */
    public function getSortOrder(): int;

    /**
     * @param int $sortOrder
     * @return self
     */
    public function setSortOrder(int $sortOrder): self;

    /** @return bool */
    public function isActive(): bool;

    /**
     * @param bool $isActive
     * @return self
     */
    public function setIsActive(bool $isActive): self;
}
