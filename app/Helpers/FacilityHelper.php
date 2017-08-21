<?php
namespace App\Helpers;

use Cache;
use App\Facility;

class FacilityHelper {
  public static function getFacilities($orderby = 'name', $all = false) {
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
      $facilities = Facility::where('active','true')->orWhere('id', 'ZAE')->orWhere('id','ZHQ')->orderBy($orderby)->get();
      \Cache::put('facility.all', $facilities, 60 * 24);   // Cache for 24 hours
    }

    return $facilities;
  }
}
