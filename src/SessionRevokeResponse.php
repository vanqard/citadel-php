<?php
namespace CitadelClient;

class SessionRevokeResponse
{
    public function __construct(
        public array $responseHeaders
    ) {
    }
}
