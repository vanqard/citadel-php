<?php
namespace CitadelClient;

class SessionResolveResponse
{
    public function __construct(
        public Recommended $recommended,
        public ?ResolvedSession $session = null
    ) {
    }
}
