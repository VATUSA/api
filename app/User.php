<?php namespace App;

use App\Helpers\RatingHelper;
use Illuminate\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;

/**
 * Class User
 * @package App
 */
class User extends Model implements AuthenticatableContract, CanResetPasswordContract
{
    use Authenticatable, CanResetPassword;

    /**
     * @var string
     */
    protected $table = 'controllers';
    /**
     * @var string
     */
    public $primaryKey = "cid";
    /**
     * @var bool
     */
    public $incrementing = false;
    /**
     * @var array
     */
    public $timestamps = ['created_at'];
    /**
     * @var array
     */
    protected $hidden = ['password', 'remember_token'];

    /**
     * @return array
     */
    public function getDates()
    {
        return ['created_at','updated_at','lastactivity'];
    }

    /**
     * @return string
     */
    public function fullname()
    {
        return $this->fname . " " . $this->lname;
    }

    /**
     * @return Model|null|static
     */
    public function facility()
    {
        return $this->belongsTo('App\Facility', 'facility')->first();
    }

    /**
     * @return bool
     */
    public function promotionEligible()
    {
        if ($this->flag_homecontroller == 0) return false;

        if ($this->rating == RatingHelper::shortToInt("OBS"))
            return $this->isS1Eligible();
        if ($this->rating == RatingHelper::shortToInt("S1"))
        return $this->isS2Eligible();
        if ($this->rating == RatingHelper::shortToInt("S2"))
            return $this->isS3Eligible();
        if ($this->rating == RatingHelper::shortToInt("S3"))
            return $this->isC1Eligible();

        return false;
    }

    /**
     * @return bool
     */
    public function isS1Eligible()
    {
        if ($this->rating > RatingHelper::shortToInt("OBS"))
            return false;


        $er2 = ExamResults::where('cid', $this->cid)->where('exam_id', config('exams.BASIC'))->where('passed',1)->count();
        $er = ExamResults::where('cid', $this->cid)->where('exam_id', config('exams.S1'))->where('passed',1)->count();

        return ($er>=1 || $er2>=1);
    }

    /**
     * @return bool
     */
    public function isS2Eligible()
    {
        if ($this->rating != RatingHelper::shortToInt("S1"))
            return false;

        $er = ExamResults::where('cid', $this->cid)->where('exam_id', config('exams.S2'))->where('passed',1)->count();

        return ($er>=1);
    }

    /**
     * @return bool
     */
    public function isS3Eligible()
    {
        if ($this->rating != RatingHelper::shortToInt("S2"))
            return false;

        $er = ExamResults::where('cid', $this->cid)->where('exam_id', config('exams.S3'))->where('passed',1)->count();

        return ($er>=1);
    }

    /**
     * @return bool
     */
    public function isC1Eligible()
    {
        if ($this->rating != RatingHelper::shortToInt("S3"))
            return false;

        $er = ExamResults::where('cid', $this->cid)->where('exam_id', config('exams.C1'))->where('passed',1)->count();

        return ($er>=1);
    }

    /**
     * @return mixed
     */
    public function lastActivityWebsite() {
        return $this->lastactivity->diffInDays(null);
    }

    /**
     * @return string
     */
    public function lastActivityForum() {
        $f = \DB::connection('forum')->table("smf_members")->where("member_name", $this->cid)->first();
        return ($f) ? Carbon::createFromTimestamp($f->last_login)->diffInDays(null) : "Unknown";
    }

    /**
     * @param string $by
     * @param string $msg
     * @param string $newfac
     */
    public function removeFromFacility($by = "Automated", $msg = "None provided", $newfac = "ZAE")
    {
        $facility = $this->facility;
        $region = $this->facility()->region;
        $facname = $this->facility()->name;

        if ($facility != "ZAE") {
            EmailHelper::sendEmail(
                [$this->email, "$facility-atm@vatusa.net", "$facility-datm@vatusa.net", "vatusa$region@vatusa.net"],
                "Removal from $facname",
                "emails.user.removed",
                [
                    'name' => $this->fname . " " . $this->lname,
                    'facility' => $this->facname,
                    'by' => $by,
                    'msg' => $msg,
                    'facid' => $facility,
                    'region' => $region
                ]
            );
        }

        if ($by > 800000) { $byuser = User::find($by); $by = $byuser->fullname(); }

        log_action($this->id, "Removed from $facility by $by: $msg");

        $this->facility_join = \DB::raw("NOW()");
        $this->facility = $newfac;
        $this->save();

        $t = new Transfers();
        $t->cid = $this->cid;
        $t->to = $newfac;
        $t->from = $facility;
        $t->reason = $msg;
        $t->status = 1;
        $t->actiontext = $msg;
        $t->save();

        if ($this->rating >= RatingHelper::shortToInt("I1"))
            SMFHelper::createPost(7262, 82, "User Removal: " . $this->fullname() . " (" . RatingHelper::intToShort($this->rating) . ") from " . $facility, "User " . $this->fullname() . " (" . $this->cid . "/" . RatingHelper::intToShort($this->rating) . ") was removed from $facility and holds a higher rating.  Please check for demotion requirements.  [url=https://www.vatusa.net/mgt/controller/" . $this->cid . "]Member Management[/url]");
    }
}

