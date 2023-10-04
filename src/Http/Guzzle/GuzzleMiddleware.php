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

    // TODO
    public static function reauthenticate(
        ClientRepo $repo,
        ClientAuthService $authService,
        LogService $logger,
    ): \Closure {
        $maxRetries = 1;
        return function (callable $handler) use ($repo, $authService, $maxRetries, $logger) {
            return function (
                RequestInterface $request,
                array $options
            ) use (
                $repo,
                $authService,
                $handler,
                $logger,
                $maxRetries
            ) {
                return $handler($request, $options)->then(
                    function (ResponseInterface $response) use (
                        $repo,
                        $authService,
                        $request,
                        $handler,
                        $options,
                        $logger,
                        $maxRetries
                    ) {
                        if (ApiUtils::isNabuproApiInvalidToken($request, $response)) {
                            $logger->debug('reauthenticate', 'Invalid nabupro token');
                            if (!isset($options['reauth'])) {
                                $options['reauth'] = 0;
                            }
                            if ($options['reauth'] < $maxRetries) {
                                $options['reauth']++;

                                $logger->debug('reauthenticate', 'Reauthenticate client');

                                $client = $repo->find((int) $request->getHeader('clientId')[0]);
                                $authService->authenticate($client);

                                $request = $request->withHeader(
                                    'Authorization',
                                    $client->getApiClientToken()
                                );

                                $logger->debug('reauthenticate', 'Reauthenticate completed, resend request');

                                return $handler($request, $options);
                            }
                        }

                        $logger->debug('reauthenticate', 'Valid token, process request');

                        $response->getBody()->rewind();
                        return $response;
                    }
                );
            };
        };
    }


}