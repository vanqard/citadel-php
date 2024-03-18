<?php
namespace CitadelClient;

class SessionResolveBearerRequest
{
    public function __construct(
        public string $token
    ) {
    }
}
