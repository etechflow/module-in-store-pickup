<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Controller\Adminhtml\Tag;

use ETechFlow\InStorePickup\Api\TagRepositoryInterface;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Registry;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;

/**
 * Edit / new-tag form.
 */
class Edit extends Action
{
    public const ADMIN_RESOURCE = 'ETechFlow_InStorePickup::tags';

    public function __construct(
        Context $context,
        private readonly PageFactory $resultPageFactory,
        private readonly Registry $registry,
        private readonly TagRepositoryInterface $tagRepository
    ) {
        parent::__construct($context);
    }

    /**
     * @return Page
     */
    public function execute()
    {
        $tagId = (int) $this->getRequest()->getParam('tag_id');
        $title = __('New Store Tag');

        if ($tagId > 0) {
            try {
                $tag = $this->tagRepository->getById($tagId);
                $this->registry->register('etechflow_isp_current_tag', $tag);
                $title = __('Edit Store Tag: %1', $tag->getLabel());
            } catch (NoSuchEntityException $e) {
                $this->messageManager->addErrorMessage(__('Tag does not exist.'));
                return $this->resultRedirectFactory->create()->setPath('*/*/index');
            }
        }

        /** @var Page $resultPage */
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('ETechFlow_InStorePickup::tags');
        $resultPage->getConfig()->getTitle()->prepend($title);
        return $resultPage;
    }
}
