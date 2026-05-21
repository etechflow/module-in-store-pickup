<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Controller\Adminhtml\Autofill;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Psr\Log\LoggerInterface;

/**
 * AJAX endpoint that fetches an MSI Source's name + address details.
 *
 * Uses soft DI on `Magento\InventoryApi\Api\SourceRepositoryInterface`
 * via the ObjectManager pattern — Magento's Inventory module is OPTIONAL
 * on Open Source installs; we don't want a hard composer dep that
 * fails when MSI is absent.
 *
 * Returns JSON:
 *   { ok: true, source: { name, code, street1, street2, city, region, postcode, country_id, phone, ... } }
 *
 * Or:
 *   { ok: false, error: "..." }
 */
class MsiSourceCopy extends Action
{
    public const ADMIN_RESOURCE = 'ETechFlow_InStorePickup::store';

    public function __construct(
        Context $context,
        private readonly JsonFactory $jsonFactory,
        private readonly \Magento\Framework\ObjectManagerInterface $objectManager,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        $json = $this->jsonFactory->create();
        $sourceCode = trim((string) $this->getRequest()->getParam('source_code', ''));

        if ($sourceCode === '') {
            return $json->setData(['ok' => false, 'error' => 'MSI source code is required']);
        }

        // Soft dep on MSI — only available where the merchant has the Inventory module
        if (!interface_exists('\\Magento\\InventoryApi\\Api\\SourceRepositoryInterface')) {
            return $json->setData([
                'ok'    => false,
                'error' => 'Magento Inventory (MSI) module is not installed. "Copy from Source" requires MSI.',
            ]);
        }

        try {
            $repo = $this->objectManager->get('\\Magento\\InventoryApi\\Api\\SourceRepositoryInterface');
            $source = $repo->get($sourceCode);
        } catch (\Throwable $e) {
            return $json->setData([
                'ok'    => false,
                'error' => sprintf('MSI source "%s" not found.', $sourceCode),
            ]);
        }

        try {
            return $json->setData([
                'ok'     => true,
                'source' => [
                    'name'       => (string) $source->getName(),
                    'code'       => (string) $source->getSourceCode(),
                    'street1'    => (string) $source->getStreet(),
                    'street2'    => '',  // MSI source has single-line street
                    'city'       => (string) $source->getCity(),
                    'region'     => (string) $source->getRegion(),
                    'postcode'   => (string) $source->getPostcode(),
                    'country_id' => (string) $source->getCountryId(),
                    'phone'      => (string) $source->getPhone(),
                    'email'      => (string) $source->getEmail(),
                    'latitude'   => $source->getLatitude(),
                    'longitude'  => $source->getLongitude(),
                    'description' => (string) $source->getDescription(),
                ],
            ]);
        } catch (\Throwable $e) {
            $this->logger->warning(
                'ETechFlow_ISP MSI source copy failed to read fields',
                ['source_code' => $sourceCode, 'exception' => $e->getMessage()]
            );
            return $json->setData(['ok' => false, 'error' => 'Failed to read MSI source fields.']);
        }
    }
}
