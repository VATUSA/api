<?php

namespace App\Http\Controllers\API\v2;

use App\Helpers\RoleHelper;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Tymon\JWTAuth\Exceptions\JWTException;

class InfrastructureController extends APIController
{
    /**
     * @param Request $request
     * @return array|\Illuminate\Http\JsonResponse|string
     *
     * @SWG\Get(
     *     path="/infrastructure/deploy",
     *     summary="(DONE) Deploy Stack. CORS Restricted",
     *     description="(DONE) Deploy Stack. CORS Restricted",
     *     produces={"application/json"},
     *     tags={"infrastructure"},
     *     security={"session","jwt"},
     *     @SWG\Response(
     *         response="401",
     *         description="Unauthorized",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Unauthorized"}},
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Forbidden",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Forbidden"}},
     *     ),
     *     @SWG\Response(
     *         response=200,
     *         description="Return JSON Token.",
     *         @SWG\Schema(ref="#/definitions/OK"),
     *     )
     * )
     * @SWG\Post(
     *     path="/infrastructure/deploy",
     *     summary="(DONE) Deploy Stack. CORS Restricted",
     *     description="(DONE) Deploy Stack. CORS Restricted",
     *     produces={"application/json"},
     *     tags={"infrastructure"},
     *     security={"session","jwt"},
     *     @SWG\Response(
     *         response="401",
     *         description="Unauthorized",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Unauthorized"}},
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Forbidden",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Forbidden"}},
     *     ),
     *     @SWG\Response(
     *         response=200,
     *         description="Return JSON Token.",
     *         @SWG\Schema(ref="#/definitions/OK"),
     *     )
     * )
     */
    public function deploy(Request $request) {
        if (!\Auth::check()) return response()->unauthenticated();
        if (!RoleHelper::isWebTeam()) return response()->forbidden();

        $msg = null;
        exec("ssh " . env('SSH_CONNECTION_STRING') . " -i " . env('SSH_KEY_FILE'), $msg);
        return response()->ok(['return' => $msg]);
    }
}
