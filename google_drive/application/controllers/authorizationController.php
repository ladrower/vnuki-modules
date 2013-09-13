<?php namespace Vnuki\Google\Drive;


class Authorization_Controller extends Controller_Abstract
{

	const SCOPE_DRIVE_FILE = "https://www.googleapis.com/auth/drive.file";
	const SCOPE_USERINFO_PROFILE = "https://www.googleapis.com/auth/userinfo.profile";


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
            case "authorize":
                $this->methodAuthorize();
            break;
            case "access_token":
                $this->methodAccessToken();
            break;

            default:
            	$this->invalidMethod();
        }
		
	}

	protected function methodAuthorize()
	{
		$this->_deleteAccessToken();

		$params = array(
			'client_id' 			=> APP_KEY,
	    	'scope' 				=> implode(" ", array(self::SCOPE_DRIVE_FILE,)),
	    	'response_type' 		=> 'code',
	    	$this->_redirectParam	=> $this->getRedirectReferer()
    	);
		
		$url = $this->_session->buildAuthorizeURL(
            $params, 
            $this->buildUrl('access_token', 'authorization')
        );

		$this->redirect($url);
	}

	protected function methodAccessToken()
	{
		$code = $this->_helper->getRequestVar('code');

		if (null !== $code) {
		
			try {
				$accessToken = $this->exchangeAccessToken($code, $this->buildUrl('access_token', 'authorization'));
				$this->_saveAccessToken($accessToken);
			} catch(Exception $e) {
				// Log exception here $e->getCode() $e->getMessage()
				$this->relogin(null, 5);
			}

			$redirect = $this->_helper->getRequestVar($this->_redirectParam);
			if (null !== $redirect) {
				$this->redirect($redirect);
			} else {
				// close window with js
			}

		} elseif (null !== $this->_helper->getRequestVar('error')) {
	
			$this->_rejectAccessToken();
		}
	}

    protected function _saveAccessToken($token)
    {
        if (!isset($token['access_token']) || empty($token['access_token']) ) {
        	throw new Exception("No AccessToken passed");	
        }

        if (!isset($token['expires_in']) || intval($token['expires_in']) <= 0 ) {
        	throw new Exception("Invalid expiration period");
        }

        if (!isset($token['token_type'])) {
        	throw new Exception("Invalid token_type");
        }

        $token['valid_till'] = time() + intval($token['expires_in']);

        $this->_session->accessToken = $token;
    }

    protected function _rejectAccessToken()
    {
        $this->_session->accessToken = array('not_approved' => 1);
    }

    protected function _deleteAccessToken()
    {
        unset($this->_session->accessToken);
    }

	/**
     * Exchange the authorization code for an access token
     *
     * @param  array  	$code  The code returned from the authorization URL
     * @param  string  	$redirect_uri  The URI registered with the application
     * @return array
     */
	public function exchangeAccessToken($code, $redirect_uri)
	{
		return $this->_session->obtainAccessToken($code, $redirect_uri);
	}

}



?>