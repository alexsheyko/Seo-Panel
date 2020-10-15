<?php

/***************************************************************************

https://github.com/nixsolutions/yandex-php-library/wiki/Yandex-OAuth

 ***************************************************************************/

// include google api module
//include_once(SP_LIBPATH . "/google-api-php-client/vendor/autoload.php");
include_once(SP_LIBPATH . "/yandex-market-php-oauth/vendor/autoload.php");
//include_once(SP_LIBPATH . "/OAuthClient.php");
include_once(SP_CTRLPATH . "/user-token.ctrl.php");

use Yandex\OAuth\OAuthClient;
use Yandex\Common\AbstractServiceClient;
use Yandex\Common\Exception\YandexException;

// class defines all google api controller functions
class YandexWebAPIController extends Controller{

	var $tokenCtrler;
	var $sourceName = 'yandex';
	
	/*
	 * contructor
	 */
	function __construct() {
		parent::__construct();
		$this->tokenCtrler = new UserTokenController();
	}
	
	/*
	 * function to create auth api client with credentials
	 */
	function createAuthAPIClient() {

		// if credentials defined
		if (defined('SP_YANDEX_API_CLIENT_ID') && defined('SP_YANDEX_API_CLIENT_SECRET') && SP_YANDEX_API_CLIENT_ID != '' && SP_YANDEX_API_CLIENT_SECRET != '') {

			//$client = new OAuthClient(SP_YANDEX_API_CLIENT_ID, SP_YANDEX_API_CLIENT_SECRET);
            //$client->authRedirect(true);

			$client = new OAuthClient();
			//$client->setApplicationName("SP_CHECKER");
			$client->setClientId(SP_YANDEX_API_CLIENT_ID);
			$client->setClientSecret(SP_YANDEX_API_CLIENT_SECRET);
			//$client->setAccessType('offline');
			//$redirectUrl = SP_WEBPATH . "/admin-panel.php?sec=connections&action=connect_return&category=" . $this->sourceName;
			//$client->setRedirectUri($redirectUrl);

			// set app scopes
			//$client = $this->setAppScopes($client);
			
		} else {
			$alertCtler = new AlertController();
			$alertInfo = array(
				'alert_subject' => "Click here to enter Google Auth Credentials",
				'alert_message' => "Error: Google Auth Credentials not set",
				'alert_url' => SP_WEBPATH ."/admin-panel.php?sec=google-settings",
				'alert_type' => "danger",
				'alert_category' => "reports",
			);
			$alertCtler->createAlert($alertInfo, false, true);
			return "Error: Yandex Auth Credentials not set.";
		}
		
		return $client;
		
	}
	
	
	/**
	 * function to get auth client
	 */
	function getAuthClient($userId) {
		
		$client = $this->createAuthAPIClient();
		
		// if client created successfully
		if (is_object($client)) {
			
			// get user token
			$tokenInfo = $this->tokenCtrler->getUserToken($userId, $this->sourceName);
			
			// if token not set for the user
			if (empty($tokenInfo['access_token'])) {
			    $spTextWebmaster = $this->getLanguageTexts('webmaster', $_SESSION['lang_code']);
			    $errorText = $spTextWebmaster["Error: Yandex api connection failed"] . ". ";
			    $errorText .= "<a href='".SP_WEBPATH ."/admin-panel.php?sec=connections' target='_blank'>{$spTextWebmaster['Click here to connect to your yandex account']}.</a>";
                $alertCtler = new AlertController();
                $alertInfo = array(
					'alert_subject' => $spTextWebmaster['Click here to connect to your yandex account'],
					'alert_message' => $spTextWebmaster["Error: Yandex api connection failed"],
					'alert_url' => SP_WEBPATH ."/admin-panel.php?sec=connections",
					'alert_type' => "danger",
					'alert_category' => "reports",
				);
                $alertCtler->createAlert($alertInfo, $userId);
			    return $errorText;
			}
			
			// set token info
			$tokenInfo['created'] = strtotime($tokenInfo['created']);
			//$client->setAccessToken($tokenInfo);                  /// google
			$client->setAccessToken($tokenInfo['access_token']);    /// yandex

			/*
			// check whether token expired, then refresh existing token
			if ($client->isAccessTokenExpired()) {
			
				try {
					$client->refreshToken($tokenInfo['refresh_token']);
					$newToken = $client->getAccessToken();
					$newTokenInfo = array();
					$newTokenInfo['created'] = date('Y-m-d H:i:s', $newToken['created']);
					$newTokenInfo['access_token'] = $newToken['access_token'];
					$newTokenInfo['token_type'] = $newToken['token_type'];
					$newTokenInfo['expires_in'] = $newToken['expires_in'];
					
					// comment refresh token update to test the perfomnace
					//$newTokenInfo['refresh_token'] = $newToken['refresh_token'];
					
					$this->tokenCtrler->updateUserToken($tokenInfo['id'], $newTokenInfo);
				} catch (Exception $e) {
					$err = $e->getMessage();
					return "Error: Refresh token - $err";
				}
				
			}
			*/
			
		}
		
		return $client;
		
	}
	
	/*
	 * function to setup app scopes(read write permissions)
	 */
	function setAppScopes($client) {
	    //$client->addScope([Google_Service_Webmasters::WEBMASTERS, Google_Service_AnalyticsReporting::ANALYTICS_READONLY]);
		return $client;
	}
	
	/*
	 * function to get auth url
	 */
	function getAPIAuthUrl($userId) {
		$ret = array('auth_url' => false);
		$client = $this->createAuthAPIClient();
		
		// if client created successfully
		if (is_object($client)) {
			
			try {
				//$authUrl = $client->createAuthUrl();
				$authUrl = $client->getAuthUrl();
				$ret['auth_url'] = $authUrl;
			} catch (Exception $e) {
				$err = $e->getMessage();
				$ret['msg'] = "Error: Create token - $err";								
			}
				
		} else {
			$ret['msg'] = $client;
		}
		
		return $ret;
		
	}
	
	/*
	 * function to create auth token
	 */
	function createUserAuthToken($userId, $authCode) {
		
		$ret = array('status' => false);
		$client = $this->createAuthAPIClient();
		
		// if client created successfully
		if (is_object($client)) {
		
			try {
				//$tkInfo = $client->fetchAccessTokenWithAuthCode($authCode);
				//$tkInfo = $client->getAccessTokenByYandexOAuth($authCode);
                try {
                    // осуществляем обмен
                    $client->requestAccessToken($authCode);
                } catch (AuthRequestException $e) {
                    //echo $ex->getMessage();
                    $err = $e->getMessage();
				    $ret['msg'] = "Error: token - $err";
                }
                // забираем полученный токен
                $tkInfo =  $client->getAccessTokenInfo();

				$tokenInfo['created'] = date('Y-m-d H:i:s', $tkInfo['created']);
				$tokenInfo['user_id'] = intval($userId);
				$tokenInfo['access_token'] = $tkInfo['access_token'];
				$tokenInfo['token_type'] = $tkInfo['token_type'];
				$tokenInfo['expires_in'] = $tkInfo['expires_in'];
				$tokenInfo['refresh_token'] = $tkInfo['refresh_token'];

				$tokenInfo['token_category'] = $this->sourceName;

				$this->tokenCtrler->insertUserToken($tokenInfo);
				$ret['status'] = true;
			} catch (Exception $e) {
				$err = $e->getMessage();
				$ret['msg'] = "Error: Create token - $err";
			}
			
		} else {
			$ret['msg'] = $client;
		}
		
		return $ret;
		
	}
	
	/*
	 * function to remove all user tokens
	 */
	function removeUserAuthToken($userId) {
		$ret = array('status' => false);
		
		try {
			
			$tokenInfo = $this->tokenCtrler->getUserToken($userId, $this->sourceName);
			
			if (!empty($tokenInfo['id'])) {
				$client = $this->createAuthAPIClient();
				$client->revokeToken($tokenInfo['access_token']);
			}
			
		} catch (Exception $e) {
			$err = $e->getMessage();
			$ret['msg'] = "Error: revoke token - $err";
		}
		
		$tokenInfo = $this->tokenCtrler->deleteAllUserTokens($userId, $this->sourceName);
		return $ret;
		
	}
}

class Yandex_Service_Webmasters extends AbstractServiceClient{

    protected $token = '';
    protected $version = 'v4';
    protected $serviceDomain = 'api.webmaster.yandex.net';

    public function setVersion($version)
    {
        $this->version = $version;

        return $this;
    }

    public function getVersion()
    {
        return $this->version;
    }

    public function getServiceUrl($resource = '')
    {
        return parent::getServiceUrl($resource) . '/' . $this->version;
    }

    public function getRequestUrl($path)
    {
        //return parent::getServiceUrl() . $path;
        return 'https://' . $this->serviceDomain . '/' .  $this->version . $path;
    }

    public function __construct($token = '')
    {
        $this->setAccessToken($token);
        $this->token = $token;
    }
    protected function getDecodedBody($body, $type = null)
    {
        if (!isset($type)) {
            $type = static::DECODE_TYPE_DEFAULT;
        }
        switch ($type) {
            case self::DECODE_TYPE_XML:
                return simplexml_load_string((string) $body);
            case self::DECODE_TYPE_JSON:
            default:
                //return json_decode((string) $body, true);
                return json_decode((string) $body, false);
        }
    }

	///////////////////////
	///////////////////////        WEBMASTER
	///////////////////////
    protected function sendRequest($method, $uri, array $options = [])
    {
        $url = $this->getRequestUrl($uri);
        try {
            //$response = $this->getClient()->request($method, $uri, $options);
            $options = array('http' => array(
                'method'  => $method,
                'header' => 'Authorization: OAuth '. $this->token
            ));
            $context  = stream_context_create($options);
            $response = file_get_contents($url, false, $context);

        } catch (ClientException $ex) {
            $result = $ex->getResponse();
            $code = $result->getStatusCode();
            $message = $result->getReasonPhrase();

            throw new YandexException(
                'Service responded with error code: "' . $code . '" and message: "' . $message . '"',
                $code
            );
        }

        return $response;
    }
    /**
     */
    public function getUser()
    {
        $response = $this->sendRequest(
            'GET',
            '/user'
        );
        $decoded = $this->getDecodedBody($response);
        return $decoded->user_id;
    }

    /**
     *
     */
    public function listSites()
    {
        $user_id = $this->getUser();


        $response = $this->sendRequest(
            'GET',
            '/user/' . $user_id . '/hosts',
        );

        $decoded = $this->getDecodedBody($response);

        return [
            'siteEntry' => $decoded->hosts,
            'user_id' => $user_id,
            'msg' => $user_id,
        ];
    }
			
}
?>