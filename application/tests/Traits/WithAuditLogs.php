<?php

namespace Tests\Traits;

use App\Contracts\AuditLogContract;
use Mockery\MockInterface;
use Mockery\VerificationDirector;

trait WithAuditLogs
{
    private MockInterface $auditLogSpy;

    public function setUpWithAuditLogs(): void
    {
        $this->auditLogSpy = $this->spy(AuditLogContract::class);
    }

    public function assertLog(
        string  $action,
        int|false|null    $user_id = false,
        int|false|null    $admin_id = false,
        int|false|null    $event_id = false,
        false|string|null$description = false,
        array|false  $parameters = false,
        int $times = 1,
    ): void
    {
        /** @var VerificationDirector $verification */
        $verification = $this->auditLogSpy->shouldHaveReceived('log');

        $verification->withArgs(function (
                string  $_action,
                int|false|null    $_user_id = false,
                int|false|null    $_admin_id = false,
                int|false|null    $_event_id = false,
                false|string|null $_description = false,
                array|false   $_parameters = false
            ) use ($action, $user_id, $admin_id, $event_id, $description, $parameters){
                return
                    $action === $_action
                    && ($user_id === false ||$user_id === $_user_id)
                    && ($admin_id === false || $admin_id === $_admin_id)
                    && ($event_id ===false || $event_id === $_event_id)
                    && ($description === false || $description === $_description)
                    && ($parameters === false || $parameters === $_parameters);
            })
            ->times($times);
    }
}
