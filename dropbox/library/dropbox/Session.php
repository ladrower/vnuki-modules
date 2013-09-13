<?php namespace Vnuki\Dropbox;
/**
 *
 */
class Dropbox_Session extends Dropbox_RESTClient
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
     * Application Access Type
     * @var
     */
    private $access;

    /**
     * Dropbox token
     * @var
     */
    private $token = null;

    /**
     * @var
     */
    private $oauthSignatureMethod = "HMAC-SHA1";

    /**
     * OAuth API version implemented by this class
     * @var
     */
    private $oauthVersion = "1.0";

    /**
     * @var
     */
    private $oauthBaseURL = "https://api.dropbox.com/1";

    /**
     * Default constructor
     *
     * @param  string  $key     Application Key provided by Dropbox
     * @param  string  $secret  Application Secret provided by Dropbox
     * @param  string  $access  Application access type (can be 'dropbox' or 'app_folder')
     * @return void
     */
    function __construct($key, $secret, $access) {
        parent::__construct();

        $this->key = $key;
        $this->secret = $secret;
        $this->access = $access;
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
     * Gets the access type allowed for this session
     *
     * @return string
     */
    public function getAccessType() {
        return $this->access;
    }

    /**
     * URL encode each parameter to RFC3986 for use in the base string
     *
     * @param  array $params  Associative array of parameters to encode
     * @return array
     */
    protected function encodeParams($params) {
        ksort($params);
        $encoded = array();

        foreach ($params as $param => $value) {
            if ($value !== null) {
                $encoded[] = rawurlencode($param) . '=' . rawurlencode($value);
            }
            else {
                unset($params[$param]);
            }
        }

        return $encoded;
    }

    /**
     * Generates OAuth signature for a request
     *
     * @param  string $method  The HTTP method of the request
     * @param  string $url     The destination URL
     * @param  array  $params  The parameters to send
     * @return string
     */
    protected function getSignature($method, $url, $params) {

        $encoded = $this->encodeParams($params);

        $sigBase = $method . "&" . rawurlencode($url) . "&";

        $sigBase .= rawurlencode(implode("&", $encoded));

        if (isset($this->accessToken["oauth_token_secret"])) {
            // Signature key for all the other requests
            $sigKey = $this->secret . "&" . $this->accessToken["oauth_token_secret"];
        }
        else {
            // Signature key for temporary token requests
            $sigKey = $this->secret . "&";
        }

        // Compute signature
        $oauthSig = base64_encode(hash_hmac("sha1", $sigBase, $sigKey, true));

        return $oauthSig;

    }

    /**
     * Request a temparary unprivileged request token from Dropbox
     *
     * @return  string  The token
     */
    public function obtainRequestToken() {
        $requestTokenUrl = $this->oauthBaseURL . "/oauth/request_token";
        $oauthTimestamp = time();
        $nonce = md5(mt_rand());

        $params = array(
            "oauth_consumer_key" => $this->key,
            "oauth_nonce" => $nonce,
            "oauth_signature_method" => $this->oauthSignatureMethod,
            "oauth_timestamp" => $oauthTimestamp,
            "oauth_version" => $this->oauthVersion,
        );
        //ksort($params);

        // Generate a signature for the request parameters
        $oauthSig = $this->getSignature("GET", $requestTokenUrl, $params);

        // Enqueue the signature to the request
        $params["oauth_signature"] = $oauthSig;

        // Build the full URL and call the API
        $query = http_build_query($params, "", "&");
        $response = $this->get($requestTokenUrl . "?" . $query, null, true);

        return $response["body"];

    }

    /**
     * Builds the URL needed to authorize the application
     *
     * @param  string  $token     The temporary unprivileged access token
     * @param  string  $callback  An URL to redirect the user when the authorization is granted
     * @param  string  $locale    Locale of the user/app
     * @return string
     */
    public function buildAuthorizeURL($token, $callback = null, $locale = "en-US") {

        $url = "https://www.dropbox.com/1/oauth/authorize?" . http_build_query($token, "", "&");

        if (!empty($callback)) {
            $url .= "&oauth_callback=" . rawurlencode($callback);
        }

        if (!empty($locale)) {
            $url .= "&locale=" . rawurlencode($locale);
        }

        return $url;
    }

    /**
     * Request a permanent access token
     *
     * @param  array  $token  The token returned from the authorization URL
     * @return array
     */
    public function obtainAccessToken($token) {

        $this->accessToken = $token;

        $nonce = md5(mt_rand());

        $requestTokenUrl = $this->oauthBaseURL . "/oauth/access_token";

        $oauthTimestamp = time();

        // Prepare the standard request parameters
        $params = array(
            "oauth_consumer_key" => $this->key,
            "oauth_token" => $this->accessToken["oauth_token"],
            "oauth_signature_method" => $this->oauthSignatureMethod,
            "oauth_version" => $this->oauthVersion,
            // Generate nonce and timestamp if signature method is HMAC-SHA1
            "oauth_timestamp" => ($this->oauthSignatureMethod == "HMAC-SHA1") ? time() : null,
            "oauth_nonce" => ($this->oauthSignatureMethod == "HMAC-SHA1") ? $nonce : null
        );
        //ksort($params);

        $oauthSig = $this->getSignature("POST", $requestTokenUrl, $params);

        $params["oauth_signature"] = $oauthSig;

        $response = $this->post($requestTokenUrl, $params, null, true);
        return $response["body"];

    }

    public function resetApiClient() {
        $this->reset();
    }

    /**
     *
     * @param  string   $method  The HTTP method
     * @param  string   $url     The Dropbox base API URL
     * @param  string   $api     The Dropbox API method to call
     * @param  string   $args    Arguments to pass
     * @param  boolean  $raw     If true doesn't decode JSON data
     */
    public function fetch($method, $url, $api, $args = array(), $raw = false) {

        $nonce = ($this->oauthSignatureMethod == "HMAC-SHA1") ? md5(mt_rand()) : null;
        $oauthTimestamp = ($this->oauthSignatureMethod == "HMAC-SHA1") ? time() : null;

        // Prepare the standard request parameters
        $params = array(
            "oauth_consumer_key" => $this->key,
            "oauth_token" => $this->accessToken["oauth_token"],
            "oauth_signature_method" => $this->oauthSignatureMethod,
            "oauth_version" => $this->oauthVersion,
            // Generate nonce and timestamp if signature method is HMAC-SHA1
            "oauth_timestamp" => $oauthTimestamp,
            "oauth_nonce" => $nonce
        );

        $inputFile = null;
        if ("PUT" == $method && $args["inputfile"]) {
            $inputFile = $args["inputfile"];
            unset($args["inputfile"]);
        }

        // Merge with the additional request parameters
        $params = array_merge($params, $args);
        //ksort($params);

        $oauthSig = $this->getSignature($method, $url . $api, $params);

        $params["oauth_signature"] = $oauthSig;

        $query = "";

        switch ($method) {
            case "POST":
                $response = $this->post($url . $api, $params, null, $raw);
                break;

            case "PUT":
                $query = "?" . http_build_query($params, "", "&");
                $response = $this->put($url . $api . $query, $inputFile, null, $raw);
                break;

            case "GET":
            default:
                $query = "?" . http_build_query($params, "", "&");
                $response = $this->get($url . $api . $query, null, $raw);
                break;
        }

        return $response;
    }
}