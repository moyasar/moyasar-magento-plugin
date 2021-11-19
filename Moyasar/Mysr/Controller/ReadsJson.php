<?php

namespace Moyasar\Mysr\Controller;

use Magento\Framework\App\Request\Http;

trait ReadsJson
{
    protected $json = null;

    protected function getJsonArray()
    {
        if ($this->json == null) {
            $this->json = @json_decode($this->context->getRequest()->getContent(), true);
        }

        if ($this->json == null) {
            $this->json = $this->context->getRequest()->getPost();
        }

        return $this->json;
    }

    public function getJson($key = null, $default = null)
    {
        $data = $this->getJsonArray();

        if ($key == null) {
            return $data;
        }

        if (isset($data[$key])) {
            return $data[$key];
        }

        return $default;
    }
}
