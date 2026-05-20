<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Model;

use ETechFlow\InStorePickup\Api\Data\TagInterface;
use ETechFlow\InStorePickup\Model\ResourceModel\Tag as TagResource;
use Magento\Framework\Model\AbstractModel;

class Tag extends AbstractModel implements TagInterface
{
    /** @var string */
    protected $_eventPrefix = 'etechflow_isp_tag';

    /** @var string */
    protected $_eventObject = 'tag';

    /**
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(TagResource::class);
    }

    public function getTagId(): ?int
    {
        $v = $this->getData(self::TAG_ID);
        return $v === null ? null : (int) $v;
    }

    public function setTagId(?int $tagId): self
    {
        return $this->setData(self::TAG_ID, $tagId);
    }

    public function getCode(): string
    {
        return (string) $this->getData(self::CODE);
    }

    public function setCode(string $code): self
    {
        return $this->setData(self::CODE, $code);
    }

    public function getLabel(): string
    {
        return (string) $this->getData(self::LABEL);
    }

    public function setLabel(string $label): self
    {
        return $this->setData(self::LABEL, $label);
    }

    public function getColour(): ?string
    {
        $v = $this->getData(self::COLOUR);
        return $v === null || $v === '' ? null : (string) $v;
    }

    public function setColour(?string $colour): self
    {
        return $this->setData(self::COLOUR, $colour);
    }

    public function getSortOrder(): int
    {
        return (int) $this->getData(self::SORT_ORDER);
    }

    public function setSortOrder(int $sortOrder): self
    {
        return $this->setData(self::SORT_ORDER, $sortOrder);
    }

    public function isActive(): bool
    {
        return (bool) $this->getData(self::IS_ACTIVE);
    }

    public function setIsActive(bool $isActive): self
    {
        return $this->setData(self::IS_ACTIVE, $isActive ? 1 : 0);
    }
}
