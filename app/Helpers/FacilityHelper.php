<?php

namespace App\Helpers;

use Cache;
use App\Facility;
use App\Exceptions\FacilityNotFoundException;

/**
 * Class FacilityHelper
 * @package App\Helpers
 */
class FacilityHelper
{
    /**
     * @param string $orderby
     * @param bool $all
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     * @throws \Exception
     */
    public static function getFacilities($orderby = 'name', $all = false)
    {
        // Is data cached?
        if ($all && Cache::has("facility.all")) {
            return Cache::get("facility.all");
        } elseif (!$all && Cache::has("facility.active")) {
            return Cache::get("facility.active");
        }

        if ($orderby != "id" && $orderby != "name") {
            throw new \Exception("Invalid orderby specified in FacilityHelper::getFacilities");
        }

        if (!$all) {
            $facilities = Facility::where('active', true)->orderBy($orderby)->get();
            \Cache::put('facility.active', $facilities, 60 * 24);   // Cache for 24 hours
        } else {
            $facilities = Facility::where('active', 'true')->orWhere('id', 'ZAE')->orWhere('id', 'ZHQ')->orderBy($orderby)->get();
            \Cache::put('facility.all', $facilities, 60 * 24);   // Cache for 24 hours
        }

        return $facilities;
    }

    /**
     * @param $facility
     * @return array
     */
    public static function getFacilityStaff($facility)
    {
        if (Cache::has("facility.$facility.staff")) {
            return Cache::get("facility.$facility.staff");
        }

        $fac = Facility::find($facility);
        if (!$fac || !$fac->active) {
            throw new \FacilityNotFoundException();
        }

        $data = [];
        $data["atm"] = static::staffArrayBuild(RoleHelper::find($facility, "ATM"));
        $data["datm"] = static::staffArrayBuild(RoleHelper::find($facility, "DATM"));
        $data["ta"] = static::staffArrayBuild(RoleHelper::find($facility, "TA"));
        $data["ec"] = static::staffArrayBuild(RoleHelper::find($facility, "EC"));
        $data["fe"] = static::staffArrayBuild(RoleHelper::find($facility, "FE"));
        $data["wm"] = static::staffArrayBuild(RoleHelper::find($facility, "WM"));

        Cache::put("facility.$facility.staff", 24 * 60);
        return $data;
    }

    /**
     * @param $staff
     * @return array
     */
    public static function staffArrayBuild($staff) {
        $data = [];
        foreach ($staff as $s) {
            $data[] = [
                'cid' => $s->cid,
                "name" => $s->fullname(),
                "email" => $s->email,
                "rating" => $s->rating
            ];
        }
        return $data;
    }
}
