<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Controller\Adminhtml\Tag;

use ETechFlow\InStorePickup\Api\TagRepositoryInterface;
use ETechFlow\InStorePickup\Controller\Adminhtml\AjaxSaveResultTrait;
use ETechFlow\InStorePickup\Model\TagFactory;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Psr\Log\LoggerInterface;

/**
 * POST handler for the tag edit form.
 */
class Save extends Action implements HttpPostActionInterface
{
    use AjaxSaveResultTrait;

    public const ADMIN_RESOURCE = 'ETechFlow_InStorePickup::tags';

    private const DATA_PERSISTOR_KEY = 'etechflow_isp_tag';

    public function __construct(
        Context $context,
        private readonly TagRepositoryInterface $tagRepository,
        private readonly TagFactory $tagFactory,
        private readonly DataPersistorInterface $dataPersistor,
        private readonly JsonFactory $ajaxSaveResultJsonFactory,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        $data = $this->getRequest()->getPostValue();
        if (empty($data)) {
            return $this->respondRedirect('*/*/index');
        }

        $data = $this->normalisePayload($data);
        $tagId = (int) ($data['tag_id'] ?? 0);

        // v1.1.6 fix: strip tag_id=0 so new tags INSERT (not UPDATE WHERE id=0).
        // See Store/Save.php for the full root-cause explanation.
        unset($data['tag_id']);

        try {
            $this->dataPersistor->set(self::DATA_PERSISTOR_KEY, $data);

            $tag = $tagId > 0 ? $this->tagRepository->getById($tagId) : $this->tagFactory->create();

            if (empty($data['code'])) {
                $this->messageManager->addErrorMessage(__('Tag code is required.'));
                return $this->respondRedirect('*/*/edit', ['tag_id' => $tagId], true);
            }
            if (empty($data['label'])) {
                $this->messageManager->addErrorMessage(__('Tag label is required.'));
                return $this->respondRedirect('*/*/edit', ['tag_id' => $tagId], true);
            }

            $tag->setData(array_merge($tag->getData(), $data));
            $this->tagRepository->save($tag);

            $this->dataPersistor->clear(self::DATA_PERSISTOR_KEY);
            $this->messageManager->addSuccessMessage(__('Tag saved: %1', $tag->getLabel()));

            return $this->respondRedirect(
                '*/*/edit',
                ['tag_id' => $tag->getTagId(), '_current' => true]
            );
        } catch (\Throwable $e) {
            $this->logger->error('ETechFlow_InStorePickup: tag save failed.', ['exception' => $e->getMessage()]);
            $this->messageManager->addErrorMessage(__('Could not save tag: %1', $e->getMessage()));
            return $this->respondRedirect('*/*/edit', ['tag_id' => $tagId], true);
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
