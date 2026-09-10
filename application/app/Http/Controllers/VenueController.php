<?php

namespace App\Http\Controllers;

use App\Http\Requests\Venue\VenueRequest;
use App\Models\Venue;
use App\Services\VenueService;

class VenueController extends Controller
{
    public function index(VenueService $venueService)
    {
        return [
            "data" => $venueService->getVenuesList(),
        ];
    }

    public function create(VenueRequest $request, VenueService $venueService)
    {
        $venueService->createVenue(auth()->user(), $request->toDTO());
        return response()->json(["success" => true], 201);
    }

    public function update(VenueRequest $request, Venue $venue, VenueService $venueService)
    {
        $venueService->updateVenue(auth()->user(), $venue, $request->toDTO());
        return ["success" => true];
    }

    public function delete(Venue $venue, VenueService $venueService)
    {
        $venueService->deleteVenue(auth()->user(), $venue);
        return ["success" => true];
    }
}
