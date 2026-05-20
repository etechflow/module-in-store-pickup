<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Block\Adminhtml\Tag;

use ETechFlow\InStorePickup\Api\TagRepositoryInterface;
use Magento\Backend\Block\Widget\Context;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Base for Tag form action buttons (Save / Back / Delete).
 */
class GenericButton
{
    public function __construct(
        protected readonly Context $context,
        protected readonly TagRepositoryInterface $tagRepository
    ) {
    }

    /**
     * @return int|null
     */
    public function getTagId(): ?int
    {
        try {
            $id = (int) $this->context->getRequest()->getParam('tag_id');
            if ($id <= 0) {
                return null;
            }
            return $this->tagRepository->getById($id)->getTagId();
        } catch (NoSuchEntityException $e) {
            return null;
        }
    }

    /**
     * @param string $route
     * @param array<string, mixed> $params
     * @return string
     */
    public function getUrl(string $route = '', array $params = []): string
    {
        return $this->context->getUrlBuilder()->getUrl($route, $params);
    }
}
