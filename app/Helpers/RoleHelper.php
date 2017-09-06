<?php
namespace App\Helpers;

use Cache;
use App\Role;
use App\User;

/**
 * Class RoleHelper
 * @package App\Helpers
 */
class RoleHelper {
    /**
     * @param $facility
     * @param $role
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public static function find($facility, $role) {
        if (Cache::has("role.$facility.$role")) {
            return Cache::get("role.$facility.$role");
        }

        $role = Role::where("facility", $facility)->where("role", $role)->get();
        Cache::put("role.$facility.$role", 24 * 60);
        return $role;
    }

    /**
     * @param $facility
     * @param $cid
     * @param $role
     */
    public static function add($facility, $cid, $role) {
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
     * @return bool
     */
    public static function delete($facility, $cid, $role) {
        $role = Role::where("facility", $facility)->where("cid", $cid)->where("role", $role)->delete();
        Cache::forget("role.$facility.$role");
        return true;
    }

    /**
     * @param $cid
     * @param $facility
     * @param $role
     * @return bool
     */
    public static function has($cid, $facility, $role) {
        $role = Role::where("facility", $facility)->where("cid", $cid)->where("role", $role)->count();
        if ($role) {
            return true;
        }
        return false;
    }

    /**
     * @param $cid
     * @param $facility
     * @param bool $includeTA
     * @return bool
     */
    public static function isSeniorStaff($cid, $facility, $includeTA = false) {
        if (static::has($cid, $facility, "ATM") ||
            static::has($cid, $facility, "DATM") ||
            ($includeTA && static::has($cid, $facility, "TA"))) {
            return true;
        }

        return false;
    }

    public static function isVATUSAStaff($cid = null, $skipWebTeam = false) {
        $user = User::where('cid', $cid)->first();
        if ($user == null) {
            return false;
        }

        if ($user->facility == "ZHQ") {
            return true;
        }

        if (!$skipWebTeam) {
            if (Role::where("facility", "ZHQ")->where("cid", $cid)->where("role", "LIKE", "US%")->count() >= 1) {
                return true;
            }
        } else {
            if (Role::where('facility','ZHQ')->where("cid", $cid)->where("role", "LIKE", "US%")->where("role","NOT LIKE","USWT")->count() >= 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param null|integer $cid
     * @return bool
     */
    public static function isWebTeam($cid = null) {
        if ($cid == null || $cid == 0) {
            $cid = \Auth::user()->cid;
            $user = \Auth::user();
        } else {
            $user = User::where('cid', $cid)->first();
        }
        if (!$user) {
            return false;
        }
        if (Role::where("facility", "ZHQ")->where("cid", $cid)->where("role","USWT")->count() >= 1) {
            return true;
        }

        return false;
    }
}
