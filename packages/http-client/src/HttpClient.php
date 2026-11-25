<?php

declare(strict_types=1);

namespace Core\Http;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\RequestOptions;
use InvalidArgumentException;
use LogicException;
use Psr\Http\Message\UriInterface;

final readonly class HttpClient
{
    private ?UriInterface $baseUri;

    public function __construct(private ClientInterface $transport = new Client(), string $baseUri = '')
    {
        $this->baseUri = $baseUri === '' ? null : self::parseBaseUri($baseUri);
    }

    private static function parseBaseUri(string $baseUri): UriInterface
    {
        $uri = new Uri($baseUri);
        if (
            !in_array($uri->getScheme(), ['http', 'https'], true)
            || $uri->getHost() === ''
            || $uri->getUserInfo() !== ''
            || $uri->getQuery() !== ''
            || $uri->getFragment() !== ''
        ) {
            throw new InvalidArgumentException('Expected a trusted HTTP or HTTPS base URL');
        }

        $prefix = ClientInput::path($uri->getPath());
        return $uri->withPath($prefix === '' ? '/' : '/' . rtrim($prefix, '/') . '/');
    }

    public function forBaseUri(string $baseUri): self
    {
        if ($baseUri === '') {
            throw new InvalidArgumentException('Expected a trusted HTTP or HTTPS base URL');
        }

        return new self($this->transport, $baseUri);
    }

    public function get(string $path, array $query = []): ClientResponse
    {
        return $this->send('GET', $path, $query);
    }

    public function post(string $path, array $json = [], array $query = []): ClientResponse
    {
        return $this->send('POST', $path, $query, $json);
    }

    public function put(string $path, array $json = [], array $query = []): ClientResponse
    {
        return $this->send('PUT', $path, $query, $json);
    }

    public function patch(string $path, array $json = [], array $query = []): ClientResponse
    {
        return $this->send('PATCH', $path, $query, $json);
    }

    public function delete(string $path, array $query = []): ClientResponse
    {
        return $this->send('DELETE', $path, $query);
    }

    public function head(string $path, array $query = []): ClientResponse
    {
        return $this->send('HEAD', $path, $query);
    }

    public function options(string $path, array $query = []): ClientResponse
    {
        return $this->send('OPTIONS', $path, $query);
    }

    private function send(string $method, string $path, array $query, ?array $json = null): ClientResponse
    {
        $baseUri = $this->baseUri ?? throw new LogicException('Select a service URL with forBaseUri()');
        $uri = $baseUri
            ->withPath($baseUri->getPath() . ClientInput::path($path))
            ->withQuery(ClientInput::query($query));
        $options = [
            RequestOptions::ALLOW_REDIRECTS => false,
            RequestOptions::HTTP_ERRORS => false,
            RequestOptions::CONNECT_TIMEOUT => 3.0,
            RequestOptions::TIMEOUT => 10.0,
            RequestOptions::VERIFY => true,
            RequestOptions::PROXY => '',
            RequestOptions::HEADERS => ['Accept' => 'application/json'],
        ];
        if ($json !== null) {
            $options[RequestOptions::BODY] = ClientInput::json($json);
            $options[RequestOptions::HEADERS]['Content-Type'] = 'application/json';
        }

        return ClientResponse::fromResponse($this->transport->request($method, $uri, $options));
    }
}
