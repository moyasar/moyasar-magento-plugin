<?php

namespace Moyasar\Mysr\Controller\ApplePay;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultFactory;
use Moyasar\Mysr\Helper\Data;

/**
 * Class Validate
 * @package Moyasar\Mysr\Controller\ApplePay
 *
 * @method \Magento\Framework\App\Request\Http getRequest()
 */
class Validate extends Action implements CsrfAwareActionInterface
{
    protected $moyasarHelper;
    protected $resultFactory;

    public function __construct(Context $context, Data $helper, ResultFactory $resultFactory)
    {
        parent::__construct($context);
        $this->moyasarHelper = $helper;
        $this->resultFactory = $resultFactory;
    }

    public function execute()
    {
        if (!$this->isPost()) {
            return $this->resultFactory
                ->create(ResultFactory::TYPE_JSON)
                ->setData(['error' => 'Only POST allowed'])
                ->setStatusHeader(400, null, 'Bad Request');
        }

        $validationUrl = $this->json('validation_url');
        if (!$validationUrl) {
            return $this->resultFactory
                ->create(ResultFactory::TYPE_JSON)
                ->setData(['error' => 'Validation URL is required'])
                ->setStatusHeader(400, null, 'Bad Request');
        }

        $appleResponse = $this->getHelper()->validateApplePayMerchant($validationUrl);
        if (!$appleResponse) {
            return $this->resultFactory
                ->create(ResultFactory::TYPE_JSON)
                ->setData(['error' => 'Could not get response from Apple'])
                ->setStatusHeader(400, null, 'Bad Request');
        }

        $json = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $json->setData($appleResponse);
        return $json;
    }

    protected function isPost()
    {
        return mb_strtoupper($_SERVER['REQUEST_METHOD']) == 'POST';
    }

    protected function json($key = null)
    {
        static $requestJson = null;

        if (is_null($requestJson)) {
            $requestBody = $this->getRequest()->getContent();
            $requestJson = @json_decode($requestBody, true);
        }

        if (!$requestJson) {
            $requestJson = [];
        }

        if ($key && isset($requestJson[$key])) {
            return $requestJson[$key];
        }

        return $requestJson;
    }

    /**
     * Get moyasar helper
     *
     * @return Data
     */
    protected function getHelper()
    {
        return $this->moyasarHelper;
    }

    // A stupid hack to be able to receive POST requests, Magento is dumpster fire
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}
