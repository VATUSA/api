<?php

namespace App\Http\Controllers\API\v2;

use App\Action;
use App\Helpers\EmailHelper;
use App\Role;
use App\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Exam;
use PharIo\Manifest\Email;

class EmailController extends APIController
{
    /**
     * @SWG\Get(
     *     path="/email",
     *     summary="Get info of VATUSA email address assigned for user",
     *     description="Get info of VATUSA email address assigned for user",
     *     produces={"application/json"},
     *     tags={"email"},
     *     security={"jwt","session"},
     *     @SWG\Parameter(description="JWT Token", in="header", name="bearer", required=true, type="string"),
     *     @SWG\Response(
     *         response="200",
     *         description="OK",
     *         @SWG\Schema(
     *             type="array",
     *             @SWG\Items(
     *                 type="object",
     *                 @SWG\Property(property="type", type="string", description="Type of email (forward/full)"),
     *                 @SWG\Property(property="email", type="string", description="Email address"),
     *                 @SWG\Property(property="destination", type="string", description="Destination for email forwards")
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
        $response = [];
        $return = Role::where('cid', \Auth::user()->cid)->get();
        foreach ($return as $row) {
            if ($row->facility === "ZHQ" && preg_match("/^US(\d+)$/", $row->role, $matches)) {
                $temp = [
                    "type" => EmailHelper::getType("vatusa" . $matches[1] . "@vatusa.net"),
                    "email" => "vatusa" . $matches[1] . "@vatusa.net",
                ];
                if ($temp['type'] === EmailHelper::$email_forward) {
                    $temp['destination'] = implode(",", EmailHelper::forwardDestination($temp['email']));
                }
                $response[] = $temp;
            }
            if ($row->facility !== "ZHQ" && $row->facility !== "ZAE" && in_array($row->role, ["ATM", "DATM", "TA", "EC", "FE", "WM"])) {
                $temp = [
                    "type" => EmailHelper::getType(strtoupper($row->facility . "-" . $row->role . "@vatusa.net")),
                    "email" => strtoupper($row->facility . "-" . $row->role . "@vatusa.net"),

                ];
                if ($temp['type'] === EmailHelper::$email_forward) {
                    $temp["destination"] = implode(",", EmailHelper::forwardDestination($temp['email']));
                }
                $response[] = $temp;
            }
        }
        return response()->json($response);
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
     *         response="400",
     *         description="Bad request",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={{"application/json":{"status"="error","msg"="Missing required field"}},{"application/json":{"status"="error","msg"="Password too weak"}}},
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Forbidden",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Forbidden"}},
     *     ),
     *     @SWG\Response(
     *         response="409",
     *         description="Conflict, usually caused by mismatched parameters",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Ambiguous request"}},
     *     ),
     *     @SWG\Response(
     *         response="500",
     *         description="Server error",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Unknown error"}},
     *     ),
     *     @SWG\Response(
     *         response="200",
     *         description="OK",
     *         @SWG\Schema(ref="#/definitions/OK"),
     *         examples={"application/json":{"status"="OK"}}
     *     )
     * )
     */
    public function postIndex(Request $request) {
        $email = $request->input("email", null);
        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return response()->json(generate_error("Missing required field", true), 400);
        }

        if (!\Auth::user()->hasEmailAccess($email)) {
            return response()->json(generate_error("Forbidden", true), 403);
        }

        /* Now determine which course of action:
         * - Full Account (password set)
         * - Forward (destination set)
         */
        $password = $request->input("password", null);
        $destination = $request->input("destination", null);
        if (!$password && !$destination) {
            return response()->json(generate_error("Missing required field", true), 400);
        }
        // We cannot determine full or forward if both are set, causing ambiguous information
        if ($password !== null && $destination !== null) {
            return response()->json(generate_error("Ambiguous request", true), 409);
        }

        // Now handle, *FULL ACCOUNT*
        if ($password !== null) {
            if (strlen($password) < 6) {
                return response()->json(generate_error("Password too weak", true), 400);
            }
            if (EmailHelper::getType($email) === EmailHelper::$email_full) {
                if (EmailHelper::setPasswordEmail($email, $password)) {
                    return response()->json(["status" => "OK"]);
                } else {
                    return response()->json(generate_error("Unknown error", true), 500);
                }
            } else {
                if (!EmailHelper::deleteForward($email)) {
                    \Log::critical("Error deleting forward for $email to change to full account");
                    return response()->json(generate_error("Unknown error", true), 500);
                }
                if (!EmailHelper::addEmail($email, $password)) {
                    \Log::critical("Error creating full account $email");
                    return response()->json(generate_error("Unknown error", true), 500);
                }

                return response()->json(["status" => "OK"]);
            }
        }

        // Now handle, *FORWARD*
        if (!filter_var($destination, FILTER_VALIDATE_EMAIL)) {
            return response()->json(generate_error("Missing required field", true), 400);
        }
        if (EmailHelper::getType($email) === EmailHelper::$email_full) {
            if (!EmailHelper::deleteEmail($email)) {
                \Log::critical("Error deleting full account $email to set to forward to $destination");
                return response()->json(generate_error("Unknown error", true), 500);
            }
        }
        if (!EmailHelper::setForward($email, $destination)) {
            \Log::critical("Error setting forward $email -> $destination");
            return response()->json(generate_error("Unknown error", true), 500);
        }
        return response()->json(["status" => "OK"]);
    }
}
