<?php
namespace CitadelClient;

class ResolvedValue
{
    public function __construct(
        public string $name,
        public string | int | bool | null $value,
        public string $from
    ) {
    }
}
