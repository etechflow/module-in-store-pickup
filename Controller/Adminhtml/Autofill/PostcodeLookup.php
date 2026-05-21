<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Controller\Adminhtml\Autofill;

use ETechFlow\InStorePickup\Model\Autofill\PostcodeLookupClient;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;

/**
 * AJAX endpoint hit by the admin store-edit form when the user types
 * a postcode and clicks "Find Address".
 *
 * Returns JSON:
 *   { ok: true, addresses: [ { line1, line2, city, county, postcode, label }, ... ] }
 *
 * Or on failure:
 *   { ok: false, error: "..." }
 *
 * Auth: standard adminhtml ACL on the parent store-edit resource.
 */
class PostcodeLookup extends Action
{
    public const ADMIN_RESOURCE = 'ETechFlow_InStorePickup::store';

    public function __construct(
        Context $context,
        private readonly JsonFactory $jsonFactory,
        private readonly PostcodeLookupClient $client
    ) {
        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        $json = $this->jsonFactory->create();
        $postcode = trim((string) $this->getRequest()->getParam('postcode', ''));

        if ($postcode === '') {
            return $json->setData(['ok' => false, 'error' => 'Postcode is required']);
        }

        $addresses = $this->client->lookup($postcode);
        if (empty($addresses)) {
            return $json->setData([
                'ok'        => true,
                'addresses' => [],
                'message'   => 'No addresses found for that postcode (or postcode is invalid).',
            ]);
        }

        return $json->setData(['ok' => true, 'addresses' => $addresses]);
    }
}
