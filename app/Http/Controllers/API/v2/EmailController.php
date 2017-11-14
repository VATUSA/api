<?php
namespace App\Http\Controllers\API\v2;

use App\Action;
use App\Helpers\EmailHelper;
use App\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Exam;

class EmailController extends APIController
{
    /**
     * @SWG\Get(
     *     path="/email",
     *     summary="Get list of VATUSA email addresses assigned for user",
     *     description="Get list of VATUSA email addresses assigned for user",
     *     produces={"application/json"},
     *     tags={"email"},
     *     security={"jwt","session"},
     *     @SWG\Parameter(description="JWT Token", in="header", name="bearer", required=true, type="string"),
     *     @SWG\Response(
     *         response="403",
     *         description="Forbidden",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","message"="Forbidden"}},
     *     ),
     *     @SWG\Response(
     *         response="200",
     *         description="OK",
     *         @SWG\Schema(
     *             type="array",
     *             @SWG\Items(
     *                 type="object",
     *                 @SWG\Property(property="type", type="string", description="Type of email (forward/full)"),
     *                 @SWG\Property(property="email", type="string", description="Email address"),
     *             ),
     *         ),
     *         examples={
     *              "application/json":{
     *                      {"type":"forward","email":"test@vatusa.net"},
     *                      {"type":"full","email":"easy@vatusa.net"}
     *              }
     *         }
     *     )
     * )
     */
    public function getIndex() {

    }

    /**
     * @SWG\Post(
     *     path="/email",
     *     summary="Modify email account",
     *     description="Modify email account",
     *     produces={"application/json"},
     *     tags={"email"},
     *     security={"jwt","session"},
     *     @SWG\Parameter(description="JWT Token", in="header", name="bearer", required=true, type="string"),
     *     @SWG\Parameter(description="Email Address", in="query", name="email", required=true, type="string"),
     *     @SWG\Parameter(description="Set destination for forwarded address", in="query", name="destination", type="string"),
     *     @SWG\Parameter(description="Password for full account", in="query", name="password", type="string"),
     *     @SWG\Response(
     *         response="403",
     *         description="Forbidden",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","message"="Forbidden"}},
     *     ),
     *     @SWG\Response(
     *         response="200",
     *         description="OK",
     *         @SWG\Schema(ref="#/definitions/OK"),
     *         examples={"application/json":{"status"="OK"}}
     *     )
     * )
     */
    public function postIndex() {

    }

    /**
     * @SWG\Delete(
     *     path="/email",
     *     summary="Delete email account",
     *     description="Delete email account",
     *     produces={"application/json"},
     *     tags={"email"},
     *     security={"jwt","session"},
     *     @SWG\Parameter(description="JWT Token", in="header", name="bearer", required=true, type="string"),
     *     @SWG\Parameter(description="Email Address", in="query", name="email", required=true, type="string"),
     *     @SWG\Response(
     *         response="403",
     *         description="Forbidden",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","message"="Forbidden"}},
     *     ),
     *     @SWG\Response(
     *         response="200",
     *         description="OK",
     *         @SWG\Schema(ref="#/definitions/OK"),
     *         examples={"application/json":{"status"="OK"}}
     *     )
     * )
     */
    public function deleteIndex() {

    }
}
