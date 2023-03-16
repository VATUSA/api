<?php

namespace App\Helpers;

use App\Facility;
use Cache;
use App\Role;
use App\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

/**
 * Class RoleHelper
 * @package App\Helpers
 */
class RoleHelper
{
    /**
     * @param $facility
     * @param $role
     *
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public static function find($facility, $role)
    {
        if (Cache::has("role.$facility.$role")) {
            return Cache::get("role.$facility.$role");
        }
        $role = Role::where("facility", $facility)->where("role", $role)->get();
        Cache::put("role.$facility.$role", 24 * 60 * 60);

        return $role;
    }

    /**
     * @param $facility
     * @param $cid
     * @param $role
     */
    public static function add($facility, $cid, $role)
    {
        $role = new Role();
        $role->cid = $cid;
        $role->facility = $facility;
        $role->role = $role;
        $role->save();
        Cache::forget("role.$facility.$role");
    }

    /**
     * @param $facility
     * @param $cid
     * @param $role
     *
     * @return bool
     */
    public static function delete($facility, $cid, $role)
    {
        $role = Role::where("facility", $facility)->where("cid", $cid)->where("role", $role)->delete();
        Cache::forget("role.$facility.$role");

        return true;
    }

    /**
     * @param              $cid
     * @param              $facility
     * @param string|array $role
     *
     * @return bool
     */
    public static function has($cid, $facility, $role)
    {
        if (is_array($role)) {
            if ($facility instanceof Facility) {
                $facility = $facility->id;
            }
            foreach ($role as $r) {
                $rq = Role::where("facility", $facility)->where("cid", $cid)->where("role", $r)->count();
                if ($rq) {
                    return true;
                }
            }

            return false;
        }

        $r = Role::where("facility", $facility)->where("cid", $cid)->where("role", $role)->count();
        if ($r >= 1) {
            return true;
        }

        return false;
    }

    /**
     * Is the user allowed to modify a given role?
     *
     * @param User     $user
     * @param Facility $facility
     * @param string   $role
     *
     * @return bool
     */
    public static function canModify(User $user, Facility $facility, string $role)
    {
        if ($facility === "ZHQ") {
            if (static::isVATUSAStaff($user->cid, true)) {
                return true;
            }

            return false;
        }

        switch ($role) {
            case "ATM":
            case "DATM":
            case "TA":
                if (static::isVATUSAStaff($user->cid, true)) {
                    return true;
                }
                break;
            default:
                if (static::has($user->cid, $facility, ['ATM', 'DATM']) || static::isVATUSAStaff($user->cid)) {
                    return true;
                }
                break;
        }

        return false;
    }

    /**
     * @param      $cid
     * @param      $facility
     * @param bool $includeTA
     *
     * @return bool
     */
    public static function isSeniorStaff($cid = null, $facility = null, $includeTA = false)
    {
        if (!$cid && !Auth::check()) {
            return false;
        }
        if (!$cid) {
            $cid = Auth::user()->cid;
        }
        $user = User::find($cid);
        if (!$user) {
            return false;
        }
        if (!$facility) {
            $facility = $user->facility;
        }

        if (($includeTA && static::has($cid, $facility, ['ATM', 'DATM', 'TA'])) ||
            static::has($cid, $facility, ['ATM', 'DATM'])) {

            return true;
        }
        if (static::isVATUSAStaff($cid)) {
            return true;
        }

        return false;
    }

    public static function isFacilityStaff($cid = null, $facility = null)
    {
        if (!$cid && !Auth::check()) {
            return false;
        }
        if (!$cid) {
            $cid = Auth::user()->cid;
        }
        $user = User::find($cid);
        if (!$user) {
            return false;
        }
        if (!$facility) {
            $facility = $user->facility;
        }
        if (static::has($cid, $facility, ['ATM', 'DATM', 'TA', 'WM', 'FE', 'EC'])) {
            return true;
        }
        if (static::isVATUSAStaff($cid)) {
            return true;
        }

        return false;
    }

    public static function isInstructor($cid = null, $facility = null)
    {
        if (!$cid && !Auth::check()) {
            return false;
        }
        if (!$cid) {
            $cid = Auth::user()->cid;
        }
        if (!$facility && Auth::check()) {
            $facility = Auth::user()->facility;
        }

        if ($facility instanceof Facility) {
            $facility = $facility->id;
        }

        $user = User::find($cid);
        if (!$facility) {
            $facility = $user->facility;
        }

        // Check home controller, if no always assume no
        if (!$user->flag_homecontroller) {
            return false;
        }

        // First check home facility and rating (excluding SUP)
        if ($user->facility == $facility && $user->rating >= Helper::ratingIntFromShort("I1") && $user->rating < Helper::ratingIntFromShort("SUP")) {
            return true;
        }

        //ADMs have INS Access
        if ($user->rating == Helper::ratingIntFromShort("ADM")) {
            return true;
        }

        // Check for an instructor role
        if (Role::where("facility", $facility)->where("cid", $cid)->where("role", "INS")->count()) {
            return true;
        }

        // Check for VATUSA staff, global access.
        if (static::isVATUSAStaff($cid)) {
            return true;
        }

        return false;
    }

    public static function isMentor($cid = null, $facility = null)
    {
        if (!$cid && !Auth::check()) {
            return false;
        }
        if (!$cid) {
            $cid = Auth::user()->cid;
        }

        if (!$facility && Auth::check()) {
            $facility = Auth::user()->facilityObj;
        }
        if (!($facility instanceof Facility)) {
            $facility = Facility::find($facility);
        }

        $user = User::find($cid);
        if (!$facility) {
            $facility = $user->facilityObj;
        }
        if (!$user->flag_homecontroller) {
            return false;
        }
        if (!$facility->active && $facility != "ZHQ") {
            return false;
        }

        if (Role::where("cid", $cid)->where("facility", $facility->id)->where("role", "MTR")->count()) {
            return true;
        }

        return false;
    }

    public static function isTrainingStaff($cid = null, bool $includeMentor = true, $facility = null)
    {
        return ($includeMentor && self::isMentor($cid, $facility)) || self::isInstructor($cid,
                $facility) || self::isSeniorStaff($cid, $facility,
                true);
    }

    public static function isVATUSAStaff($cid = null, $skipWebTeam = false, $isApi = false)
    {
        if (!\Auth::check() && !$isApi) {
            return false;
        }
        if ($cid == null || $cid == 0) {
            $cid = \Auth::user()->cid;
        }

        $user = User::where('cid', $cid)->first();
        if ($user == null) {
            return false;
        }

        /*if ($user->facility == "ZHQ") {
            return true;
        }*/

        if (!$skipWebTeam) {
            if (Role::where("facility", "ZHQ")->where("cid", $cid)->where("role", "LIKE", "US%")->count() >= 1) {
                return true;
            }
        } else {
            if (Role::where('facility', 'ZHQ')->where("cid", $cid)->where("role", "LIKE", "US%")->where("role",
                    "NOT LIKE", "USWT")->count() >= 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param null|integer $cid
     *
     * @return bool
     */
    public static function isWebTeam($cid = null)
    {
        if ($cid == null || $cid == 0) {
            $cid = \Auth::user()->cid;
            $user = \Auth::user();
        } else {
            $user = User::where('cid', $cid)->first();
        }
        if (!$user) {
            return false;
        }
        if (static::has($cid, "ZHQ", "US6")) {
            return true;
        }
        if (Role::where("facility", "ZHQ")->where("cid", $cid)->where("role", "USWT")->count() >= 1) {
            return true;
        }

        return false;
    }



    /**
     * @param      $cid
     * @param      $facility
     * @param      $role
     *
     * @return bool
     */
    public static function hasRole($cid, $facility, $role, $isApi = false)
    {
        if (Schema::hasColumn('facilities', strtolower($role))) {
            $c = Facility::where(strtolower($role), $cid)->where('id', $facility)->count();
            if ($c) {
                return true;
            }
        }

        $c = Role::where('role', $role)->where('cid', $cid)->where('facility', $facility)->count();
        if ($c) {
            return true;
        }

        return false;
    }
}
