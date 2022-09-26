<?php

namespace Moyasar\Mysr\Helper\Http\Exceptions;

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
        parent::__construct($message, 0, null);
        $this->response = $response;
    }

    public function apiErrorMessage()
    {
        if (! $this->response->isJson()) {
            return null;
        }

        $response = $this->response->json();
        $message = $response['message'] ?? $response['errors'] ?? null;

        return is_array($message) ? implode(', ', $message) : $message;
    }
}
