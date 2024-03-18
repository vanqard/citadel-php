<?php
namespace CitadelClient;

use DateTime;

class ResolvedIdentity
{
    public function __construct(
        public string $id,
        public DateTime $assignedAt,
        public string $user,
        public array $data
    ) {
    }
}
