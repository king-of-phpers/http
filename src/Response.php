<?php
/**
 * @author: ZhaQiu <34485431@qq.com>
 * @time: 2018/12/27
 */

namespace KOF\Http;

use GuzzleHttp\Psr7\Response as GuzzleResponse;
use KOF\Http\Exception\ResponseException;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Class Response
 * @package KOF\Http
 */
class Response  extends SymfonyResponse
{

    public static function createFromResponse($response)
    {

        if ($response instanceof GuzzleResponse) {
            return new static(
                (string)$response->getBody(),
                $response->getStatusCode(),
                $response->getHeaders()
            );
        }

        throw new ResponseException('undefined response');
    }
}
