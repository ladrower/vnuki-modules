<?php namespace Vnuki\Dropbox;

class Authorization_Controller extends Controller_Abstract
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

        $requestToken = $this->getRequestToken();
        if ($requestToken) {
            parse_str($requestToken, $token);
            $this->_saveRequestToken($token);

            $url = $this->_session->buildAuthorizeURL(
                $token,
                $this->buildUrl('access_token', 'authorization', array('redirect' => $this->getRedirectReferer() )),
                "en-US"
            );
            $this->redirect($url);
        }
    }

    protected function methodAccessToken()
    {
        $oauth_token = $this->_helper->getRequestVar('oauth_token');
        $uid = $this->_helper->getRequestVar('uid');
        if (null !== $oauth_token && null !== $uid) {
            $token = array(
                'oauth_token' => $oauth_token,
                'oauth_token_secret' => $this->_session->requestToken['oauth_token_secret']
            );

            try {
                $accessToken = $this->getAccessToken($token);
            } catch(Exception $e) {
                // Log exception here $e->getCode() $e->getMessage()
                $this->relogin();
            }
            parse_str($accessToken, $token);
            $this->_saveAccessToken($token);

            $redirect = $this->_helper->getRequestVar('redirect');
            if (null !== $redirect) {
                $this->redirect($redirect);
            } else {
                // close window with js
            }

            // User is authorized. TODO: redirect to success page or close window...

        } elseif (null !== $this->_helper->getRequestVar('not_approved')) {
            // User choosed not to authorize the application
            $this->_rejectAccessToken();
            // close window with js

        }
    }

    protected function _saveRequestToken($token)
    {
        $this->_session->requestToken = $token;
    }

    protected function _saveAccessToken($token)
    {
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
     * Request a permanent access token
     *
     * @param  array  $token  The token returned from the authorization URL
     * @return array
     */
    public function getAccessToken($token)
    {
        return $this->_session->obtainAccessToken($token);
    }

    /**
     * Request a temparary unprivileged request token from Dropbox
     *
     * @return  string  The token
     */
    public function getRequestToken()
    {
        return $this->_session->obtainRequestToken();
    }
}

?>