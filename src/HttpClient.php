<?php
/**
 * @author: ZhaQiu <34485431@qq.com>
 * @time: 2018/12/27
 */

namespace KOF\Http;

use CURLFile;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Promise\Promise;
use KOF\Http\Contracts\ClientInterface;
use KOF\Http\Exception\HttpClientException;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Promise as GuzzlePromise;

/**
 * Class HttpClient
 * @package KOF\Http
 */
class HttpClient extends Client implements ClientInterface
{

    /**
     * @var Client[]
     */
    protected $promises;

    /**
     * Async Request Key
     *
     * @var string
     */
    protected $atomic;

    /**
     * @var array
     */
    protected $fallback = [];

    /**
     * @param string $method
     * @param string $uri
     * @param array $parameters
     * @param array $options
     * @return mixed
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function invoke(string $method, string $uri, array $parameters = [], array $options = [])
    {
        $options = array_merge([
            'connect_timeout' => 5,
            'timeout' => 5,
            'http_errors' => false,
        ], $options);
        $response = $this->request(
            $method,
            $uri,
            $this->createRequestOptions($method, $parameters, $options)
        );

        return Response::createFromResponse($response);
    }

    /**
     * @param string $method
     * @param string $uri
     * @param array $parameters
     * @param array $options
     * @return $this
     */
    public function invokeAsync(string $method, string $uri, array $parameters = [], array $options = [])
    {
        $options = array_merge([
            'connect_timeout' => 5,
            'timeout' => 2,
            'http_errors' => false,
        ], $options);

        if (isset($options['atomic'])) {
            $this->atomic = $options['atomic'];
        } else {
            throw new HttpClientException('the options atomic must be request');
        }

        $options = $this->createRequestOptions($method, $parameters, $options);
        switch (strtoupper($method)) {
            case 'GET':
                $this->promises[$this->atomic] = $this->getAsync($uri, $options);
                break;
            case 'POST':
                $this->promises[$this->atomic] = $this->postAsync($uri, $options);
                break;
            case 'PUT':
                $this->promises[$this->atomic] = $this->putAsync($uri, $options);
                break;
            case 'PATCH':
                $this->promises[$this->atomic] = $this->patchAsync($uri, $options);
                break;
            case 'DELETE':
                $this->promises[$this->atomic] = $this->deleteAsync($uri, $options);
                break;
            case 'HEAD':
                $this->promises[$this->atomic] = $this->headAsync($uri, $options);
                break;
        }

        return $this;
    }

    /**
     * @param \Closure $closure
     * @param null $nodeMsg
     * @return $this|ClientInterface
     */
    public function fallback(\Closure $closure)
    {
        $this->fallback[$this->atomic] = $closure;

        return $this;
    }


    /**
     * @return array
     * @throws \Throwable
     */
    public function sendInvokeAsync()
    {
        if ($this->promises) {
            foreach ($this->promises as $key => &$promise) {
                /**
                 * @var $promise Promise
                 */
                $promise->then(
                    function (ResponseInterface $response) {
                        return Response::createFromResponse($response);
                    },
                    function (RequestException $exception) use ($key) {
                        if (isset($this->fallback[$key])) {
                            return $this->fallback[$key]();
                        }

                        throw new HttpClientException($exception);
                    }
                );
            }
        }

        $results = GuzzlePromise\unwrap($this->promises);
        $this->promises = [];
        $this->fallback = [];
        $this->atomic = null;

        return $results;
    }

    /**
     * @param $method
     * @param array $parameters
     * @param array $options
     * @return array
     */
    protected function createRequestOptions($method, $parameters = [], array $options = [])
    {
        if ('GET' !== $method) {
            $multipart = $this->createMultipart($parameters);
            if (!empty($multipart)) {
                $options['multipart'] = $multipart;
            }
        } else {
            $options['query'] = $parameters;
        }

        return $options;
    }

    /**
     * @param array $parameters
     * @param string $prefix
     * @return array
     */
    protected function createMultipart(array $parameters, $prefix = '')
    {
        $return = [];
        foreach ($parameters as $name => $value) {
            $item = [
                'name' => empty($prefix) ? $name : "{$prefix}[{$name}]",
            ];
            switch (true) {
                case (is_object($value) && ($value instanceof CURLFile)):
                    $item['contents'] = fopen($value->getFilename(), 'r');
                    $item['filename'] = $value->getPostFilename();
                    $item['headers'] = [
                        'content-type' => $value->getMimeType(),
                    ];
                    break;
                case (is_string($value) && is_file($value)):
                    $item['contents'] = fopen($value, 'r');
                    break;
                case is_array($value):
                    $return = array_merge($return, $this->createMultipart($value, $item['name']));
                    continue 2;
                default:
                    $item['contents'] = $value;
            }
            $return[] = $item;
        }

        return $return;
    }
}
