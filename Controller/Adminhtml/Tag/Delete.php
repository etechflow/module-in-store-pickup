<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Controller\Adminhtml\Tag;

use ETechFlow\InStorePickup\Api\TagRepositoryInterface;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\NoSuchEntityException;
use Psr\Log\LoggerInterface;

/**
 * Delete a tag. The store↔tag link table cascades via FK.
 */
class Delete extends Action
{
    public const ADMIN_RESOURCE = 'ETechFlow_InStorePickup::tags';

    public function __construct(
        Context $context,
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
        $tagId = (int) $this->getRequest()->getParam('tag_id');

        if ($tagId <= 0) {
            $this->messageManager->addErrorMessage(__('Missing tag_id.'));
            return $redirect->setPath('*/*/index');
        }

        try {
            $this->tagRepository->deleteById($tagId);
            $this->messageManager->addSuccessMessage(__('Tag deleted.'));
        } catch (NoSuchEntityException $e) {
            $this->messageManager->addErrorMessage(__('Tag does not exist.'));
        } catch (\Throwable $e) {
            $this->logger->error('ETechFlow_InStorePickup: tag delete failed.', ['exception' => $e->getMessage()]);
            $this->messageManager->addErrorMessage(__('Could not delete tag: %1', $e->getMessage()));
        }

        return $redirect->setPath('*/*/index');
    }
}
