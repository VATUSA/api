<?php

namespace App\Http\Controllers\API\v2;

use App\Helpers\AuthHelper;
use App\Helpers\EmailHelper;
use App\Helpers\RatingHelper;
use App\Helpers\RoleHelper;
use App\Role;
use App\Transfer;
use App\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Facility;

/**
 * Class SoloController
 * @package App\Http\Controllers\API\v2
 */
class SoloController extends APIController
{
    /**
     * @return string
     *
     * @TODO
     *
     * @SWG\Get(
     *     path="/solo",
     *     summary="Get list of active solo certifications",
     *     description="Get list of active solo certifications",
     *     produces={"application/json"},
     *     tags={"solo"},
     *     @SWG\Parameter(name="facility", in="path", type="string", description="Filter for facility IATA ID"),
     *     @SWG\Response(
     *         response="200",
     *         description="OK",
     *         @SWG\Schema(
     *             type="array",
     *             @SWG\Items(
     *                 type="object",
     *                 @SWG\Property(property="id", type="integer", description="Solo Certification id"),
     *                 @SWG\Property(property="cid",type="integer",description="CERT ID of user"),
     *                 @SWG\Property(property="lastname",type="string",description="Last name"),
     *                 @SWG\Property(property="firstname",type="string",description="First name"),
     *                 @SWG\Property(property="position", type="string", description="Position ID (XYZ_APP, ZZZ_CTR)"),
     *                 @SWG\Property(property="expDate", type="string", description="Expiration Date (YYYY-MM-DD)"),
     *             ),
     *         ),
     *     )
     * ),
     */
    public function getSolo() {

    }

    /**
     * @return string
     *
     * @TODO
     *
     * @SWG\Post(
     *     path="/solo",
     *     summary="Put new solo certification. Requires JWT, API Key, or Session cookie",
     *     description="Put new solo certification. Requires JWT, API Key, or Session cookie (required roles: [N/A for API Key] ATM, DATM, TA, INS)",
     *     produces={"application/json"},
     *     tags={"solo"},
     *     security={"apikey","jwt","session"},
     *     @SWG\Parameter(name="cid", in="formData", type="integer", required=true, description="CERT ID"),
     *     @SWG\Parameter(name="position", in="formData", type="string", required=true, description="Position ID (XYZ_APP, ZZZ_CTR)"),
     *     @SWG\Parameter(name="expDate", in="formData", type="string", required=true, description="Date of expiration (YYYY-MM-DD)"),
     *     @SWG\Response(
     *         response="400",
     *         description="Malformed request, check format of position, expDate",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={{"application/json":{"status"="error","message"="Invalid position"}},{"application/json":{"status"="error","message"="Invalid expDate"}}},
     *     ),
     *     @SWG\Response(
     *         response="404",
     *         description="Not found, controller doesn't exist",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","message"="Not found"}},
     *     ),
     *     @SWG\Response(
     *         response="200",
     *         description="OK",
     *         @SWG\Schema(
     *             type="object",
     *             @SWG\Property(property="status", type="string"),
     *             @SWG\Property(property="id", type="integer", description="ID number of solo certification"),
     *         ),
     *         examples={"application/json":{"status"="OK","id"=1234}}
     *     )
     * ),
     */
    public function postSolo() {

    }

    /**
     * @return string
     *
     * @TODO
     *
     * @SWG\Delete(
     *     path="/solo",
     *     summary="Delete solo certification. Requires JWT, API Key, or Session cookie",
     *     description="Delete solo certification. Requires JWT, API Key, or Session cookie (required roles: [N/A for API Key] ATM, DATM, TA, INS)",
     *     produces={"application/json"},
     *     tags={"solo"},
     *     security={"apikey","jwt","session"},
     *     @SWG\Parameter(name="cid", in="formData", type="integer", required=true, description="CERT ID"),
     *     @SWG\Parameter(name="position", in="formData", type="string", required=true, description="Position ID (XYZ_APP, ZZZ_CTR)"),
     *     @SWG\Response(
     *         response="404",
     *         description="Not found, solo certification doesn't exist",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","message"="Not found"}},
     *     ),
     *     @SWG\Response(
     *         response="200",
     *         description="OK",
     *         @SWG\Schema(ref="#/definitions/OK"),
     *         examples={"application/json":{"status"="OK"}}
     *     )
     * ),
     */
    public function deleteSolo() {

    }
}
