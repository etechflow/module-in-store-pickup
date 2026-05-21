<?php

declare(strict_types=1);

namespace ETechFlow\InStorePickup\Controller\Adminhtml;

use Magento\Framework\Controller\Result\Json as JsonResult;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultInterface;

/**
 * Shared helper for Save controllers in the InStorePickup module.
 *
 * Magento UI Component forms (the modern <ui:form> XML kind) submit via
 * AJAX through Magento_Ui/js/form/save. They expect a JSON response with
 * `{ redirect, error, back }` — NOT a plain HTTP 302 Redirect.
 *
 * Returning a Redirect on an AJAX form submit causes the JS to silently
 * follow the redirect server-side, fetching the new page HTML without
 * actually navigating the browser. The user stays on the same edit URL
 * with the form cleared by the success-handler.
 *
 * This trait gives every Save controller a single `respondRedirect()`
 * method that:
 *   - Returns ResultJson with proper shape for AJAX submits
 *   - Falls back to a plain ResultRedirect for non-AJAX submits
 *
 * Consuming controllers must:
 *   - Inject JsonFactory in their constructor
 *   - Provide it as $this->ajaxSaveResultJsonFactory
 *   - Call $this->respondRedirect(path, params, isError) instead of
 *     manually building a Redirect.
 *
 * Action class members already provide $this->_url and $this->resultRedirectFactory.
 */
trait AjaxSaveResultTrait
{
    /**
     * AJAX-aware redirect — returns Json for AJAX, Redirect otherwise.
     */
    private function respondRedirect(string $path, array $params = [], bool $isError = false): ResultInterface
    {
        $url = $this->_url->getUrl($path, $params);

        if ($this->isAjaxRequest()) {
            /** @var JsonResult $json */
            $json = $this->ajaxSaveResultJsonFactory->create();
            return $json->setData([
                'redirect' => $url,
                'error'    => $isError,
                'back'     => false,
            ]);
        }

        /** @var Redirect $redirect */
        $redirect = $this->resultRedirectFactory->create();
        return $redirect->setPath($path, $params);
    }

    private function isAjaxRequest(): bool
    {
        $request = $this->getRequest();
        return $request->isAjax()
            || $request->isXmlHttpRequest()
            || strtolower((string) $request->getHeader('X-Requested-With')) === 'xmlhttprequest';
    }
}
