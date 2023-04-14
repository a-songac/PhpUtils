<?php

namespace ASongac\PhpUtils\Http\Guzzle;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class GuzzleMiddleware {

    public static function throttle(int $maxRetries = 1, string $retryAfterHeader = 'Retry-After') {
        return fn(callable $handler) =>
            fn(RequestInterface $request, array $options) => $handler($request, $options)->then(
                function (ResponseInterface $response) use (
                    $request,
                    $handler,
                    $options,
                    $maxRetries,
                    $retryAfterHeader
                ) {
                    if ($response->getStatusCode() == 429) {
                        if (!isset($options['retry'])) {
                            $options['retry'] = 0;
                        }
                        if ($options['retry'] < $maxRetries) {
                            $options['retry']++;
                            $delay = current($response->getHeader($retryAfterHeader));
                            sleep($delay);
                            return $handler($request, $options);
                        }
                    }
                    return $response;
                }
            );
    }

}