<?php

namespace Moyasar\Mysr\Helper\Http;

class HttpResponse
{
    private $status;
    private $headers;
    private $body;

    public function __construct($status, $headers, $body)
    {
        $this->status = $status;
        $this->headers = $headers;
        $this->body = $body;
    }

    public function status()
    {
        return $this->status;
    }

    public function headers()
    {
        return $this->headers;
    }

    public function body()
    {
        return $this->body;
    }

    public function isClientError()
    {
        return $this->status >= 400 && $this->status < 500;
    }

    public function isServerError()
    {
        return $this->status >= 500 && $this->status < 600;
    }

    public function isSuccess()
    {
        return $this->status >= 200 && $this->status < 300;
    }

    public function isJson()
    {
        return
            is_array($this->headers) &&
            isset($this->headers['content-type']) &&
            strstr($this->headers['content-type'], 'application/json');
    }

    public function json()
    {
        return @json_decode($this->body, true);
    }
}