<?php

namespace App\Http\Controllers\API\v2;

use App\Helpers\AuthHelper;
use App\Helpers\EmailHelper;
use App\Helpers\RatingHelper;
use App\Helpers\RoleHelper;
use App\Role;
use App\Transfer;
use App\User;
use App\Exam;
use App\ExamResults;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Facility;

/**
 * Class TMUController
 * @package App\Http\Controllers\API\v2
 */
class TMUController  extends APIController
{
    /**
     * @return string
     *
     * @TODO
     *
     * @SWG\Get(
     *     path="/tmu/(facility)/colors",
     *     summary="Change the colors of a TMU Facility's map",
     *     description="Change the colors of a TMU Facility's map",
     *     produces={"application/json"},
     *     tags={"tmu"},
     *     @SWG\Parameter(name="facility", in="path", type="string", description="Filter for facility IATA ID"),
     *     @SWG\Parameter(name="month", in="query", type="integer", description="Filter by month number, requires year"),
     *     @SWG\Parameter(name="year", in="query", type="integer", description="4 digit year to limit results by"),
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

}
