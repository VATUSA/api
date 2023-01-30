<?php namespace App;

use App\Classes\OAuth\VatsimConnect;
use App\Helpers\CERTHelper;
use App\Helpers\ExamHelper;
use App\Helpers\Helper;
use App\Helpers\EmailHelper;
use App\Helpers\RatingHelper;
use App\Helpers\RoleHelper;
use App\Helpers\SMFHelper;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Support\Facades\Cache;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Carbon\Carbon;
use League\OAuth2\Client\Token\AccessToken;
use Illuminate\Support\Str;

/**
 * Class User
 * @package App
 *
 * @SWG\Definition(
 *     type="object",
 *     @SWG\Property(property="cid", type="integer"),
 *     @SWG\Property(property="fname", type="string", description="First name"),
 *     @SWG\Property(property="lname", type="string", description="Last name"),
 *     @SWG\Property(property="email", type="string", description="Email address of user, will be null if API Key or
                                       necessary roles are not available (ATM, DATM, TA, WM, INS)"),
 *     @SWG\Property(property="facility", type="string", description="Facility ID"),
 *     @SWG\Property(property="rating", type="integer", description="Rating based off array where 1=OBS, S1, S2, S3,
                                        C1, C2, C3, I1, I2, I3, SUP, ADM"),
 *     @SWG\Property(property="rating_short", type="string", description="String representation of rating"),
 *     @SWG\Property(property="created_at", type="string", description="Date added to database"),
 *     @SWG\Property(property="updated_at", type="string"),
 *     @SWG\Property(property="flag_needbasic", type="integer", description="1 needs basic exam"),
 *     @SWG\Property(property="flag_xferOverride", type="integer", description="Has approved transfer override"),
 *     @SWG\Property(property="flag_broadcastOptedIn", type="integer", description="Has opted in to receiving broadcast
                                                       emails"),
 *     @SWG\Property(property="flag_preventStaffAssign", type="integer", description="Ineligible for staff role
                                                         assignment"),
 *     @SWG\Property(property="facility_join", type="string", description="Date joined facility (YYYY-mm-dd
                                               hh:mm:ss)"),
 *     @SWG\Property(property="promotion_eligible", type="boolean", description="Is member eligible for promotion?"),
 *     @SWG\Property(property="transfer_eligible", type="boolean", description="Is member is eligible for transfer?"),
 *     @SWG\Property(property="last_promotion", type="string", description="Date last promoted"),
 *     @SWG\Property(property="flag_homecontroller", type="boolean", description="1-Belongs to VATUSA"),
 *     @SWG\Property(property="lastactivity", type="string", description="Date last seen on website"),
 *     @SWG\Property(property="isMentor", type="boolean", description="Has Mentor role"),
 *     @SWG\Property(property="isSupIns", type="boolean", description="Is a SUP and has INS role"),
 *     @SWG\Property(property="roles", type="array",
 *         @SWG\Items(type="object",
 *             @SWG\Property(property="facility", type="string"),
 *             @SWG\Property(property="role", type="string")
 *         )
 *     ),
 *     @SWG\Property(property="visiting_facilities", type="array",
 *         @SWG\Items(type="object",
 *             @SWG\Property(property="id", type="string"),
 *             @SWG\Property(property="name", type="string"),
 *             @SWG\Property(property="region", type="integer")
 *         )
 *     )
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
    //public $timestamps = ["created_at", "updated_at", "prefname_date", "facility_join"];

    protected $dates = ["lastactivity", "facility_join", "prefname_date"];

    /**
     * @var array
     */
    protected $hidden = [
        "password",
        "remember_token",
        "cert_update",
        "access_token",
        "refresh_token",
        "token_expires",
        "discord_id",
        "prefname",
        "prefname_date"
    ];

    protected $fillable = ["access_token", "refresh_token", "token_expires"];

    protected $appends = ["promotion_eligible", "transfer_eligible", "roles"];

    protected $casts = [
        'flag_needbasic'          => 'boolean',
        'flag_xferOverride'       => 'boolean',
        'flag_homecontroller'     => 'boolean',
        'flag_broadcastOptedIn'   => 'boolean',
        'flag_preventStaffAssign' => 'boolean'
    ];


    /**
     * @return string
     */
    public function fullname()
    {
        return $this->fname . " " . $this->lname;
    }

    public function facilityObj()
    {
        return $this->belongsTo(Facility::class, 'facility');
    }

    public function facility()
    {
        return $this->belongsTo(Facility::class, 'facility')->first();
    }

    public function trainingRecords()
    {
        return $this->hasMany(TrainingRecord::class, 'student_id', 'cid');
    }

    public function trainingRecordsIns()
    {
        return $this->hasMany(TrainingRecord::class, 'instructor_id', 'cid');
    }

    public function evaluations()
    {
        return $this->hasMany(OTSEval::class, 'student_id', 'cid');
    }

    public function evaluationsIns()
    {
        return $this->hasMany(OTSEval::class, 'instructor_id', 'cid');
    }

    public function visits()
    {
        return $this->hasMany(Visit::class, 'cid', 'cid');
    }

    public function lastPromotion()
    {
        return $this->hasMany(Promotion::class, 'cid', 'cid')->latest()->first();
    }

    /**
     * @return bool
     */
    public function promotionEligible()
    {
        if (!$this->flag_homecontroller) {
            Cache::set("promotionEligible-$this->cid", false);

            return false;
        }

        $result = false;
        if ($this->rating == RatingHelper::shortToInt("OBS")) {
            $result = $this->isS1Eligible();
        }
        if ($this->rating == RatingHelper::shortToInt("S1")) {
            $result = $this->isS2Eligible();
        }
        if ($this->rating == RatingHelper::shortToInt("S2")) {
            $result = $this->isS3Eligible();
        }
        if ($this->rating == RatingHelper::shortToInt("S3")) {
            $result = $this->isC1Eligible();
        }

        Cache::set("promotionEligible-$this->cid", $result);

        return $result;
    }

    /**
     * @return bool
     */
    public function isS1Eligible()
    {
        if ($this->rating > Helper::ratingIntFromShort("OBS")) {
            return false;
        }

        return !$this->flag_needbasic;
    }

    public function isS2Eligible()
    {
        if ($this->rating != Helper::ratingIntFromShort("S1")) {
            return false;
        }

        return ExamHelper::academyPassedExam($this->cid, "S2");
    }

    public function isS3Eligible()
    {
        if ($this->rating != Helper::ratingIntFromShort("S2")) {
            return false;
        }

        return ExamHelper::academyPassedExam($this->cid, "S3");

    }

    public function isC1Eligible()
    {
        if ($this->rating != Helper::ratingIntFromShort("S3")) {
            return false;
        }

        return ExamHelper::academyPassedExam($this->cid, "C1");

    }

    /**
     * @return mixed
     */
    public function lastActivityWebsite()
    {
        return $this->lastactivity->diffInDays(null);
    }

    /**
     * @return string
     */
    public function lastActivityForum()
    {
        $f = \DB::connection('forum')->table("smf_members")->where("member_name", $this->cid)->first();

        return ($f) ? Carbon::createFromTimestamp($f->last_login)->diffInDays(null) : "Unknown";
    }

    public function hasEmailAccess($email)
    {
        $eparts = explode("@", $email);

        if (RoleHelper::isVATUSAStaff($this->cid)) {
            return true;
        }

        if ($eparts[1] == "vatusa.net") {
            // Facility address
            // (facility)-(position)@vatusa.net
            if (strpos($eparts[0], "-") >= 1) {
                $uparts = explode("-", $eparts[0]);
                // ATMs,DATMs,WM have access to all addresses
                if (RoleHelper::isSeniorStaff($this->cid, $uparts[0], false) || RoleHelper::has($this->cid, $uparts[0],
                        "WM")) {
                    return true;
                }
                if (RoleHelper::has($this->cid, $uparts[0], $uparts[1])) {
                    return true;
                } else {
                    return false;
                }
            }
            // Staff address
            // vatusa#@vatusa.net
            if (preg_match("/^vatusa(\d+)/", $eparts[0], $match)) {
                if (RoleHelper::has($this->cid, "ZHQ", "US" . $match[1])) {
                    return true;
                }

                return false;
            }
        } else {
            $facility = Facility::where("hosted_email_domain", $eparts[1])->first();
            if (!$facility) {
                return false;
            }

            if (RoleHelper::isSeniorStaff($this->cid, $facility->id, false) || RoleHelper::has($this->cid,
                    $facility->id, "WM")) {
                return true;
            }

            $access = EmailAccounts::where("username", $eparts[0])->where("facility", $facility->id)->first();

            if ($access->cid == $this->cid) {
                return true;
            }
        }

        return false;
    }

    /**
     * Remove from Facility
     *
     * @param string $by
     * @param string $msg
     * @param string $newfac
     *
     * @throws \Exception
     */
    public function removeFromFacility($by = "Automated", $msg = "None provided", $newfac = "ZAE")
    {
        $old_facility = $this->facility;
        $region = $this->facilityObj->region;
        $facname = $this->facilityObj->name;

        if ($old_facility != "ZAE") {
            EmailHelper::sendEmail(
                [
                    $this->email,
                    "$old_facility-atm@vatusa.net",
                    "$old_facility-datm@vatusa.net",
                    "vatusa2@vatusa.net"
                ],
                "Removal from $facname",
                "emails.user.removed",
                [
                    'name'        => $this->fname . " " . $this->lname,
                    'facility'    => $this->facname,
                    'by'          => User::findName($by),
                    'msg'         => $msg,
                    'facid'       => $old_facility,
                    'region'      => $region,
                    'obsInactive' => $this->rating == 1 && Str::contains($msg,
                            ['inactive', 'inactivity', 'Inactive', 'Inactivity', 'activity', 'Activity'])
                ]
            );
        }

        if ($by > 800000) {
            $byuser = User::find($by);
            $by = $byuser->fullname();
        }

        log_action($this->cid, "Removed from $old_facility by $by: $msg");

        if ($this->rating == RatingHelper::shortToInt("OBS") &&
            env('EXIT_SURVEY', null) != null &&
            !in_array($old_facility, ["ZZN", "ZAE", "ZHQ"]) &&
            $newfac == "ZAE") {
            SurveyAssignment::assign(Survey::find(env('EXIT_SURVEY_ID')), $this, ['region' => $region]);
        }

        $this->facility_join = Carbon::now();
        $this->facility = $newfac;

        /**
         * Demote I1 on transfer to ZAE to C1/C3 based on their previous rating.
         */
        if ($this->rating == RatingHelper::shortToInt("I1") && $newfac == "ZAE") {
            $dm = new Promotion();
            $pm_hist = $dm->where('cid', $this->cid)
                ->where('to', RatingHelper::shortToInt("I1"))
                ->orderBy('id', 'desc')->first();
            // visiting controllers have no promotion record
            if ($pm_hist != null) {
                $original_rating = $pm_hist->from;
                $dm->cid = $this->cid;
                $dm->grantor = 0; // automated
                $dm->from = RatingHelper::shortToInt("I1");
                $dm->to = $original_rating;
                $dm->save();
                CERTHelper::changeRating($this->cid, $original_rating, false);
                $this->rating = $original_rating; // save within this function, not using CERTHelper
                log_action($this->cid,
                    "Demoted to " . RatingHelper::intToShort($original_rating) . " on transfer to ZAE");
            }
            // remove MTR/INS role
            $role = new Role();
            $mtr_ins_query = $role->where("cid", $this->cid)
                ->where("facility", $old_facility)
                ->where(function ($query) {
                    $query->where("role", "MTR")->orWhere("role", "INS");
                });
            if ($mtr_ins_query->count()) {
                $mtr_ins_query->delete();
                log_action($this->cid, "MTR/INS role removed on transfer to ZAE");
            }
        }

        /** Remove from visiting rosters if going to ZAE */
        if ($newfac == "ZAE" && $this->visits) {
            foreach ($this->visits as $visit) {
                log_action($this->cid, "User removed from {$visit->facility} visiting roster: Transfer to ZAE");
                $visit->delete();
            }
        }

        $this->save();

        $t = new Transfer();
        $t->cid = $this->cid;
        $t->to = $newfac;
        $t->from = $old_facility;
        $t->reason = $msg;
        $t->status = 1;
        $t->actiontext = $msg;
        $t->save();

        Cache::forget("roster-$old_facility-home");
        Cache::forget("roster-$old_facility-both");

        if ($this->rating >= RatingHelper::shortToInt("I1")) {
            SMFHelper::createPost(7262, 82,
                "User Removal: " . $this->fullname() . " (" . RatingHelper::intToShort($this->rating) . ") from " . $old_facility,
                "User " . $this->fullname() . " (" . $this->cid . "/" . RatingHelper::intToShort($this->rating) . ") was removed from $old_facility and holds a higher rating.  Please check for demotion requirements.  [url=https://www.vatusa.net/mgt/controller/" . $this->cid . "]Member Management[/url]");
        }
    }

    public function addToFacility($facility)
    {
        $oldfac = $this->facility;
        $facility = Facility::find($facility);
        $oldfac = Facility::find($oldfac);

        $this->facility = $facility->id;
        $this->facility_join = Carbon::now();
        $this->save();

        if ($this->rating >= RatingHelper::shortToInt("I1") && $this->rating < RatingHelper::shortToInt("SUP")) {
            SMFHelper::createPost(7262, 82,
                "User Addition: " . $this->fullname() . " (" . RatingHelper::intToShort($this->rating) . ") to " . $this->facility,
                "User " . $this->fullname() . " (" . $this->cid . "/" . RatingHelper::intToShort($this->rating) . ") was added to " . $this->facility . " and holds a higher rating.\n\nPlease check for demotion requirements.\n\n[url=https://www.vatusa.net/mgt/controller/" . $this->cid . "]Member Management[/url]");
        }

        $fc = 0;

        if ($oldfac->id != "ZZN" && $oldfac->id != "ZAE") {
            if (RoleHelper::has($this->cid, $oldfac->id, "ATM") || RoleHelper::has($this->cid, $oldfac->id, "DATM")) {
                EmailHelper::sendEmail(["vatusa2@vatusa.net"], "ATM or DATM discrepancy",
                    "emails.transfers.atm", ["user" => $this, "oldfac" => $oldfac]);
                $fc = 1;
            } elseif (RoleHelper::has($this->cid, $oldfac->id, "TA")) {
                EmailHelper::sendEmail(["vatusa3@vatusa.net"], "TA discrepancy", "emails.transfers.ta",
                    ["user" => $this, "oldfac" => $oldfac]);
                $fc = 1;
            } elseif (RoleHelper::has($this->cid, $oldfac->id, "EC") || RoleHelper::has($this->cid, $oldfac->id,
                    "FE") || RoleHelper::has($this->cid, $oldfac->id, "WM")) {
                EmailHelper::sendEmail([$oldfac->id . "-atm@vatusa.net", $oldfac->id . "-datm@vatusa.net"],
                    "Staff discrepancy", "emails.transfers.otherstaff", ["user" => $this, "oldfac" => $oldfac]);
                $fc = 1;
            }
        }

        if ($fc) {
            SMFHelper::createPost(7262, 82,
                "Staff discrepancy on transfer: " . $this->fullname() . " (" . RatingHelper::intToShort($this->rating),
                "User " . $this->fullname() . " (" . $this->cid . "/" . RatingHelper::intToShort($this->rating) . ") was added to facility " . $this->facility . " but holds a staff position at " . $oldfac->id . ".\n\nPlease check for accuracy.\n\n[url=https://www.vatusa.net/mgt/controller/" . $this->cid . "]Member Management[/url] [url=https://www.vatusa.net/mgt/facility/" . $oldfac->id . "]Facility Management for Old Facility[/url] [url=https://www.vatusa.net/mgt/facility/" . $this->facility . "]Facility Management for New Facility[/url]");
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
                    'fname'   => $this->fname,
                    'lname'   => $this->lname
                ]
            );
            EmailHelper::sendEmail([
                "$fac-atm@vatusa.net",
                "$fac-datm@vatusa.net",
                "vatusa2@vatusa.net"
            ], "User added to facility", "emails.user.addedtofacility", [
                "name"     => $this->fullname(),
                "cid"      => $this->cid,
                "email"    => $this->email,
                "rating"   => RatingHelper::intToShort($this->rating),
                "facility" => $fac
            ]);

            $this->visits()->where('facility', $fac)->delete();
            Cache::forget("roster-$fac-home");
            Cache::forget("roster-$fac-both");
        }
    }

    public static function findName($cid, $returnCID = false)
    {
        $ud = User::where('cid', $cid)->count();
        if ($ud) {
            $u = User::where('cid', $cid)->first();

            return ($returnCID ? $u->fname . ' ' . $u->lname . ' - ' . $u->cid : $u->fname . ' ' . $u->lname);
        } elseif ($cid == "0") {
            return "Automated";
        } else {
            return 'Unknown';
        }
    }

    public function roles()
    {
        return $this->hasMany("App\Role", "cid", "cid");
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

    public function transferEligible(&$checks = null)
    {
        if ($checks === null) {
            $checks = [];
        }

        if (!is_array($checks)) {
            $checks = [];
        }

        $checks['homecontroller'] = false;
        $checks['needbasic'] = false;
        $checks['pending'] = false;
        $checks['initial'] = false;
        $checks['90days'] = false;
        $checks['promo'] = false;
        $checks['override'] = false;
        $checks['is_first'] = true;

        if ($this->flag_homecontroller) {
            $checks['homecontroller'] = true;
        } else {
            $checks['homecontroller'] = false;
        }


        if (!$this->flag_needbasic || ExamHelper::academyPassedExam($this->cid, "basic", 0, 6)) {
            $checks['needbasic'] = true;
        }

        // true = check passed

        // Pending transfer request
        $transfer = Transfer::where('cid', $this->cid)->where('status', 0)->count();
        if (!$transfer) {
            $checks['pending'] = true;
        }

        $checks['initial'] = true;
        if (!in_array($this->facility, ["ZAE", "ZZN", "ZHQ"])) {
            if (Transfer::where('cid', $this->cid)->where('to', 'NOT LIKE', 'ZAE')->where('to', 'NOT LIKE',
                    'ZZN')->where('status', 1)->count() == 1) {
                if ($this->facility_join->diffInDays(Carbon::now()) <= 30) {
                    $checks['initial'] = true;
                }
            } else {
                $checks['is_first'] = false;
            }
        } else {
            $checks['is_first'] = false;
        }
        $transfer = Transfer::where('cid', $this->cid)->where('status', 1)->where('to', 'NOT LIKE', 'ZAE')->where('to',
            'NOT LIKE', 'ZZN')->where('status', 1)->orderBy('created_at', 'DESC')->first();
        if (!$transfer) {
            $checks['90days'] = true;
        } else {
            $checks['days'] = Carbon::createFromFormat('Y-m-d H:i:s', $transfer->updated_at)->diffInDays(new Carbon());
            if ($checks['days'] >= 90) {
                $checks['90days'] = true;
            } else {
                $checks['90days'] = false;
            }
        }

        // S1-C1 within 90 check
        $promotion = Promotion::where('cid', $this->cid)->where([
            ['to', '<=', Helper::ratingIntFromShort("C1")],
            ['created_at', '>=', \DB::raw("DATE(NOW() - INTERVAL 90 DAY)")]
        ])->whereRaw('promotions.to > promotions.from')->first();

        if ($promotion == null) {
            $checks['promo'] = 1;
        } else {
            $checks['promo'] = 0;
        }

        if ($this->rating >= RatingHelper::shortToInt("I1") && $this->rating <= RatingHelper::shortToInt("I3")) {
            $checks['instructor'] = false;
        } else {
            $checks['instructor'] = true;
        }

        $checks['staff'] = true;
        if (RoleHelper::isFacilityStaff($this->cid, $this->facility)) {
            $checks['staff'] = false;
        }

        // Override flag
        if ($this->flag_xferOverride) {
            $checks['override'] = true;
        } else {
            $checks['override'] = false;
        }

        if ($checks['override']) {
            return true;
        }
        if ($checks['instructor'] && $checks['staff'] && $checks['homecontroller'] && $checks['needbasic'] && $checks['pending'] && (($checks['is_first'] && $checks['initial']) || $checks['90days']) && $checks['promo']) {
            return true;
        } else {
            return false;
        }
    }

    public function setTransferOverride($value = 0)
    {
        $this->flag_xferoverride = $value;
        $this->save();

        log_action($this->cid,
            "Transfer override flag " . ($value) ? "enabled by " . \Auth::user()->fullname() : "removed");
    }

    public function getPrimaryRole()
    {
        if ($this->facility()->atm == $this->cid) {
            return "ATM";
        }
        if ($this->facility()->datm == $this->cid) {
            return "DATM";
        }
        if ($this->facility()->ta == $this->cid) {
            return "TA";
        }
        if ($this->facility()->ec == $this->cid) {
            return "EC";
        }
        if ($this->facility()->fe == $this->cid) {
            return "FE";
        }
        if ($this->facility()->wm == $this->cid) {
            return "WM";
        }

        for ($i = 1; $i <= 14; $i++) {
            if (RoleHelper::has($this->cid, "ZHQ", "US$i")) {
                return $i;
            }
        }

        return false;
    }

    public function getRolesAttribute()
    {
        return Role::where('cid', $this->cid)->get();
    }

    public function getPromotionEligibleAttribute()
    {
        return Cache::get("promotionEligible-$this->cid");
    }

    public function getTransferEligibleAttribute()
    {
        if (Helper::testCORS()) {
            return $this->transferEligible();
        } else {
            return null;
        }
    }

    public function getNameAttribute()
    {
        return $this->fullname();
    }

    public function getFullNameAttribute()
    {
        return $this->fullname();
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

    /**
     * When doing $user->token, return a valid access token or null if none exists
     *
     * @return \League\OAuth2\Client\Token\AccessToken
     * @return null
     */
    public function getTokenAttribute()
    {
        if ($this->access_token === null) {
            return null;
        } else {
            $token = new AccessToken([
                'access_token'  => $this->access_token,
                'refresh_token' => $this->refresh_token,
                'expires'       => $this->token_expires,
            ]);
            if ($token->hasExpired()) {
                $token = VatsimConnect::updateToken($token);
            }

            $this->update([
                'access_token'  => ($token) ? $token->getToken() : null,
                'refresh_token' => ($token) ? $token->getRefreshToken() : null,
                'token_expires' => ($token) ? $token->getExpires() : null,
            ]);

            return $token;
        }
    }

    public function resolveRouteBinding($value)
    {
        return $this->where($this->getRouteKeyName(), $value)->first() ?? abort(404);
    }

    public function checkPromotionCriteria(
        &$trainingRecordStatus,
        &$otsEvalStatus,
        &$examPosition,
        &$dateOfExam,
        &$evalId
    ) {
        $trainingRecordStatus = 0;
        $otsEvalStatus = 0;

        $dateOfExam = null;
        $examPosition = null;
        $evalId = null;

        $evals = $this->evaluations;
        $numPass = 0;
        $numFail = 0;

        if ($evals) {
            foreach ($evals as $eval) {
                if ($eval->form->rating_id == $this->rating + 1) {
                    if ($eval->result) {
                        $dateOfExam = $eval->exam_date;
                        $examPosition = $eval->exam_position;
                        $evalId = $eval->id;
                        $numPass++;
                    } else {
                        $numFail++;
                    }
                }
            }
            if ($numPass) {
                $otsEvalStatus = 1;
            } elseif ($numFail) {
                $otsEvalStatus = 2;
            }
        }

        switch (Helper::ratingShortFromInt($this->rating + 1)) {
            case 'S1':
                $pos = "GND";
                break;
            case 'S2':
                $pos = "TWR";
                break;
            case 'S3':
                $pos = "APP";
                break;
            case 'C1':
                $pos = "CTR";
                break;
            default:
                $pos = "NA";
                break;
        }
        if ($this->trainingRecords()->where([
            ['position', 'like', "%$pos"],
            'ots_status' => 1
        ])->exists()) {
            $trainingRecordStatus = 1;
        }

        if ($pos == "GND") {
            $trainingRecordStatus = $otsEvalStatus = -1;
        }
    }
}
