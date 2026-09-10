<?php

namespace App\Services;

use App\Contracts\AuditLogContract;
use App\Data\Venues\VenueData;
use App\Models\User;
use App\Models\Venue;

class VenueService
{
    public function __construct(
        readonly private AuditLogContract $venueAuditLogService
    )
    {}

    public function createVenue(User $creator, VenueData $venueData)
    {
        Venue::create([
            "name" => $venueData->name,
            "address" => $venueData->address,
            "description" => $venueData->description,
            "capacity" => $venueData->capacity,
        ]);

        $this->venueAuditLogService->log("venue_created", user_id: $creator->id);
    }

    public function getVenuesList()
    {
        return Venue::query()
            ->orderBy("created_at")
            ->get();
    }

    public function updateVenue(User $user, Venue $venue, VenueData $venueData)
    {
        $venue->fill((array) $venueData);
        $venue->save();
        $this->venueAuditLogService->log("venue_updated", user_id: $user->id, parameters: (array) $venueData);
    }

    public function deleteVenue(User $user, Venue $venue): void
    {
        if ($venue->events()->exists()) {
            abort(409, "Venue is used by events.");
        }

        $venue->delete();

        $this->venueAuditLogService->log(
            "venue_deleted",
            user_id: $user->id,
        );
    }
}
