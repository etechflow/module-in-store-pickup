<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Controller\Adminhtml\Tag;

use ETechFlow\InStorePickup\Api\TagRepositoryInterface;
use ETechFlow\InStorePickup\Model\TagFactory;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Psr\Log\LoggerInterface;

/**
 * POST handler for the tag edit form.
 */
class Save extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'ETechFlow_InStorePickup::tags';

    public function __construct(
        Context $context,
        private readonly TagRepositoryInterface $tagRepository,
        private readonly TagFactory $tagFactory,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($context);
    }

    /**
     * @return Redirect
     */
    public function execute()
    {
        /** @var Redirect $redirect */
        $redirect = $this->resultRedirectFactory->create();
        $data = $this->getRequest()->getPostValue();

        if (empty($data)) {
            return $redirect->setPath('*/*/index');
        }

        $data = $this->normalisePayload($data);

        try {
            $tagId = (int) ($data['tag_id'] ?? 0);
            $tag = $tagId > 0 ? $this->tagRepository->getById($tagId) : $this->tagFactory->create();

            if (empty($data['code'])) {
                $this->messageManager->addErrorMessage(__('Tag code is required.'));
                return $redirect->setPath('*/*/edit', ['tag_id' => $tagId]);
            }
            if (empty($data['label'])) {
                $this->messageManager->addErrorMessage(__('Tag label is required.'));
                return $redirect->setPath('*/*/edit', ['tag_id' => $tagId]);
            }

            $tag->setData(array_merge($tag->getData(), $data));
            $this->tagRepository->save($tag);

            $this->messageManager->addSuccessMessage(__('Tag saved: %1', $tag->getLabel()));

            if ($this->getRequest()->getParam('back')) {
                return $redirect->setPath('*/*/edit', ['tag_id' => $tag->getTagId()]);
            }
            return $redirect->setPath('*/*/index');
        } catch (\Throwable $e) {
            $this->logger->error('ETechFlow_InStorePickup: tag save failed.', ['exception' => $e->getMessage()]);
            $this->messageManager->addErrorMessage(__('Could not save tag: %1', $e->getMessage()));
            return $redirect->setPath('*/*/edit', ['tag_id' => (int) ($data['tag_id'] ?? 0)]);
        }
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function normalisePayload(array $data): array
    {
        foreach (['code', 'label', 'colour'] as $f) {
            if (isset($data[$f]) && is_string($data[$f])) {
                $data[$f] = trim($data[$f]);
            }
        }
        $data['is_active'] = !empty($data['is_active']) ? 1 : 0;
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        return $data;
    }
}
