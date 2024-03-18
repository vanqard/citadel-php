<?php
namespace CitadelClient;

class ResolveSessionResponse
{
    public function __construct(
        public Recommended $recommended,
        public ?ResolvedSession $session = null
    ) {
    }
}
