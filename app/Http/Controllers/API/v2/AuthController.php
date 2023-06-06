<?php

namespace App\Http\Controllers\API\v2;

use Illuminate\Http\Request;

class AuthController extends APIController
{
    /**
     * @param Request $request
     * @return array|\Illuminate\Http\JsonResponse|string
     *
     * @OA\Get(
     *     path="/auth/token",
     *     summary="Get JWT. [Private]",
     *     description="Get JWT. CORS Restricted",
     *     responses={"application/json"},
     *     tags={"auth"},
     *     security={"session"},
     *     @OA\Response(
     *         response="401",
     *         description="Unauthorized",
     *         @OA\Schema(ref="#/components/schemas/error"),
     *         content={"application/json":{"status"="error","msg"="Unauthorized"}},
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Return JSON Token.",
     *         @OA\Schema(
     *             type="object",
     *             @OA\Property(property="token", type="string",example="eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzUxMiJ9.eyJpc3MiOiJodHRwczovL2FwaS52YXR1c2EubmV0L3YyL2F1dGgiLCJpYXQiOjE1MTAyODA5NzIsImV4cCI6MTUxMDI4NDU3MiwibmJmIjoxNTEwMjgwOTcyLCJqdGkiOiIzRHU5S2xBRkJsQk5CTTA0Iiwic3ViIjoiODc2NTk0IiwicHJ2IjoiODdlMGFmMWVmOWZkMTU4MTJmZGVjOTcxNTNhMTRlMGIwNDc1NDZhYSJ9.vAzBMZgnzxymv6LnftovyN3Mww7obJ7Ms9H4nQ1la9dLOHpAzW2RvvBjMFdkvi3GyCJoVx23B8uOGOCpRKj5Qg")
     *        )
     *     )
     * )
     */
    public function getToken(Request $request) {
        if (!\Auth::check()) return response()->unauthenticated();
        $token = \Auth::guard('jwt')->login(\Auth::user());
        return response()->api([
            'token' => $token,
        ]);
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     *
     * @OA\Get(
     *     path="/auth/token/refresh",
     *     summary="Refresh JWT. [Private]",
     *     description="Refresh JWT. CORS Restricted",
     *     responses={"application/json"},
     *     tags={"auth"},
     *     security={"jwt","session"},
     *     @OA\Response(
     *         response="401",
     *         description="Unauthorized",
     *         @OA\Schema(ref="#/components/schemas/error"),
     *         content={"application/json":{"status"="error","msg"="Unauthorized"}},
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Return JSON Token.",
     *         @OA\Schema(
     *             type="object",
     *             @OA\Property(property="token", type="string",example="eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzUxMiJ9.eyJpc3MiOiJodHRwczovL2FwaS52YXR1c2EubmV0L3YyL2F1dGgiLCJpYXQiOjE1MTAyODA5NzIsImV4cCI6MTUxMDI4NDU3MiwibmJmIjoxNTEwMjgwOTcyLCJqdGkiOiIzRHU5S2xBRkJsQk5CTTA0Iiwic3ViIjoiODc2NTk0IiwicHJ2IjoiODdlMGFmMWVmOWZkMTU4MTJmZGVjOTcxNTNhMTRlMGIwNDc1NDZhYSJ9.vAzBMZgnzxymv6LnftovyN3Mww7obJ7Ms9H4nQ1la9dLOHpAzW2RvvBjMFdkvi3GyCJoVx23B8uOGOCpRKj5Qg")
     *        )
     *     )
     * )
     */
    public function getRefreshToken() {
        if (!\Auth::check()) return response()->unauthenticated();
        $token = \Auth::guard('jwt')->refresh();
        return response()->api([
            'token' => $token
        ]);
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     *
     * @OA\Get(
     *     path="/auth/info",
     *     summary="Get information about logged in user. [Private]",
     *     description="Get user info. CORS Restricted",
     *     responses={"application/json"},
     *     tags={"auth"},
     *     security={"jwt","session"},
     *     @OA\Response(
     *         response="401",
     *         description="Unauthorized",
     *         @OA\Schema(ref="#/components/schemas/error"),
     *         content={"application/json":{"status"="error","msg"="Unauthorized"}},
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Return User object",
     *         @OA\Schema(
     *             ref="#/components/schemas/User"
     *         )
     *     )
     * )
     */
    public function getUserInfo() {
        if (!\Auth::check()) return response()->unauthenticated();
        return response()->api(\Auth::user());
    }
}
