<?php
namespace CitadelClient;

class Recommended
{
    public function __construct(
        public string $action,
        public MultiValueHeaders $responseHeaders,
        public string $reason
    ) {
    }
}
