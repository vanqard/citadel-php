<?php
namespace CitadelClient;

class SessionResolveRequest
{
    public function __construct(
        public string $cookieHeader,
        public string $clientId,
        public string $clientSecret
    ) {
    }
}
