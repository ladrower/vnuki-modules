<?php namespace Vnuki\Google\Drive;

session_start();

require_once("config.php");
require_once(MODULE_APPLICATIONPATH . DS . "controllers" . DS . "controllerAbstract.php");
require_once(MODULE_LIBPATH . DS . "drive" . DS . "RESTClient.php");
require_once(MODULE_LIBPATH . DS . "drive" . DS . "Session.php");


/* 
 * Google Drive Module Bootstrap class
 * Contains two public methods: 
 *      run() - for outer use (outer http request processing)
 *      retrieveController() - for inner use (delegates controller object to the caller)
*/
class Bootstrap
{
	protected $_controller;
    protected $_validControllers;

	public function __construct()
    {
    	$this->_validControllers = array('authorization', 'account', 'files');
        $this->_init();
    }

    protected function _init()
    {
    	$controller = $this->_getRequestVar('controller');

        if (null !== $controller) {
            $this->_loadController($controller);
        }
    }

    protected function _loadController($name)
    {   
        if (!in_array($name, $this->_validControllers)) {
            return false;
        }

        $controllerPath = MODULE_APPLICATIONPATH ."/controllers/". $name ."Controller.php";
        $className = __NAMESPACE__ . "\\" . ucfirst($name) ."_Controller";

        try {

            if (!file_exists($controllerPath)) {
                throw new Exception ('Controller file does not exist');
            }
            @require_once($controllerPath);

            if (!class_exists($className)) {
                throw new Exception ('Controller class does not exist');
            }
            $this->_controller = new $className();
            return true;
        } catch(Exception $e) {
            echo "Not implemented";
        }

        return false;
    }


	protected function _getRequestVar($name)
    {
        return (isset($_GET[$name])) ? $_GET[$name] : null;
    }

	public function run()
	{
		if ($this->_controller) {
            try {
                $this->_controller->processMethod();
            } catch (Exception $e) {

                if ($e->getMessage() == "Invalid Credentials") {
                    exit("User did not grant access to this component");
                }

                $this->_controller->relogin(
                    array(
                        'action' => $this->_getRequestVar('method'), 
                        'controller' => $this->_getRequestVar('controller')
                    )
                );
            }
        }
	}

    public function retrieveController($name)
    {
        if ($this->_loadController($name)) {
            return $this->_controller;
        }
        return null;
    }
}

class Exception extends \Exception
{
    
}

?>