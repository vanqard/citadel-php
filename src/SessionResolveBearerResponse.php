<?php
namespace CitadelClient;

class SessionResolveBearerResponse
{
    public function __construct(
        public ?ResolvedSession $session
    ) {
    }
}
