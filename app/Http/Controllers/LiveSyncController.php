<?php

namespace App\Http\Controllers;

use App\Support\LiveSync\DataVersion;
use App\Support\ShoppingList\ShoppingListCount;
use Illuminate\Http\JsonResponse;

/**
 * GET /sync/{scope}/{id?} → {"v": "<fingerprint>"}, polled by shared screens
 * (see liveSync in app.js). 410 when the list was deleted on the other phone.
 */
class LiveSyncController extends Controller
{
    public function __invoke(string $scope, ?int $id = null): JsonResponse
    {
        $version = DataVersion::for($scope, $id);

        abort_if($version === null, 404);
        abort_if($version === DataVersion::GONE, 410);

        // The shopping list also reports what is left to buy (the app icon badge).
        $payload = ['v' => $version];

        if ($scope === DataVersion::SHOPPING) {
            $payload['count'] = ShoppingListCount::get();
        }

        return response()->json($payload)->header('Cache-Control', 'no-store');
    }
}
