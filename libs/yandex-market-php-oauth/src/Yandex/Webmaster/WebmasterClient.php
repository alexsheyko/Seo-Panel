<?php
/**
 * Yandex PHP Library

 *
 * @copyright NIX Solutions Ltd.
 * @link https://github.com/nixsolutions/yandex-php-library
 */

/**
 * @namespace
 */
namespace Yandex\Webmaster;

use Psr\Http\Message\UriInterface;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Exception\ClientException;
use Yandex\Common\AbstractServiceClient;
use Yandex\Webmaster\Exception\WebmasterRequestException;

/**
 *
 * @category Yandex
 * @package Webmaster
 *
 */
class WebmasterClient extends AbstractServiceClient
{
    //const DECODE_TYPE_DEFAULT = self::DECODE_TYPE_XML;

    private $version = 'v4';
    protected $serviceDomain = 'api.webmaster.yandex.net';

    public function setVersion($version)
    {
        $this->version = $version;

        return $this;
    }

    /**
     * @return string
     */
    public function getVersion()
    {
        return $this->version;
    }

    /**
     * @inheritdoc
     */
    public function getServiceUrl($resource = '')
    {
        return parent::getServiceUrl($resource) . '/' . $this->version;
    }

    /**
     * @param $path
     * @return string
     */
    public function getRequestUrl($path)
    {
        return parent::getServiceUrl() . $path;
    }

    /**
     * @param string $token access token
     */
    public function __construct($token = '')
    {
        $this->setAccessToken($token);
    }

    /**
     * Sends a request
     *
     * @param string              $method  HTTP method
     * @param string|UriInterface $uri     URI object or string.
     * @param array               $options Request options to apply.
     *
     * @throws \Exception|\GuzzleHttp\Exception\ClientException
     * @return Response
     */
    protected function sendRequest($method, $uri, array $options = [])
    {
        try {
            $response = $this->getClient()->request($method, $uri, $options);
        } catch (ClientException $ex) {
            $result = $ex->getResponse();
            $code = $result->getStatusCode();
            $message = $result->getReasonPhrase();

            throw new WebmasterRequestException(
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
        $result = explode(":", $response->getBody());
        array_shift($result);
        return implode(':', $result);
    }

    /**
     */
    public function directoryContents($path = '/', $offset = null, $amount = null, $depth = '1')
    {
        $response = $this->sendRequest(
            'PROPFIND',
            $path,
            [
                'headers' => [
                    'Depth' => $depth
                ],
                'query' => [
                    'offset' => $offset,
                    'amount' => $amount
                ]
            ]
        );

        $decodedResponseBody = $this->getDecodedBody($response->getBody());

        $contents = [];
        foreach ($decodedResponseBody->children('DAV:') as $element) {
            array_push(
                $contents,
                [
                    'href' => $element->href->__toString(),
                    'status' => $element->propstat->status->__toString(),
                    'creationDate' => $element->propstat->prop->creationdate->__toString(),
                    'lastModified' => $element->propstat->prop->getlastmodified->__toString(),
                    'displayName' => $element->propstat->prop->displayname->__toString(),
                    'contentLength' => $element->propstat->prop->getcontentlength->__toString(),
                    'resourceType' => $element->propstat->prop->resourcetype->collection ? 'dir' : 'file',
                    'contentType' => $element->propstat->prop->getcontenttype->__toString()
                ]
            );
        }
        return $contents;
    }

    /**
     *
     */
    public function listSites()
    {
        $user_id = $this->getUser();
        print $user_id;

        $response = $this->sendRequest(
            'GET',
            '/user/123/hosts',
        );

        $decodedResponseBody = $this->getDecodedBody($response->getBody());

        $info = (array) $decodedResponseBody->children('DAV:')->response->propstat->prop;
        return [
            'usedBytes' => $info['quota-used-bytes'],
            'availableBytes' => $info['quota-available-bytes']
        ];
    }


}
