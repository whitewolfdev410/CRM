<?php

namespace App\Http\Controllers;

use App\Core\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;

class BroadcastController extends Controller
{
    /**
     * Authenticate the request for channel access.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function authenticate(Request $request)
    {
        if ($request->hasSession()) {
            $request->session()->reflash();
        }

        $request->setUserResolver(function () {
            $userId = Auth::user()->getId();
            
            return User::select([
                'id',
                'person_id',
                DB::raw('person_name(person_id) as person_name')
            ])->find($userId);
        });
        
        return Broadcast::auth($request);
    }
}
