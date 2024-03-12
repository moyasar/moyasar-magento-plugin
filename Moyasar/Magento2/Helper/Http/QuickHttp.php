<?php

namespace Moyasar\Magento2\Helper\Http;

use Exception;
use InvalidArgumentException;
use Moyasar\Magento2\Helper\Http\Exceptions\ClientException;
use Moyasar\Magento2\Helper\Http\Exceptions\ConnectionException;
use Moyasar\Magento2\Helper\Http\Exceptions\ServerException;
use Moyasar\Magento2\Helper\MoyasarHelper;

class QuickHttp
{
    private $curl_handler;
    private $disposed = false;

    // Config
    private $headers = [];

    public static function make()
    {
        return new static();
    }

    public function __construct()
    {
        global $wp_version;

        $this->curl_handler = curl_init(null);
        $this->headers['User-Agent'] = 'Moyasar Http; Magento Plugin v' . MoyasarHelper::VERSION;

        // Default Configurations
        curl_setopt($this->curl_handler, CURLOPT_TIMEOUT, 25);
    }

    public function __destruct()
    {
        $this->dispose();
    }

    public function basic_auth($username, $password = null)
    {
        $this->headers['Authorization'] = 'Basic ' . base64_encode("$username:$password");

        return $this;
    }

    public function set_headers($headers)
    {
        if (!is_array($headers)) {
            throw new InvalidArgumentException('headers must be an array');
        }

        $this->headers = array_merge($this->headers, $headers);
        return $this;
    }

    public function setOption($option, $value)
    {
        curl_setopt($this->curl_handler, $option, $value);
        return $this;
    }

    public function request($method, $url, $data = [])
    {
        if ($this->disposed) {
            throw new Exception('Instance is in unusable state, please create a new one');
        }

        $is_json = is_array($data);
        $method = trim(strtoupper($method));

        if ($is_json) {
            $this->headers['Content-Type'] = 'application/json';
        }

        if ($is_json && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            $data = json_encode($data);
            curl_setopt($this->curl_handler, CURLOPT_POSTFIELDS, $data);
        }

        if (in_array($method, ['GET', 'HEAD'])) {
            $url = $url . $this->encode_url_params($data);
        }

        $this
            ->setOption(CURLOPT_URL, $url)
            ->setOption(CURLOPT_CUSTOMREQUEST, $method)
            ->setOption(CURLOPT_HTTPHEADER, $this->build_headers())
            ->setOption(CURLOPT_RETURNTRANSFER, true)
            ->setOption(CURLOPT_HEADER, true);

        $raw_response = curl_exec($this->curl_handler);

        if ($error = curl_error($this->curl_handler)) {
            throw new ConnectionException('HTTP Error: ' . $error . ', ' . curl_errno($this->curl_handler));
        }

        $status = curl_getinfo($this->curl_handler, CURLINFO_RESPONSE_CODE);
        $header_size = curl_getinfo($this->curl_handler, CURLINFO_HEADER_SIZE);
        $headers = $this->parse_headers(substr($raw_response, 0, $header_size));
        $body = substr($raw_response, $header_size);
        $response = new HttpResponse($status, $headers, $body);

        $this->dispose();

        if ($response->isServerError()) {
            throw new ServerException('Server error: server returned status ' . $response->status(), $response);
        }

        if ($response->isClientError()) {
            throw new ClientException('Client error: server returned status ' . $response->status(), $response);
        }

        return $response;
    }

    public function get($url, $params = [])
    {
        return $this->request('GET', $url, $params);
    }

    public function post($url, $data = [])
    {
        return $this->request('POST', $url, $data);
    }

    public function put($url, $data = [])
    {
        return $this->request('PUT', $url, $data);
    }

    private function encode_url_params($params = [])
    {
        if (!is_array($params) || count($params) == 0) {
            return '';
        }

        $encoded = '?';

        foreach ($params as $key => $value) {
            $encoded .= urlencode($key) . '=' . urlencode($value) . '&';
        }

        return rtrim($encoded, '&');
    }

    private function build_headers()
    {
        $raw = [];

        foreach ($this->headers as $key => $value) {
            $raw[] = $key . ': ' . $value;
        }

        return $raw;
    }

    private function parse_headers($raw)
    {
        $headers = [];

        foreach (explode("\r\n", $raw) as $line) {
            $line = explode(':', $line);

            if (count($line) < 2) {
                continue;
            }

            $headers[strtolower(trim($line[0]))] = trim($line[1]);
        }

        return $headers;
    }

    private function dispose()
    {
        if ($this->disposed) {
            return;
        }

        $this->disposed = true;

        // Close CURL Handler
        if (is_resource($this->curl_handler)) {
            curl_close($this->curl_handler);
        }
    }
}
