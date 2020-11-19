<?php

namespace App\Helpers;

use App\EmailTemplate;
use App\User;
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
     * @param bool   $all
     *
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
            $facilities = Facility::where('active', 'true')->orWhere('id', 'ZAE')->orWhere('id',
                'ZHQ')->orderBy($orderby)->get();
            \Cache::put('facility.all', $facilities, 60 * 24);   // Cache for 24 hours
        }

        return $facilities;
    }

    /**
     * @param $facility
     *
     * @return array
     * @throws FacilityNotFoundException
     */
    public static function getFacilityStaff($facility)
    {
        if ($facility instanceof Facility) {
            $facility = $facility->id;
        }
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

        Cache::put("facility.$facility.staff", $data, 24 * 60);

        return $data;
    }

    /**
     * @param $staff
     *
     * @return array
     */
    public static function staffArrayBuild($staff)
    {
        $data = [];
        foreach ($staff as $s) {
            $data[] = [
                'cid'    => $s->cid,
                "name"   => $s->user->fullname(),
                "email"  => $s->user->email,
                "rating" => $s->user->rating
            ];
        }

        return $data;
    }

    /**
     * @param      $facility
     * @param null $limit
     *
     * @return mixed
     * @throws FacilityNotFoundException
     */
    public static function getRoster($facility, $limit = null)
    {
        if ($facility instanceof Facility) {
            $facility = $facility->id;
        }
        if (Cache::has("facility.$facility.roster")) {
            return Cache::get("faciliy.$facility.roster");
        }

        $facility = Facility::find($facility);
        if (!$facility || $facility->active != 1) {
            throw new FacilityNotFoundException();
        }

        $roster = $facility->members()->orderby('rating', 'desc')->orderBy('lname', 'asc')->orderBy('fname',
            'asc')->get();
        Cache::put("facility.$facility.roster", $roster, env('CACHE_TIME_ROSTER', 10)); // low cache for v1 period

        return $roster;
    }

    public static function EmailTemplates()
    {
        return [
            'examassigned',
            'exampassed',
            'examfailed',
        ];
    }

    public static function EmailTemplateMap($id)
    {
        switch ($id) {
            case 'examassigned':
                return resource_path('views/emails/exam/assign.blade.php');
            case 'exampassed':
                return resource_path('views/emails/exam/passed.blade.php');
            case 'examfailed':
                return resource_path('views/emails/exam/failed.blade.php');
            default:
                abort(500, "$id is not known");
        }
    }

    public static function findEmailTemplate($id, $tn)
    {
        /*$template = EmailTemplate::where('facility_id', $id)->where('template', $tn)->first();
        if (!$template) {
            $template = new EmailTemplate();
            $template->facility_id = $id;
            $template->template = $tn;
            $template->body = file_get_contents(static::EmailTemplateMap($tn));
            $template->save();
        }*/
        $template = new EmailTemplate();
        $template->facility_id = $id;
        $template->template = $tn;
        $template->body = file_get_contents(static::EmailTemplateMap($tn));
        return $template;
    }

    public static function urlListToArray(string $list, string $delim = ",")
    {
        return array_map('trim', explode($delim, $list));
    }


    /**
     * Get facility's URL
     *
     * @param $facility
     *
     * @return mixed|string
     */
    public static function getURL($facility)
    {
        if ($facility instanceof Facility) {
            return $facility->url;
        }

        return Facility::findOrFail($facility)->url;
    }

    /**
     * Get facility's development URL(s)
     *
     * @param $facility
     *
     * @return array
     */
    public static function getDevURLs($facility)
    {
        if ($facility instanceof Facility) {
            return static::urlListToArray($facility->url_dev);
        }

        return static::urlListToArray(Facility::findOrFail($facility)->url_dev);
    }
}
