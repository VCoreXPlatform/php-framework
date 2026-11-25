<?php

declare(strict_types=1);

namespace Core\Http;

use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;

final class ClientResponse extends Response
{
    public static function fromResponse(ResponseInterface $response): self
    {
        return new self(
            $response->getStatusCode(),
            $response->getHeaders(),
            $response->getBody(),
            $response->getProtocolVersion(),
            $response->getReasonPhrase(),
        );
    }

    public function json(): mixed
    {
        $stream = $this->getBody();
        $position = $stream->isSeekable() ? $stream->tell() : null;

        try {
            if ($position !== null) {
                $stream->rewind();
            }
            $body = $stream->getContents();
            return $body === '' ? null : json_decode($body, true, 512, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
        } finally {
            if ($position !== null) {
                $stream->seek($position);
            }
        }
    }
}
