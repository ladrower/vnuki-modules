<?php namespace Vnuki\Dropbox;


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


    /**
     * Upload a file to the user's Dropbox
     *
     * The path is relative to a root (ex /<root>/<path>) that can be 'sandbox' or 'dropbox'
     *
     * @param  string   $file       The full path of the file to upload
     * @param  string   $path       The destination path (default = root)
     * @param  string   $name       Specifies a different name for the uploaded file
     * @param  boolean  $overwrite  Overwrite any existing file
     * @return array
     */
    public function putFile($file, $path = "/", $name = null, $overwrite = true)
    {
        // Check for file existence before
        if (!file_exists($file)) {
            throw new Exception("Local file '" . $file . "' does not exist");
        }

        // Dropbox has a 150MB limit upload for the API
        if (filesize($file) > 157286400) {
            throw new Exception("File exceeds 150MB upload limit");
        }

        $args = array(
            "overwrite" => (int) $overwrite,
            "inputfile" => $file
        );

        // Prepend the right access string to the desired path
        if ("dropbox" == APP_ACCESS_TYPE) {
            $path = "dropbox" . $path;
        } else {
            $path = "sandbox" . $path;
        }

        // Determine the full path
        if (!empty($name)) {
            $path .= $name;
        }
        else {
            $path .= basename($file);
        }

        // Get the raw response body
        $response = $this->_session->fetch("PUT", $this->_dropboxContentAPIURL, "/files_put/" . $path, $args);

        return $response["body"];
    }

    public function putFileChunked($file, $path = "/", $name = null, $overwrite = true)
    {
        $logger = new Controller_Logger();
        $result = null;

        try {
            $result = $this->_doChunkedUpload($file, $path, $name, $overwrite);
            $logString = "Chunked Upload result: " . var_export($result, true);
        } catch(Exception $e) {
            $logString = "Exception in putFileChunked method " . $logger->getEntrySeparator() . 
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
            throw new Exception("Server responsed with code not equal to 200", $info['http_code']);
        }

        if ($info['download_content_length'] == -1) {
            throw new Exception("Body part is absent", $info['http_code']);
        }

        if (empty($info['url'])) {
            throw new Exception("Download url is empty", $info['http_code']);
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

    protected function _doChunkedUpload($file, $path, $name, $overwrite)
    {
        
        if (strpos($file, "http") === 0) {
            $file = $this->_preloadFileResource($file);
            $this->_tempFilename = $file;
        }

        if (!file_exists($file)) {
            throw new Exception("Local file '" . $file . "' does not exist");
        }

        // First stage: Chunked upload
        $upload_id = $this->executeChunkedUpload($file);
        if (null == $upload_id) {
            throw new Exception("Chunked uploading failed");
        }

        $this->_session->resetApiClient();

        // Second stage: Commit upload
        $result = $this->commitChunkedUpload($upload_id, $file, $path, $name, $overwrite);

        return $result;
    }

    protected function executeChunkedUpload($file)
    {
        $handle = fopen($file, "rb");
        $offset = 0;
        $upload_id = null;
        $errors = 0;

        while (!feof($handle)) {

            $chunkSize = 4194304;
            $chunkfname = tempnam(MODULE_DATAPATH . DS ."tmp", "CHK");
            $chunk = fopen($chunkfname, "wb+");
            fwrite($chunk, fread($handle, $chunkSize));
            rewind($chunk);

            $args = array(
                "upload_id" => $upload_id,
                "inputfile" => $chunkfname,
                "offset" => $offset
            );

            $offset += filesize($chunkfname);

            try {

                $response = $this->_session->fetch("PUT", $this->_dropboxContentAPIURL, "/chunked_upload", $args);
                $upload_id = $response['body']['upload_id'];

                if (400 == $response['code']) {
                    $offset = (int) $response['body']['offset'];
                    fseek($handle, $offset);
                    $errors++;
                }

            } catch(Exception $e) {
                // log exception here
                @fclose($chunk);
                @unlink($chunkfname);
                return null;
            }
            @fclose($chunk);
            @unlink($chunkfname);
        }

        fclose($handle);

        return $upload_id;
    }

    protected function commitChunkedUpload($upload_id, $file, $path = "/", $name = null, $overwrite = true)
    {
        if ("dropbox" == APP_ACCESS_TYPE) {
            $path = "dropbox" . $path;
        } else {
            $path = "sandbox" . $path;
        }

        if (!empty($name)) {
            $path .= $name;
        }
        else {
            $path .= basename($file);
        }

        $args = array(
            "upload_id" => $upload_id,
            "overwrite" => (int) $overwrite
        );

        try{
            $response = $this->_session->fetch("POST", $this->_dropboxContentAPIURL, "/commit_chunked_upload/" . $path, $args);
        }  catch(Exception $e) {
            // log exception here
            return null;
        }

        if (400 == $response['code']) {
            return null;
        }

        return $response["body"];
    }


}



?>