<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;

class EventPolicy
{
    /**
     * Create a new policy instance.
     */
    public function __construct()
    {
        //
    }

    public function update(User $user, Event $event)
    {
        return $user->id === $event->organizer_id;
    }

    public function delete(User $user, Event $event)
    {
        return $user->id === $event->organizer_id;
    }
}
