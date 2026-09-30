<?php

namespace App\Services\Contacts;

use Psr\Http\Client\RequestExceptionInterface;
use Psr\Http\Message\RequestInterface;
use RuntimeException;
use Throwable;

final class ContactTransportException extends RuntimeException implements RequestExceptionInterface
{
    public function __construct(private RequestInterface $request, string $message, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    public function getRequest(): RequestInterface
    {
        return $this->request;
    }
}
