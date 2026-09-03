<?php

namespace Tests\Feature;

use App\Services\OwnDBAuditLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class OwnDBAuditLogServiceTest extends TestCase
{
    use RefreshDatabase;

    public function testLog(): void
    {
        $log = new OwnDBAuditLogService();
        $log->log(
            "test-action",
            12,
            12,
            12,
            "descr",
            ["a" => "b"]
        );

        $this->assertDatabaseHas(
            "audit_logs",
            [
                "action" => "test-action",
                "description" => "descr",
                "parameters" => $this->castAsJson(["a" => "b"]),
                "user_id" => 12,
                "admin_id" => 12,
                "event_id" => 12,
            ]
        );

        $log->log("some-action", 13);
        $this->assertDatabaseHas(
            "audit_logs",
            [
                "action" => "some-action",
                "user_id" => 13
            ]
        );
    }
}
