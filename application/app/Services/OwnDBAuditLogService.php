<?php

namespace App\Services;

use App\Contracts\AuditLogContract;
use App\Models\AuditLog;

class OwnDBAuditLogService implements AuditLogContract
{

    public function log(
        string $action,
        ?int $user_id = null,
        ?int $admin_id = null,
        ?int $event_id = null,
        ?string $description = null,
        array $parameters = []): void
    {
        AuditLog::create([
            "action" => $action,
            "admin_id" => $admin_id,
            "event_id" => $event_id,
            "user_id" => $user_id,
            "description" => $description,
            "parameters" => $parameters
        ]);
    }
}
