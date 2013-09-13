<?php namespace Vnuki\Dropbox;

class Account_Controller extends Controller_Abstract
{
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
            case "info":
                $this->methodInfo();
                break;

            default:
                $this->invalidMethod();
        }

    }

    protected function methodInfo()
    {
        $info = $this->getInfo();
        return $info;
    }

    public function getInfo()
    {
        $response = $this->_session->fetch("GET", $this->_dropboxAPIURL, "/account/info");
        return $response["body"];
    }
}

?>