<?php

namespace Sheadawson\Linkable\Tests\Stubs;

use GuzzleHttp\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use SilverStripe\Dev\TestOnly;

/**
 * A PSR-18 client for embed/embed that answers from canned bodies keyed by URL path, so the
 * EmbeddedObject tests never touch the network.
 */
class StubHttpClient implements ClientInterface, TestOnly
{
    /**
     * @var array Map of URL path => [content type, body]
     */
    private array $responses;

    /**
     * @var array URLs requested, in order
     */
    public array $requested = [];

    public function __construct(array $responses)
    {
        $this->responses = $responses;
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->requested[] = (string) $request->getUri();
        $path = $request->getUri()->getPath();

        if (!isset($this->responses[$path])) {
            return new Response(404, ['Content-Type' => 'text/plain'], 'Not found');
        }

        [$contentType, $body] = $this->responses[$path];

        return new Response(200, ['Content-Type' => $contentType], $body);
    }
}
