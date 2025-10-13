<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('negotiation.{negotiationId}', function ($user, $negotiationId) {
    // Check if user is part of this negotiation
    $negotiation = \App\Models\Negotiation::find($negotiationId);
    
    if (!$negotiation) {
        return false;
    }
    
    // Allow if user is the initiator or supplier
    return $user->id === $negotiation->initiated_by || $user->id === $negotiation->supplier_id;
});
