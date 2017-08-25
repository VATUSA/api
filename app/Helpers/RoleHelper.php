<?php
namespace App\Helpers;

use Cache;
use App\Role;

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
        $role = Role::where("facility", $facility)->where("cid", $cid)->where("role", $role)->get();
        if (!$role) { return false; }
        $role->delete();
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
        $role = Role::where("facility", $facility)->where("cid", $cid)->where("role", $role)->get();
        if ($role) {
            return true;
        }
        return false;
    }
}
