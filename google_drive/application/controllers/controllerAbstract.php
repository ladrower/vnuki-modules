<?php namespace Vnuki\Google\Drive;

abstract class Controller_Abstract
{
    /**
     * Method invoked by external http call
     * @var
     */
    protected $_method;

    /**
     * Controller_Helper object
     * @var
     */
    protected $_helper;

    /**
     * Google Drive Session
     * @var
     */
    protected $_session;

    /**
     * Redirect parameter name
     * @var
     */
    protected $_redirectParam = "state";

    /**
     * Common Google API URL
     * @var
     */
    protected $_commonAPIURL = "https://www.googleapis.com";



    public function __construct()
    {
        $this->_session = new Google_Session(APP_KEY, APP_SECRET);
        $this->_helper = Controller_Helper::getSingleton();
        $this->setRequestedMethod();
    }

    protected function setRequestedMethod()
    {
        $this->_method = $this->_helper->getRequestVar('method');
    }

    protected function invalidMethod()
    {

    }

    public function getMethod()
    {
        return $this->_method;
    }

    public function redirect($url)
    {
        header("Location: " . $url);
        exit;
    }

    public function buildUrl($action, $controller, $params = null)
    {
        $url = MODULE_WEBROOT . "{$controller}/{$action}";
        if ($params !== null) {
            $url .= "?" . http_build_query($params, "", "&");
        }
        return $url;
    }

    public function redirector($action, $controller, $params = null)
    {
        $this->redirect($this->buildUrl($action, $controller, $params));
    }

    public function relogin($redirect = null, $sleep = 0)
    {
        if ($sleep > 0) {
            sleep($sleep);
        }
        $params = ($redirect) ? array($this->_redirectParam => $this->buildUrl($redirect['action'], $redirect['controller'])) : null;
        $this->redirector('authorize', 'authorization', $params);
    }

    public function getRedirectReferer()
    {
        $redirect = $this->_helper->getRequestVar($this->_redirectParam);
        if (null === $redirect) {
            $redirect = $this->_helper->getServerVar('HTTP_REFERER');
        }
        return empty($redirect) ? null : $redirect;
    }

    public function isAuthorized()
    {
        return $this->_session->isAuthorized();
    }

    public function isRejected()
    {
        return $this->_session->isRejected();
    }

    public abstract function processMethod();

}

class Controller_Helper
{
    private static $instance;

    public static function getSingleton()
    {
        if (self::$instance == NULL)
            self::$instance = new self();

        return self::$instance;
    }

    private function __construct()
    {

    }

    public function getRequestVar($name)
    {
        return (isset($_GET[$name])) ? $_GET[$name] : null;
    }

    public function getServerVar($name)
    {
        return (isset($_SERVER[$name])) ? $_SERVER[$name] : null;
    }
}

class Controller_Logger
{
    protected $logFolder;

    protected $logFileName;

    protected $logFileExtension = 'txt';

    protected $entrySeparator = ' | ';

    public function __construct()
    {
        $this->logFolder = MODULE_LOGPATH . '/';
        $this->logFileName = 'Log_' . date('d_F_Y');
    }

    public function write($string, $logname = '')
    {
        $data = $string . $this->getEntrySeparator() . date("H:i:s") . ";\r\n";
        $logPath = $this->logFolder . ( $logname != '' ? $logname . '_' . date('d_F_Y') : $this->logFileName ) . '.' . $this->logFileExtension;
        @file_put_contents($logPath, $data, FILE_APPEND);
    }

    public function getEntrySeparator()
    {
        return $this->entrySeparator;
    }
}

?>