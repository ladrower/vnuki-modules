<?php namespace Vnuki\Google\Drive;

class Google_RESTClient
{

    const RN  = "\r\n";

    protected $_ssl = true;

    protected $_timeout = 10;

    protected $_availableHttpMethods = array('GET', 'POST', 'PUT');

    protected $_socketHandle = null;

    protected $_requestLine = null;

    protected $_hostLine = null;

    protected $_statusLine = null;

    protected $_headerOut = null;
    
    protected $_bodyOut = null;

    public function __construct()
    {

    }

    public function __destruct() {
        $this->_disconnect();
    }

    protected function _connect($hostname, $port)
    {
        $this->_socketHandle = fsockopen($hostname, $port, $errno, $errstr, $this->_timeout);
        if (!$this->_socketHandle) {
            throw new Sockets_Exception($errstr, $errno);
        }
    }

    protected function _disconnect()
    {
        if (is_resource($this->_socketHandle)) {
            @fclose($this->_socketHandle);
        }
        $this->_socketHandle = null;
    }

    protected function _clean()
    {
        $this->_requestLine = null;
        $this->_hostLine = null;
        $this->_headersOut = null;
        $this->_bodyOut = null;
        $this->_statusLine = null;
    }

    protected function _send()
    {
        $out = $this->_requestLine . $this->_hostLine . $this->_headersOut . $this->_bodyOut;

        fwrite($this->_socketHandle, $out);

        $response = null;
        while (!feof($this->_socketHandle)) {
            $response .= fgets($this->_socketHandle, 128);
        }        

        return $response;
    }

    protected function _setRequestLine($method, $uri)
    {
        $this->_requestLine = "{$method} {$uri} HTTP/1.1" . self::RN;
    }

    protected function _setHost($host)
    {
        $this->_hostLine = "Host: {$host}" . self::RN;
    }
    
    protected function _setHeaders($headers)
    {
        $h = $this->_explodeHeadersByKeyVal($headers);

        $this->_headersOut = '';
        while (list($key, $value) = each($h)) {
            $this->_headersOut .= $key . ": " . $value . self::RN;
        }
        $this->_headersOut .= self::RN;
    }

    protected function _setBody($body)
    {
        if (null !== $body && is_array($body)) { 
            $body =  http_build_query($body, "", "&");
        }

        $this->_bodyOut = $body;
    }

    protected function _getBodySize()
    {
        return strlen($this->_bodyOut);
    }


    protected function _explodeHeadersByKeyVal($headers)
    {
        $h = array();
        foreach ($headers as $header) {
            list($key, $val) = explode(":", $header, 2);
            $h[trim($key)] = trim($val);
        }
        return $h;
    }

    protected function _addHeader(&$ref, $value, $force = false)
    {
        if ($force) {
            $ref[] = $value;
        } else {
            list($key) = explode(":", $value, 1);
            $h = $this->_explodeHeadersByKeyVal($ref);
            if (!array_key_exists(trim($key), $h)) {
                $ref[] = $value;
            }
        }
    }

    protected function _parseResponse($response, $raw)
    {
        if (empty($response)) {
            throw new Exception("Empty response considered as an error", 324);     
        }

        if (strpos($response, "\r\n\r\n") !== false) {
            $header = substr($response, 0, strpos($response, "\r\n\r\n"));
            $body = substr($response, strlen($header)+4);
        } else {
            $header = $response;
            $body = null;
        }

        $headLines = explode("\r\n", $header);

        $statusLine = trim(array_shift($headLines));

        if (preg_match("/HTTP\/[0-9]\.[0-9] ([0-9]+) ([a-zA-Z ]+)/i", $statusLine, $matches)) {
            $statusCode = $matches[1];
            $statusMessage = $matches[2];
        } else {
            throw new Exception("Invalid status line recieved"); 
        }

        $headers = array();
        foreach ($headLines as $line) {
            list($key, $val) = explode(":", $line, 2);
            $headers[trim(strtolower($key))] = trim($val);
        }

        if ($statusCode >= 400) {

            $body = json_decode($body, true);

            $error = (isset($body['error']['message'])) ? $body["error"]['message'] : "Unknown error";
            throw new Exception($error, $statusCode);
        }

        if (!$raw) {
            $body = json_decode($body, true);
        }

        return array("code" => $statusCode, "body" => $body, "headers" => $headers);

    }

    public function get($url, $headers = null, $raw = false) {
        return $this->_request($url, "GET", $headers, null, $raw);
    }

    public function post($url, $headers = null, $body = null, $raw = false) {
        return $this->_request($url, "POST", $headers, $body, $raw);
    }

    public function put($url, $headers = null, $body = null, $raw = false) {
        return $this->_request($url, "PUT", $headers, $body, $raw);
    }

    protected function _request($url, $method = "GET", $headers = null, $body = null, $raw = false) {

        if (!in_array($method, $this->_availableHttpMethods)) {
            throw new Exception("Http method not implemented. Requested {$method}", 1);
        }

        $urlComponents = parse_url($url);

        if ($urlComponents['scheme'] == 'https') {
            $hostPrefix = 'ssl://';
            $hostPort = 443;
        } else {
            $hostPrefix = '';
            $hostPort = 80;
        }

        $query = (empty($urlComponents['query'])) ? '' : '?' . $urlComponents['query'];

        $uri = $urlComponents['path'] . $query;

        $this->_clean();

        $this->_connect($hostPrefix . $urlComponents['host'], $hostPort);

        $this->_setRequestLine($method, $uri);
        $this->_setHost($urlComponents['host']);

        $this->_setBody($body);

        $this->_addHeader($headers, "Content-Length: " . $this->_getBodySize());
        $this->_addHeader($headers, "Connection: Close");

        $this->_setHeaders($headers);

        $response = $this->_send();

        $this->_disconnect();

        return $this->_parseResponse($response, $raw);
    }
}


class Sockets_Exception extends Exception
{

}