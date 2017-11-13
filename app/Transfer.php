<?php
namespace App;

use App\Helpers\Helper;
use Illuminate\Database\Eloquent\Model;
use App\User;
use App\Helpers\EmailHelper;

class Transfer extends Model
{
    protected $table = 'transfers';

    public function user()
    {
        return $this->hasOne('App\User', 'cid', 'cid');
    }

    public function to()
    {
        return $this->hasOne('App\Facility', 'id', 'to');
    }

    public function from()
    {
        return $this->hasOne('App\Facility', 'id', 'from');
    }

    public function accept($by)
    {
        $this->status = 1;
        $this->actionby = $by;
        $this->save();

        $user = User::where('cid',$this->cid)->first();
        $user->addToFacility($this->to);
        EmailHelper::sendEmail(
            [
                $this->to . "-atm@vatusa.net",
                $this->to . "-datm@vatusa.net",
                "vatusa" . $this->to()->region . "@vatusa.net",
                $this->from . "-atm@vatusa.net",
                $this->from . "-datm@vatusa.net",
                "vatusa" . $this->from()->region . "@vatusa.net",
            ],
            "Transfer accepted",
            "emails.transfers.accepted",
            [
                'fname' => $this->user()->fname,
                'lname' => $this->user()->lname,
                'cid' => $this->user()->cid,
                'to' => $this->to,
                'from' => $this->from,
            ]
        );

        $by = User::where('cid', $by)->first();

        log_action($this->cid, "Transfer request to " . $this->to . " accepted by " . $by->fullname() . " (" . $by->cid . ")");
    }

    public function reject($by, $msg)
    {
        $this->status = 2;
        $this->actiontext = $msg;
        $this->actionby = $by;
        $this->save();

        EmailHelper::sendEmail(
            [
                $this->user()->email,
                $this->to . "-atm@vatusa.net",
                $this->to . "-datm@vatusa.net",
                "vatusa" . $this->to()->region . "@vatusa.net",
                $this->from . "-atm@vatusa.net",
                $this->from . "-datm@vatusa.net",
                "vatusa" . $this->from()->region . "@vatusa.net"
            ],
            "Transfer request rejected",
            "emails.transfers.rejected",
            [
                'fname' => $this->user()->fname,
                'lname' => $this->user()->lname,
                'cid' => $this->cid,
                'facname' => $this->to()->name,
                'facid' => $this->to()->id,
                'region' => $this->to()->region,
                'by' => Helper::nameFromCID($by),
                'msg' => $msg
            ]
        );

        $by = User::where('cid', $by)->first();

        log_action($this->cid, "Transfer request to " . $this->to . " rejected by " . $by->fullname() . " (" . $by->cid . "): $msg");
    }
}
