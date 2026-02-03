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
     * @param              $user
     * @param              $facility
     * @param string|array $role
     *
     * @return bool
     */
    public static function has(User $user, $facility, $role)
    {
        if (is_array($role)) {
            if ($facility instanceof Facility) {
                $facility = $facility->id;
            }
            foreach ($role as $r) {
                if ($user->roles->where("facility", $facility)->where("role", $r)->count()) {
                    return true;
                }
            }

            return false;
        }

        if ($user->roles->where("facility", $facility)->where("role", $role)->count()) {
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
            if (static::isVATUSAStaff($user, true)) {
                return true;
            }

            return false;
        }

        switch ($role) {
            case "ATM":
            case "DATM":
            case "TA":
                if (static::isVATUSAStaff($user, true)) {
                    return true;
                }
                break;
            default:
                if (static::has($user, $facility, ['ATM', 'DATM']) || static::isVATUSAStaff($user)) {
                    return true;
                }
                break;
        }

        return false;
    }

    /**
     * @param      $user
     * @param      $facility
     * @param bool $includeTA
     *
     * @return bool
     */
    public static function isSeniorStaff(User $user, $facility = null, $includeTA = false)
    {
        if (!$facility) {
            $facility = $user->facility;
        }

        if (($includeTA && static::has($user, $facility, ['ATM', 'DATM', 'TA'])) ||
            static::has($user, $facility, ['ATM', 'DATM'])) {

            return true;
        }
        if (static::isVATUSAStaff($user)) {
            return true;
        }

        return false;
    }

    public static function isFacilityStaff(User $user, $facility = null)
    {
        if (!$facility) {
            $facility = $user->facility;
        }
        if (static::has($user, $facility, ['ATM', 'DATM', 'TA', 'WM', 'FE', 'EC'])) {
            return true;
        }
        if (static::isVATUSAStaff($user)) {
            return true;
        }

        return false;
    }

    public static function isInstructor(User $user, $facility = null)
    {
        if (!$facility && Auth::check()) {
            $facility = Auth::user()->facility;
        }

        if ($facility instanceof Facility) {
            $facility = $facility->id;
        }

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
        if ($user->roles->where("facility", $facility)->where("role", "INS")->count()) {
            return true;
        }

        // Check for VATUSA staff, global access.
        if (static::isVATUSAStaff($user)) {
            return true;
        }

        return false;
    }

    public static function isMentor(User $user, $facility = null)
    {
        if (!$facility && Auth::check()) {
            $facility = Auth::user()->facilityObj;
        }
        if (!($facility instanceof Facility)) {
            $facility = Facility::find($facility);
        }

        if (!$facility) {
            $facility = $user->facilityObj;
        }
        if (!$user->flag_homecontroller) {
            return false;
        }
        if (!$facility->active && $facility != "ZHQ") {
            return false;
        }

        if ($user->roles->where("facility", $facility->id)->where("role", "MTR")->count()) {
            return true;
        }

        return false;
    }

    public static function isTrainingStaff(User $user, bool $includeMentor = true, $facility = null)
    {
        return ($includeMentor && self::isMentor($user, $facility)) || self::isInstructor($user,
                $facility) || self::isSeniorStaff($user, $facility,
                true);
    }

    public static function isVATUSAStaff(User $user, $skipWebTeam = false, $isApi = false)
    {
        if (!\Auth::check() && !$isApi) {
            return false;
        }

        /*if ($user->facility == "ZHQ") {
            return true;
        }*/

        if (!$skipWebTeam) {
            if (Role::where("facility", "ZHQ")->where("cid", $user->cid)->where("role", "LIKE", "US%")->count() >= 1) {
                return true;
            }
        } else {
            if (Role::where('facility', 'ZHQ')->where("cid", $user->cid)->where("role", "LIKE", "US%")->where("role",
                    "NOT LIKE", "USWT")->count() >= 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param null|User $user
     *
     * @return bool
     */
    public static function isWebTeam(User $user)
    {
        if (static::has($user, "ZHQ", "US6")) {
            return true;
        }
        if ($user->roles->where("facility", "ZHQ")->where("role", "USWT")->count()) {
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

    /*******************************************************************************************************************
     *
     * USER OBJECT BASED FUNCTIONS TO PREVENT N+1 QUERIES
     *
     *******************************************************************************************************************/

    public static function userHas(User $user, $facility, $role)
    {
        if (is_array($role)) {
            if ($facility instanceof Facility) {
                $facility = $facility->id;
            }
            foreach ($role as $r) {
                if ($user->roles->where("facility", $facility)->where("role", $r)->count()) {
                    return true;
                }
            }
            return false;
        }
        if ($user->roles->where("facility", $facility)->where("role", $role)->count()) {
            return true;
        }
        return false;
    }

    public static function userIsSeniorStaff(User $user, $facility = null, $includeTA = false)
    {
        if (!$facility) {
            $facility = $user->facility;
        }
        if (($includeTA && static::userHas($user, $facility, ['ATM', 'DATM', 'TA'])) ||
            static::userHas($user, $facility, ['ATM', 'DATM'])) {
            return true;
        }
        if (static::userIsVATUSAStaff($user)) {
            return true;
        }
        return false;
    }

    public static function userIsInstructor(User $user, $facility = null)
    {
        if (!$facility) {
            $facility = $user->facility;
        }

        if ($facility instanceof Facility) {
            $facility = $facility->id;
        }

        if (!$user->flag_homecontroller) {
            return false;
        }
        if ($user->facility == $facility && $user->rating >= Helper::ratingIntFromShort("I1") && $user->rating < Helper::ratingIntFromShort("SUP")) {
            return true;
        }
        if ($user->rating == Helper::ratingIntFromShort("ADM")) {
            return true;
        }
        if (static::userHas($user, $facility, "INS")) {
            return true;
        }
        if (static::userIsVATUSAStaff($user)) {
            return true;
        }
        return false;
    }

    public static function userIsMentor(User $user, $facility = null)
    {
        if (!$facility) {
            $facility = $user->facilityObj;
        }
        if (!($facility instanceof Facility)) {
            $facility = Facility::find($facility);
            if (!$facility) return false;
        }

        if (!$user->flag_homecontroller) {
            return false;
        }
        if (!$facility->active && $facility != "ZHQ") {
            return false;
        }

        return static::userHas($user, $facility->id, "MTR");
    }

    public static function userIsVATUSAStaff(User $user, $skipWebTeam = false)
    {
        $zhqRoles = $user->roles->where("facility", "ZHQ");

        if (!$skipWebTeam) {
            return $zhqRoles->filter(function ($role) {
                return str_starts_with($role->role, 'US');
            })->isNotEmpty();
        } else {
            return $zhqRoles->filter(function ($role) {
                return str_starts_with($role->role, 'US') && $role->role !== 'USWT';
            })->isNotEmpty();
        }
    }
}
