<?php

namespace App\Http\Controllers\API\v2;

use App\Helpers\RoleHelper;
use Illuminate\Http\Request;

class InfrastructureController extends APIController
{
    /**
     * @param Request $request
     * @return array|\Illuminate\Http\JsonResponse|string
     *
     * @OA\\Get(
     *     path="/infrastructure/deploy",
     *     summary="Deploy Stack. CORS Restricted",
     *     description="Deploy Stack. CORS Restricted",
     *     responses={"application/json"},
     *     tags={"infrastructure"},
     *     security={"session","jwt"},
     *     @OA\\Response(
     *         response="401",
     *         description="Unauthorized",
     *         @OA\\Schema(ref="#/components/schemas/error"),
     *         content={"application/json":{"status"="error","msg"="Unauthorized"}},
     *     ),
     *     @OA\\Response(
     *         response="403",
     *         description="Forbidden",
     *         @OA\\Schema(ref="#/components/schemas/error"),
     *         content={"application/json":{"status"="error","msg"="Forbidden"}},
     *     ),
     *     @OA\\Response(
     *         response=200,
     *         description="Return JSON Token.",
     *         @OA\\Schema(ref="#/components/schemas/OK"),
     *     )
     * )
     * @OA\\Post(
     *     path="/infrastructure/deploy",
     *     summary="Deploy Stack. CORS Restricted",
     *     description="Deploy Stack. CORS Restricted",
     *     responses={"application/json"},
     *     tags={"infrastructure"},
     *     security={"session","jwt"},
     *     @OA\\Response(
     *         response="401",
     *         description="Unauthorized",
     *         @OA\\Schema(ref="#/components/schemas/error"),
     *         content={"application/json":{"status"="error","msg"="Unauthorized"}},
     *     ),
     *     @OA\\Response(
     *         response="403",
     *         description="Forbidden",
     *         @OA\\Schema(ref="#/components/schemas/error"),
     *         content={"application/json":{"status"="error","msg"="Forbidden"}},
     *     ),
     *     @OA\\Response(
     *         response=200,
     *         description="Return JSON Token.",
     *         @OA\\Schema(ref="#/components/schemas/OK"),
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
