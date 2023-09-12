<?php

namespace App;

use App\Classes\VATUSAMoodle;
use App\Helpers\EmailHelper;
use Exception;

/**
 * Class Transfer
 * @package App
 *
 * @OA\Schema(
 *     type="object",
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="cid", type="integer"),
 *     @OA\Property(property="to", type="string"),
 *     @OA\Property(property="from", type="string"),
 *     @OA\Property(property="reason", type="string"),
 *     @OA\Property(property="status", type="integer", description="0 pending, 1 approved, 2 rejected"),
 *     @OA\Property(property="actiontext", type="string", description="Reasoning"),
 *     @OA\Property(property="actionby", type="integer", description="Cert ID, 0 is system processed [CERT Sync usually]"),
 *     @OA\Property(property="created_at", type="string", description="Date transfer submitted"),
 *     @OA\Property(property="updated_at", type="string", description="Date transfer was last acted on [not updated after processed]")
 * )
 */
class Transfer extends Model
{
    // Transfer status codes
    public static $pending = 0;
    public static $accepted = 1;
    public static $rejected = 2;

    protected $table = 'transfers';

    public function user()
    {
        return $this->hasOne('App\User', 'cid', 'cid');
    }

    public function toFac()
    {
        return $this->hasOne('App\Facility', 'id', 'to');
    }

    public function fromFac()
    {
        return $this->hasOne('App\Facility', 'id', 'from');
    }

    public function accept($by)
    {
        $this->status = 1;
        $this->actionby = $by;
        $this->save();

        $user = User::where('cid', $this->cid)->first();
        $user->addToFacility($this->to);

        /** Remove Mentor/INS Role */
        Role::where("cid", $this->cid)->where("facility", $this->from)->where(function ($query) {
            $query->where("role", "MTR")->orWhere("role", "INS");
        })->delete();
        $moodle = new VATUSAMoodle();
        try {
            $moodle->unassignMentorRoles($this->cid);
        } catch (Exception $e) {
        }

        EmailHelper::sendEmail(
            [
                $this->to . "-atm@vatusa.net",
                $this->to . "-datm@vatusa.net",
                "vatusa2@vatusa.net",
                $this->from . "-atm@vatusa.net",
                $this->from . "-datm@vatusa.net",
            ],
            "Transfer accepted",
            "emails.transfers.accepted",
            [
                'fname' => $this->user->fname,
                'lname' => $this->user->lname,
                'cid'   => $this->user->cid,
                'to'    => $this->to,
                'from'  => $this->from,
            ]
        );

        $by = User::where('cid', $by)->first();

        log_action($this->cid,
            "Transfer request to " . $this->to . " accepted by " . $by->fullname() . " (" . $by->cid . ")");
    }

    public function reject($by, $msg)
    {
        $this->status = 2;
        $this->actiontext = $msg;
        $this->actionby = $by;
        $this->save();

        EmailHelper::sendEmail(
            [
                $this->user->email,
                $this->to . "-atm@vatusa.net",
                $this->to . "-datm@vatusa.net",
                "vatusa2@vatusa.net",
                $this->from . "-atm@vatusa.net",
                $this->from . "-datm@vatusa.net"
            ],
            "Transfer request rejected",
            "emails.transfers.rejected",
            [
                'fname'   => $this->user->fname,
                'lname'   => $this->user->lname,
                'cid'     => $this->cid,
                'facname' => $this->toFac->name,
                'facid'   => $this->toFac->id,
                'by'      => User::findName($by),
                'msg'     => $msg
            ]
        );

        $by = User::where('cid', $by)->first();

        log_action($this->cid,
            "Transfer request to " . $this->to . " rejected by " . $by->fullname() . " (" . $by->cid . "): $msg");
    }
}
