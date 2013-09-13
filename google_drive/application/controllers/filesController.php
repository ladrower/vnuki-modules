<?php namespace Vnuki\Google\Drive;


class Files_Controller extends Controller_Abstract
{
    protected $_tempFilename = null;

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Method router
     *
     * @return void
     */
    public function processMethod()
    {
        switch($this->_method)
        {
            case "":

                break;

            default:
                $this->invalidMethod();
        }

    }

    public function uploadFileChunked($file, $name = null)
    {
        $logger = new Controller_Logger();
        $result = null;

        try {
            $result = $this->_doChunkedUpload($file, $name);
            $logString = "Chunked Upload result: " . var_export($result, true);
        } catch(Exception $e) {
            $logString = "Exception in uploadFileChunked method " . $logger->getEntrySeparator() .
                "Code: " . $e->getCode() . $logger->getEntrySeparator() .
                "Message: " . $e->getMessage();
        }

        $logger->write($logString);
        $this->_doCleanUp();

        return $result;
    }

    protected function _doCleanUp()
    {
        if (null !== $this->_tempFilename && is_file($this->_tempFilename)) {
            @unlink($this->_tempFilename);
        }
    }

    protected function _preloadFileResource($url)
    {
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_NOBODY, 1);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
        curl_exec($curl);
        $info = curl_getinfo($curl);
        curl_close($curl);

        if ($info['http_code'] != 200) {
            throw new Exception("Server responsed with code not equal to 200. Target URL {$url}", $info['http_code']);
        }

        if ($info['download_content_length'] == -1) {
            throw new Exception("Body part is absent. Target URL {$url}", $info['http_code']);
        }

        if (empty($info['url'])) {
            throw new Exception("Download url is empty. Target URL {$url}", $info['http_code']);
        }

        $vkfname = tempnam(MODULE_DATAPATH . DS ."tmp", "VK");
        $vkfile = fopen($vkfname, "ab");

        $readHandle = fopen($info['url'], "rb");
        while (!feof($readHandle)) {
            fwrite($vkfile, fread($readHandle, 4194304));
        }
        @fclose($readHandle);
        @fclose($vkfile);

        if (filesize($vkfname) != $info['download_content_length']) {
            @unlink($vkfname);
            throw new Exception("The size of just written file is not equal to download_content_length header", $info['http_code']);
        }

        return $vkfname;
    }

    protected function _doChunkedUpload($file, $name = null)
    {

        if (strpos($file, "http") === 0) {
            $file = $this->_preloadFileResource($file);
            $this->_tempFilename = $file;
        }

        if (!file_exists($file)) {
            throw new Exception("Local file '" . $file . "' does not exist");
        }

        $fileSize = filesize($file);

        $fileTitle = (!empty($name)) ? $name : basename($file);

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file);
        finfo_close($finfo);

        $body = "{\"title\":\"{$fileTitle}\"}" ;

        $headers = array(
            "Content-Length: " . strlen($body),
            "Content-Type: application/json; charset=UTF-8",
            "X-Upload-Content-Type: {$mimeType}",
            "X-Upload-Content-Length: " . $fileSize
        );

        $response = $this->_session->fetch("POST", $this->_commonAPIURL, "/upload/drive/v2/files?uploadType=resumable", $headers, null, $body);

        if ($response['code'] != 200) {
            throw new Exception("Unable to start a resumable session", $response['code']);
        }

        if (!isset($response['headers']['location'])) {
            throw new Exception("Invalid resumable session URI");
        }

        $location = $response['headers']['location'];
        $url = strstr($location, '/upload/drive/v2/files');


        $offset = 0;
        $chunkSize = 256*1024*4*10;

        while (true) {

            $chunk = file_get_contents($file, false, null, $offset, $chunkSize);

            $contentLength = strlen($chunk);

            $headers = array(
                "Content-Length: " . $contentLength,
                "Content-Type: {$mimeType}",
                "Content-Range: bytes " . $offset . "-" . ($offset+$contentLength-1) . "/" . $fileSize
            );

            try {

                $response = $this->_session->fetch("PUT", $this->_commonAPIURL, $url, $headers, null, $chunk);

                if ($response['code'] == 201 || $response['code'] == 200 ) {
                    return $response['body'];
                }

                if (!isset($response['headers']['range'])) {
                    throw new Exception("Range header not returned");
                }

                if (preg_match("/bytes=[0-9]-([0-9]+)/i", $response['headers']['range'], $match)) {
                    $offset = intval($match[1]) + 1;
                } else {
                    throw new Exception("Can't retrieve new offset value from range header");
                }

            } catch(Sockets_Exception $se) {


            } catch(Exception $e) {

                $code = $e->getCode();
                if ($code >= 500 || $code == 324) {
                    $headers = array(
                        "Content-Length: 0",
                        "Content-Range: bytes */" . $fileSize
                    );
                    sleep(5);
                    $response = $this->_session->fetch("PUT", $this->_commonAPIURL, $url, $headers, null, null);

                    if ($response['code'] == 201 || $response['code'] == 200 ) {
                        return $response['body'];
                    }

                    if ($response['code'] == 308) {
                        if (!isset($response['headers']['range'])) {
                            throw new Exception("Range header not returned");
                        }

                        if (preg_match("/[0-9]-([0-9]+)/i", $response['headers']['range'], $match)) {
                            $offset = intval($match[1]) + 1;
                            continue;
                        } else {
                            throw new Exception("Can't retrieve offset value for upload resuming");
                        }
                    }

                } else {
                    throw $e;
                }
            }
        }

        return null;
    }
}

?>