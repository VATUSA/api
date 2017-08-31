<?php

namespace App\Http\Controllers\API\v2;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Tymon\JWTAuth\Exceptions\JWTException;

class AuthController extends Controller
{
    public function getAuth(Request $request) {
        if (!\Auth::check()) {
            return generate_error("not_logged_in");
        }
        $token = \JWTAuth::fromUser(\Auth::user());
        return response()->json(compact('token'));
    }
}
