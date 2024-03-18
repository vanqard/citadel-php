<?php
namespace CitadelClient;

class SessionRevokeRequest
{
    public function __construct(
        public string $cookieHeader,
        public string $clientId,
        public string $clientSecret
    ) {
    }
}
