<?php namespace App;

use App\Helpers\EmailHelper;
use App\Helpers\RatingHelper;
use App\Helpers\RoleHelper;
use Illuminate\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Tymon\JWTAuth\Contracts\JWTSubject;

/**
 * Class User
 * @package App
 *
 * @SWG\Definition(
 *     type="object",
 *     @SWG\Property(property="cid", type="integer"),
 *     @SWG\Property(property="fname", type="string", description="First name"),
 *     @SWG\Property(property="lname", type="string", description="Last name"),
 *     @SWG\Property(property="facility", type="string", description="Facility ID"),
 *     @SWG\Property(property="rating", type="integer", description="Rating based off array where 0=OBS, S1, S2, S3, C1, C2, C3, I1, I2, I3, SUP, ADM"),
 *     @SWG\Property(property="created_at", type="string", description="Date added to database"),
 *     @SWG\Property(property="updated_at", type="string"),
 *     @SWG\Property(property="flag_needbasic", type="integer", description="1 needs basic exam"),
 *     @SWG\Property(property="flag_xferOverride", type="integer", description="Has approved transfer override"),
 *     @SWG\Property(property="facility_join", type="string", description="Date joined facility"),
 *     @SWG\Property(property="flag_homecontroller", type="integer", description="1-Belongs to VATUSA"),
 *     @SWG\Property(property="lastactivity", type="string", description="Date last seen on website")
 * )
 */
class User extends Model implements AuthenticatableContract, JWTSubject
{
    use Authenticatable;

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
        return $this->belongsTo('App\Facility', 'facility');
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

    public function addToFacility($facility)
    {
        $oldfac = $this->facility;
        $facility = Facility::find($facility);
        $oldfac = Facility::find($oldfac);

        $this->facility = $facility->id;
        $this->facility_join = \DB::raw("NOW()");
        $this->save();

        if ($this->rating >= RatingHelper::shortToInt("I1") && $this->rating < RatingHelper::shortToInt("SUP")) {
            SMFHelper::createPost(7262, 82, "User Addition: ".$this->fullname()." (".RatingHelper::intToShort($this->rating).") to ".$this->facility, "User ".$this->fullname()." (".$this->cid."/".RatingHelper::intToShort($this->rating).") was added to ".$this->facility." and holds a higher rating.\n\nPlease check for demotion requirements.\n\n[url=https://www.vatusa.net/mgt/controller/".$this->cid."]Member Management[/url]");
        }

        $fc = 0;

        if ($oldfac->id != "ZZN" && $oldfac->id != "ZAE") {
            if (RoleHelper::has($this->cid, $oldfac->id, "ATM") || RoleHelper::has($this->cid, $oldfac->id, "DATM")) {
                EmailHelper::sendEmail(["vatusa" . $oldfac->region . "@vatusa.net"], "ATM or DATM discrepancy", "emails.transfers.atm", ["user" => $this, "oldfac" => $oldfac]);
                $fc = 1;
            }
            elseif (RoleHelper::has($this->cid, $oldfac->id, "TA")) {
                EmailHelper::sendEmail(["vatusa3@vatusa.net"], "TA discrepancy", "emails.transfers.ta", ["user" => $this, "oldfac" => $oldfac]);
                $fc = 1;
            }
            elseif (RoleHelper::has($this->cid, $oldfac->id, "EC") || RoleHelper::has($this->cid, $oldfac->id, "FE") || RoleHelper::has($this->cid, $oldfac->id, "WM")) {
                EmailHelper::sendEmail([$oldfac->id . "-atm@vatusa.net", $oldfac->id . "-datm@vatusa.net"], "Staff discrepancy", "emails.transfers.otherstaff", ["user" => $this, "oldfac" => $oldfac]);
                $fc = 1;
            }
        }

        if ($fc) {
            SMFHelper::createPost(7262, 82, "Staff discrepancy on transfer: " . $this->fullname() . " (" . RatingHelper::intToShort($this->rating), "User " . $this->fullname() . " (" . $this->cid . "/" . RatingHelper::intToShort($this->rating) . ") was added to facility " . $this->facility . " but holds a staff position at " . $oldfac->id . ".\n\nPlease check for accuracy.\n\n[url=https://www.vatusa.net/mgt/controller/" . $this->cid . "]Member Management[/url] [url=https://www.vatusa.net/mgt/facility/" . $oldfac->id . "]Facility Management for Old Facility[/url] [url=https://www.vatusa.net/mgt/facility/" . $this->facility . "]Facility Management for New Facility[/url]");
        }

        if ($facility->active) {
            $welcome = $facility->welcome_text;
            $fac = $facility->id;
            EmailHelper::sendWelcomeEmail(
                [$this->email],
                "Welcome to " . $facility->name,
                'emails.user.welcome',
                [
                    'welcome' => $welcome,
                    'fname' => $this->fname,
                    'lname' => $this->lname
                ]
            );
            EmailHelper::sendEmail([
                "$fac-atm@vatusa.net",
                "$fac-datm@vatusa.net",
                "vatusa" . $facility->region . "@vatusa.net"
            ], "User added to facility", "emails.user.addedtofacility", [
                "name" => $this->fullname(),
                "cid" => $this->cid,
                "email" => $this->email,
                "rating" => RatingHelper::intToShort($this->rating),
                "facility" => $fac
            ]);
        }
    }

    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     *
     * @return mixed
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return [];
    }
}

