<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Block\Adminhtml\Form;

use ETechFlow\InStorePickup\Model\Autofill\AutofillConfig;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;

/**
 * Injects a small inline JS bootstrap into the store-edit admin form
 * that adds:
 *   - "Find Address" button next to the Postcode field (opens a dropdown
 *      of addresses fetched from getAddress.io via our AJAX proxy)
 *   - "Copy from Source" button next to the MSI Source Code field
 *   - Default Country pre-selected on new-store creation
 *
 * Lives in the form via <htmlContent> in the ui_component XML, same
 * pattern as the TimezoneNote block.
 *
 * The actual JS is in view/adminhtml/web/js/autofill.js — this block
 * just emits a `<script>` tag pointing at it + a config blob.
 */
class AutofillBootstrap extends Template
{
    /** @var string */
    protected $_template = 'ETechFlow_InStorePickup::form/autofill-bootstrap.phtml';

    public function __construct(
        Context $context,
        private readonly AutofillConfig $config,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function hasGetAddressKey(): bool
    {
        return $this->config->hasGetAddressApiKey();
    }

    public function getDefaultCountry(): string
    {
        return $this->config->getDefaultCountry();
    }

    public function getPostcodeLookupUrl(): string
    {
        return $this->getUrl('etechflow_isp/autofill/postcodelookup');
    }

    public function getMsiSourceCopyUrl(): string
    {
        return $this->getUrl('etechflow_isp/autofill/msisourcecopy');
    }
}
