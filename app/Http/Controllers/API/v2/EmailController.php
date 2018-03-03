<?php

namespace App\Http\Controllers\API\v2;

use App\Action;
use App\EmailAccounts;
use App\Helpers\EmailHelper;
use App\Helpers\RoleHelper;
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
     *     summary="(DONE) Get info of VATUSA email address assigned for user. CORS Restricted",
     *     description="(DONE) Get info of VATUSA email address assigned for user. CORS Restricted",
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
     *                 @SWG\Property(property="type", type="string", description="Type of email (forward/full/static)"),
     *                 @SWG\Property(property="email", type="string", description="Email address"),
     *                 @SWG\Property(property="destination", type="string", description="Destination for email forwards")
     *             ),
     *         ),
     *         examples={
     *              "application/json":{
     *                      {"type":"forward","email":"test@vatusa.net","destination":"test2@vatusa.net"},
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
                    "type" => EmailHelper::isStaticForward("vatusa" . $matches[1] . "@vatusa.net") ?
                        EmailHelper::$email_static :
                        EmailHelper::getType("vatusa" . $matches[1] . "@vatusa.net"),
                    "email" => "vatusa" . $matches[1] . "@vatusa.net",
                ];
                if ($temp['type'] === EmailHelper::$email_forward || $temp['type'] === EmailHelper::$email_static) {
                    $temp['destination'] = implode(",", EmailHelper::forwardDestination($temp['email']));
                }
                $response[] = $temp;
            }
            if ($row->facility !== "ZHQ" && $row->facility !== "ZAE" && in_array($row->role, ["ATM", "DATM", "TA", "EC", "FE", "WM"])) {
                $temp = [
                    "type" => EmailHelper::isStaticForward(strtolower($row->facility . "-" . $row->role . "@vatusa.net")) ?
                        EmailHelper::$email_static :
                        EmailHelper::getType(strtolower($row->facility . "-" . $row->role . "@vatusa.net")),
                    "email" => strtolower($row->facility . "-" . $row->role . "@vatusa.net"),

                ];
                if ($temp['type'] === EmailHelper::$email_forward || $temp['type'] === EmailHelper::$email_static) {
                    $temp["destination"] = implode(",", EmailHelper::forwardDestination($temp['email']));
                }
                $response[] = $temp;
            }
        }
        $return = EmailAccounts::where("cid", \Auth::user()->cid)->get();
        foreach($return as $row) {
            $domain = $row->fac->hosted_email_domain;
            if (!$domain) continue;
            $temp = [
                "type" => EmailHelper::getType(strtolower($row->username . "@" . $domain)),
                "email" => strtolower($row->username . "@" . $domain)
            ];
            if ($temp['type'] === EmailHelper::$email_forward) {
                $temp['destination'] = implode(",", EmailHelper::forwardDestination($temp["email"]));
            }
            $response[] = $temp;
        }
        return response()->json($response);
    }

    /**
     * @param $address
     * @return string
     *
     * @SWG\Get(
     *     path="/email/(address)",
     *     summary="(DONE) Get info of VATUSA email address. CORS Restricted",
     *     description="(DONE) Get info of VATUSA email address. CORS Restricted",
     *     produces={"application/json"},
     *     tags={"email"},
     *     security={"jwt","session"},
     *     @SWG\Parameter(description="JWT Token", in="header", name="bearer", required=true, type="string"),
     *     @SWG\Parameter(description="Email address", in="path", name="address", required=true, type="string"),
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
     *         response="200",
     *         description="OK",
     *         @SWG\Schema(
     *             type="array",
     *             @SWG\Items(
     *                 type="object",
     *                 @SWG\Property(property="type", type="string", description="Type of email (forward/full/static)"),
     *                 @SWG\Property(property="email", type="string", description="Email address"),
     *                 @SWG\Property(property="destination", type="string", description="Destination for email forwards"),
     *                 @SWG\Property(property="static", type="boolean", description="Is address static?")
     *             ),
     *         ),
     *         examples={
     *              "application/json":{
     *                      "type":"full","email":"easy@vatusa.net"
     *              }
     *         }
     *     )
     * )
     */
    public function getEmail($address) {
        if (!filter_var($address, FILTER_VALIDATE_EMAIL)) {
            return response()->json(generate_error("Malformed request"), 400);
        }
        if (!\Auth::user()->hasEmailAccess($address)) {
            return response()->json(generate_error("Forbidden"), 403);
        }

        $response = [
            'type' => EmailHelper::isStaticForward($address) ? EmailHelper::$email_static : EmailHelper::getType($address),
            'email' => $address
        ];

        if ($response['type'] === EmailHelper::$email_forward || $response['type'] === EmailHelper::$email_static) {
            $response['destination'] = implode(",", EmailHelper::forwardDestination($address));
        }

        return response()->json($response);
    }

    /**
     * @SWG\Put(
     *     path="/email",
     *     summary="(DONE) Modify email account. CORS Restricted",
     *     description="(DONE) Modify email account. Static forwards may only be modified by the ATM, DATM or WM. CORS Restricted",
     *     produces={"application/json"},
     *     tags={"email"},
     *     security={"jwt","session"},
     *     @SWG\Parameter(description="JWT Token", in="header", name="bearer", required=true, type="string"),
     *     @SWG\Parameter(description="Email Address", in="query", name="email", required=true, type="string"),
     *     @SWG\Parameter(description="Set destination for forwarded address", in="query", name="destination", type="string"),
     *     @SWG\Parameter(description="Password for full account", in="query", name="password", type="string"),
     *     @SWG\Parameter(description="Is static forward or not", in="query", name="static", type="boolean"),
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
    public function putIndex(Request $request) {
        $email = $request->input("email", null);
        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return response()->json(generate_error("Missing required field", true), 400);
        }

        if (!\Auth::user()->hasEmailAccess($email)) {
            return response()->json(generate_error("Forbidden", true), 403);
        }

        if (EmailHelper::isStaticForward($email) &&
            (!RoleHelper::has(\Auth::user()->cid, strtoupper(substr($email, 0, 3)), ['ATM','DATM','WM']) &&
            !\Auth::user()->hasEmailAccess($email))) {
            return response()->json(generate_error("Forbidden static rules"), 403);
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

        if ($request->input("static") == "true") {
            EmailHelper::chgEmailConfig($email, EmailHelper::$config_static, $destination);
        } else {
            EmailHelper::chgEmailConfig($email, EmailHelper::$config_user, $destination);
        }
        return response()->json(["status" => "OK"]);
    }
}
