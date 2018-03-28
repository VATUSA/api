<?php

namespace App\Http\Controllers\API\v2;

use App\Facility;
use App\Helpers\RoleHelper;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Bucket;

class BucketController extends APIController
{
    /**
     * @param Request $request
     * @return array|\Illuminate\Http\JsonResponse|string
     *
     * @SWG\Get(
     *     path="/bucket/(facility)",
     *     summary="(DONE) Get bucket information. Requires JWT/Session Key",
     *     description="(DONE) Get bucket information. Requires JWT/Session Key",
     *     produces={"application/json"},
     *     tags={"auth"},
     *     security={"session","jwt"},
     *     @SWG\Parameter(name="facility", in="path", type="string", description="Facility IATA ID"),
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
     *         response="404",
     *         description="Not found",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Not found"}},
     *     ),
     *     @SWG\Response(
     *         response=200,
     *         description="Return JSON Token.",
     *         @SWG\Schema(
     *             type="object",
     *             @SWG\Property(property="bucket", type="object", ref="#/definitions/Bucket"),
     *        )
     *     )
     * )
     */
    public function getBucket(Request $request, $facility) {
        if (!\Auth::check()) return response()->unauthenticated();
        $f = Facility::find($facility);
        if (!$f) return response()->notfound(["addl" => "Invalid facility"]);
        if (!RoleHelper::isVATUSAStaff() && !RoleHelper::has(\Auth::user()->cid, $facility,["ATM","DATM","WM"])) {
            return response()->forbidden();
        }
        $bucket = Bucket::where('facility', $facility)->first();
        if (!$bucket) return response()->notfound(["addl" => "No bucket defined"]);

        return response()->ok(['bucket' => $bucket->toArray()]);
    }
}
