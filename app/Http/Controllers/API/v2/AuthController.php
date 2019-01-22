<?php

namespace App\Http\Controllers\API\v2;

use Illuminate\Http\Request;

class AuthController extends APIController
{
    /**
     * @param Request $request
     * @return array|\Illuminate\Http\JsonResponse|string
     *
     * @SWG\Get(
     *     path="/auth/token",
     *     summary="Get JWT. [Private]",
     *     description="Get JWT. CORS Restricted",
     *     produces={"application/json"},
     *     tags={"auth"},
     *     security={"session"},
     *     @SWG\Response(
     *         response="401",
     *         description="Unauthorized",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Unauthorized"}},
     *     ),
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
        if (!\Auth::check()) return response()->unauthenticated();
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
     *     summary="Refresh JWT. [Private]",
     *     description="Refresh JWT. CORS Restricted",
     *     produces={"application/json"},
     *     tags={"auth"},
     *     security={"jwt","session"},
     *     @SWG\Response(
     *         response="401",
     *         description="Unauthorized",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Unauthorized"}},
     *     ),
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
        if (!\Auth::check()) return response()->unauthenticated();
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
     *     summary="Get information about logged in user. [Private]",
     *     description="Get user info. CORS Restricted",
     *     produces={"application/json"},
     *     tags={"auth"},
     *     security={"jwt","session"},
     *     @SWG\Response(
     *         response="401",
     *         description="Unauthorized",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Unauthorized"}},
     *     ),
     *     @SWG\Response(
     *         response=200,
     *         description="Return User object",
     *         @SWG\Schema(
     *             ref="#/definitions/User"
     *         )
     *     )
     * )
     */
    public function getUserInfo() {
        if (!\Auth::check()) return response()->unauthenticated();
        return response()->json(\Auth::user());
    }
}
