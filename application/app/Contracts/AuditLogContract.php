<?php

namespace App\Contracts;

interface AuditLogContract
{
    public function log(
        string $action,
        ?int $user_id = null,
        ?int $admin_id = null,
        ?int $event_id = null,
        ?string $description = null,
        array $parameters = []
    ): void;
}
