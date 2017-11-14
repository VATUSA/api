<?php

namespace App\Http\Controllers\API\v2;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Tymon\JWTAuth\Exceptions\JWTException;

class AuthController extends APIController
{
    /**
     * @param Request $request
     * @return array|\Illuminate\Http\JsonResponse|string
     *
     * @SWG\Get(
     *     path="/auth/token",
     *     description="Get JWT.",
     *     produces={"application/json"},
     *     tags={"auth"},
     *     security={"session"},
     *     @SWG\Response(
     *         response=200,
     *         description="Return JSON Token.",
     *         @SWG\Schema(
     *             type="object",
     *             @SWG\Property(property="token", type="string",example="eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzUxMiJ9.eyJpc3MiOiJodHRwczovL2FwaS52YXR1c2EubmV0L3YyL2F1dGgiLCJpYXQiOjE1MTAyODA5NzIsImV4cCI6MTUxMDI4NDU3MiwibmJmIjoxNTEwMjgwOTcyLCJqdGkiOiIzRHU5S2xBRkJsQk5CTTA0Iiwic3ViIjoiODc2NTk0IiwicHJ2IjoiODdlMGFmMWVmOWZkMTU4MTJmZGVjOTcxNTNhMTRlMGIwNDc1NDZhYSJ9.vAzBMZgnzxymv6LnftovyN3Mww7obJ7Ms9H4nQ1la9dLOHpAzW2RvvBjMFdkvi3GyCJoVx23B8uOGOCpRKj5Qg")
     *        )
     *     )
     * )
     */
    public function getToken(Request $request) {
        $token = \Auth::guard('jwt')->login(\Auth::user());
        return response()->json([
            'token' => $token,
        ]);
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     *
     * @SWG\Get(
     *     path="/auth/token/refresh",
     *     description="Refresh JWT.",
     *     produces={"application/json"},
     *     tags={"auth"},
     *     security={"jwt","session"},
     *     @SWG\Response(
     *         response=200,
     *         description="Return JSON Token.",
     *         @SWG\Schema(
     *             type="object",
     *             @SWG\Property(property="token", type="string",example="eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzUxMiJ9.eyJpc3MiOiJodHRwczovL2FwaS52YXR1c2EubmV0L3YyL2F1dGgiLCJpYXQiOjE1MTAyODA5NzIsImV4cCI6MTUxMDI4NDU3MiwibmJmIjoxNTEwMjgwOTcyLCJqdGkiOiIzRHU5S2xBRkJsQk5CTTA0Iiwic3ViIjoiODc2NTk0IiwicHJ2IjoiODdlMGFmMWVmOWZkMTU4MTJmZGVjOTcxNTNhMTRlMGIwNDc1NDZhYSJ9.vAzBMZgnzxymv6LnftovyN3Mww7obJ7Ms9H4nQ1la9dLOHpAzW2RvvBjMFdkvi3GyCJoVx23B8uOGOCpRKj5Qg")
     *        )
     *     )
     * )
     */
    public function getRefreshToken() {
        if (!\Auth::check()) {
            return response()->json(generate_error("not_logged_in", true), 401);
        }
        $token = \Auth::guard('jwt')->refresh();
        return response()->json([
            'token' => $token
        ]);
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     *
     * @SWG\Get(
     *     path="/auth/info",
     *     description="Get user info",
     *     produces={"application/json"},
     *     tags={"auth"},
     *     security={"jwt","session"},
     *     @SWG\Response(
     *         response=200,
     *         description="Return User ojbect",
     *         @SWG\Schema(
     *             ref="#/definitions/User"
     *         )
     *     )
     * )
     */
    public function getUserInfo() {
        return response()->json(\Auth::user());
    }
}
