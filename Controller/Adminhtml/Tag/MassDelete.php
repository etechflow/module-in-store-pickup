<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Controller\Adminhtml\Tag;

use ETechFlow\InStorePickup\Api\TagRepositoryInterface;
use ETechFlow\InStorePickup\Model\ResourceModel\Tag\CollectionFactory as TagCollectionFactory;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Ui\Component\MassAction\Filter;
use Psr\Log\LoggerInterface;

/**
 * Mass-delete selected tags.
 */
class MassDelete extends Action
{
    public const ADMIN_RESOURCE = 'ETechFlow_InStorePickup::tags';

    public function __construct(
        Context $context,
        private readonly Filter $filter,
        private readonly TagCollectionFactory $collectionFactory,
        private readonly TagRepositoryInterface $tagRepository,
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

        try {
            $collection = $this->filter->getCollection($this->collectionFactory->create());
            $deleted = 0;
            foreach ($collection as $tag) {
                try {
                    $this->tagRepository->delete($tag);
                    $deleted++;
                } catch (\Throwable $e) {
                    $this->logger->error('ETechFlow_InStorePickup: mass-delete tag failed.', [
                        'tag_id'    => $tag->getTagId(),
                        'exception' => $e->getMessage(),
                    ]);
                }
            }
            $this->messageManager->addSuccessMessage(__('%1 tag(s) deleted.', $deleted));
        } catch (\Throwable $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }

        return $redirect->setPath('*/*/index');
    }
}
