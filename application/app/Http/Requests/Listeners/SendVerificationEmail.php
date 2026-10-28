<?php

namespace App\Http\Requests\Listeners;

use App\Services\AuthService;
use Illuminate\Auth\Events\Registered;

class SendVerificationEmail
{
    /**
     * Create the event listener.
     */
    public function __construct(private AuthService $authService)
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(Registered $event): void
    {
        $this->authService->sendVerificationEmail($event->user);
    }
}
