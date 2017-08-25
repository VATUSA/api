<?php
namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Facility;

/**
 * Class FacilityController
 * @package App\Http\Controllers\API
 */
class FacilityController extends Controller
{
    /**
     * @param null $all
     * @return array|string
     */
    public function getIndex($all = null) {
        if ($all == "all") {
            $all = true;
        } else {
            $all = false;
        }

        if ($all) {
            if (\Cache::has("facility.list.all")) {
                return \Cache::get("facility.list.all");
            }
        } else {
            if (\Cache::has("facility.list.active")) {
                return \Cache::get("facility.list.active");
            }
        }

        $facilities = \FacilityHelper::getFacilities("name", $all);
        $data = [];
        foreach ($facilities as $facility) {
            $facility[] = [
                'id' => $facility->id,
                'name' => $facility->name,
                'url' => $facility->url
            ];
        }
        $data = json_encode($data);

        // Store for 24 hours
        if ($all) {
            \Cache::put("facility.list.all", $data, 24*60);
        } else {
            \Cache::put("facility.list.active", $data, 24*60);
        }

        return $data;
    }
}
