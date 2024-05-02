<?php

namespace Moyasar\Magento2\Helper\Http\Exceptions;

use Moyasar\Mysr\Helper\Http\HttpResponse;
use RuntimeException;

class HttpException extends RuntimeException
{
    /**
     * @var HttpResponse
     */
    public $response;

    public function __construct($message, $response)
    {
        $this->response = $response;

        if ($apiMessage = $this->apiErrorMessage()) {
            $status = $response->status();
            $message = "[$status] $apiMessage";
        }

        parent::__construct($message, 0, null);
    }

    public function apiErrorMessage()
    {
        if (! $this->response->isJson()) {
            return null;
        }

        $response = $this->response->json();
        $message = $response['message'] ?? 'Unknown error';
        if (isset($response['errors'])) {
            $message .= ' - ' . json_encode($response['errors'], true);
        }

        return is_array($message) ? implode(', ', $message) : $message;
    }
}
