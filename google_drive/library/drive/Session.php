<?php namespace Vnuki\Google\Drive;
/**
 *
 */
class Google_Session extends Google_RESTClient
{
    /**
     * Application Key
     * @var
     */
    private $key;

    /**
     * Application Secret
     * @var
     */
    private $secret;

    /**
     * @var
     */
    private $oauthBaseURL = "https://accounts.google.com";

    /**
     * Default constructor
     *
     * @param  string  $key     Application Key provided by Google APIs Console
     * @param  string  $secret  Application Secret provided by Google APIs Console
     * @return void
     */
    function __construct($key, $secret) {
        parent::__construct();

        $this->key = $key;
        $this->secret = $secret;
    }

    public function __get($key) {
        if (isset( $_SESSION[__NAMESPACE__][$key] )) {
            return $_SESSION[__NAMESPACE__][$key];
        }
        return null;
    }

    public function __set($key, $value) {
        $_SESSION[__NAMESPACE__][$key] = $value;
    }

    public function __isset($key) {
        return isset($_SESSION[__NAMESPACE__][$key]);
    }


    public function __unset($key) {
        unset($_SESSION[__NAMESPACE__][$key]);
    }

    /**
     * Builds the URL needed to authorize the application
     *
     * @param  string  $params    An array parameters supported by the Google Authorization Server for web server applications
     * @param  string  $callback  An URL to redirect the user when the authorization is granted
     * @return string
     */
    public function buildAuthorizeURL($params, $callback = null) {

        $url = $this->oauthBaseURL . "/o/oauth2/auth?" . http_build_query($params, "", "&");

        if (!empty($callback)) {
            $url .= "&redirect_uri=" . rawurlencode($callback);
        }

        return $url;
    }

    /**
     * Exchange the authorization code for an access token
     *
     * @param  array    $code  The code returned from the authorization URL
     * @param  string   $redirect_uri  The URI registered with the application
     * @return array
     */
    public function obtainAccessToken($code, $redirect_uri) {

        $requestTokenUrl = $this->oauthBaseURL . "/o/oauth2/token";
        $body = array(
            'code'          => $code,
            'client_id'     => $this->key,
            'client_secret' => $this->secret,
            'redirect_uri'  => $redirect_uri,
            'grant_type'    => 'authorization_code'
        );

        $headers = array('Content-Type: application/x-www-form-urlencoded');

        $response = $this->post($requestTokenUrl, $headers, $body);

        return $response["body"];

    }

    /**
     *
     * @param  string   $method     The HTTP method
     * @param  string   $apiBase    The Google base API URL
     * @param  string   $apiMethod  The Google API method to call
     * @param  array    $headers    Headers to pass
     * @param  array    $params     Arguments to pass
     * @param  string   $body       Request body
     * @param  boolean  $raw        If true doesn't decode JSON data of response
     */
    public function fetch($method, $apiBase, $apiMethod, $headers = null, $params = null, $body = null, $raw = false) {

        if ($this->isAuthorized()) {
            $headers[] = "Authorization: {$this->accessToken['token_type']} {$this->accessToken['access_token']}";
        }

        if (null !== $params) {
            $querySeparator = (strpos($api, '?') === false) ? '?' : '&';
            $uri = $apiBase . $apiMethod . $querySeparator . http_build_query($params);
        } else {
            $uri = $apiBase . $apiMethod;
        }

        switch ($method) {
            case "POST":
                $response = $this->post($uri, $headers, $body, $raw);
                break;

            case "PUT":
                $response = $this->put($uri, $headers, $body, $raw);
                break;

            case "GET":
            default:
                $response = $this->get($uri, $headers, $raw);
                break;
        }

        return $response;
    }

    public function isAuthorized()
    {
        return isset($this->accessToken['access_token']) && $this->accessToken['valid_till'] > time();
    }

    public function isRejected()
    {
        return isset($this->accessToken['not_approved']) && $this->accessToken['not_approved'] == 1;
    }
}