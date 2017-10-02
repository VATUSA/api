<?php
namespace App\Http\Controllers\API\v1;

use App\Facility;
use App\Helpers\FacilityHelper;
use App\Helpers\RatingHelper;
use Illuminate\Http\Request;

/**
 * Class PublicController
 * @package App\Http\Controllers\API\v1
 */
class PublicController
{
    /**
     * @param Request $request
     * @param $apikey
     * @return string
     */
    public function getConnectionTest(Request $request, $apikey) {
        $data =[];
        $data['status'] = 'OK';
        $data['ip'] = $request->ip();
        if ($request->has('test')) {
            $data['debug'] = 1;
        } else {
            $data['debug'] = 0;
        }

        return json_encode($data);
    }

    /**
     * @param string $ext
     * @param int $limit
     *
     * Don't cache as SMF isn't integrated with the API
     */
    public function getEvents($ext = "json", $limit = 100)
    {
        //$ext = substr($ext, 1);
        if (is_numeric($ext) && $limit == 100) {
            // Safe to assume
            $limit = $ext;
            $ext = "json";
        }
        $ext = strtolower($ext);
        if ($limit > 100) {
            $limit = 100;
        }
        if (!in_array($ext, ["xml", "json"])) {
            $return['status'] = "error";
            $return['msg'] = "Unsupported data type";
            $ext = "json";
        } else {
            $return['status'] = "ok";
            $return['events'] = [];
            $results = \DB::connection('forum')->select("SELECT *, DATE_FORMAT(`start_date`, \"%c/%e/%Y\") AS `eventdate` FROM `smf_calendar` WHERE `start_date` > DATE_SUB(NOW(), INTERVAL 1 DAY) ORDER BY `start_date` ASC LIMIT $limit");
            foreach ($results as $result) {
                $return['events'][] = [
                    'id' => $result->id_topic,
                    'title' => $result->title,
                    'date' => $result->start_date,
                    'humandate' => $result->eventdate,
                    'url' => "https://forums.vatusa.net/index.php?topic=" . $result->id_topic . ".0"
                ];
            }
        }
        if ($ext == "xml") {
            $xmldata = new \SimpleXMLElement('<?xml version="1.0"?><api></api>');
            static::array_to_xml($return, $xmldata);
            return $xmldata->asXML();
        } elseif ($ext == "json") {
            return encode_json($return);
        }
    }

    /**
     * @param string $ext
     * @param int $limit
     *
     * Note: No caching to ensure latest news since forums run separately from the API
     */
    public function getNews($ext = "json", $limit = 100)
    {
        if (is_numeric($ext) && $limit == 100) {
            // Safe to assume
            $limit = $ext;
            $ext = "json";
        }
        $ext = strtolower($ext);
        if ($limit > 100) {
            $limit = 100;
        }
        if (!in_array($ext, ["xml", "json"])) {
            $return['status'] = "error";
            $return['msg'] = "Unsupported data type";
            $ext = "json";
        } else {
            $return['status'] = "ok";
            $return['news'] = [];
            $results = \DB::connection('forum')->select("SELECT `smf_topics`.`id_topic`,FROM_UNIXTIME(`smf_messages`.`poster_time`,\"%c/%e/%Y\") AS `humandate`,FROM_UNIXTIME(`smf_messages`.`poster_time`,\"%Y-%m-%d\") AS `sqldate`,`smf_messages`.`subject` FROM `smf_messages`,`smf_topics` WHERE `smf_topics`.`id_board`=47 AND `smf_topics`.`id_first_msg`=`smf_messages`.`id_msg` ORDER BY `smf_messages`.`poster_time` DESC LIMIT $limit");
            foreach ($results as $result) {
                $return['news'][] = [
                    'id' => $result->id_topic,
                    'subject' => $result->subject,
                    'humandate' => $result->humandate,
                    'date' => $result->sqldate,
                    'url' => "https://www.vatusa.net/forums/index.php?topic=" . $result->id_topic . ".0"
                ];
            }
        }
        if ($ext == "xml") {
            $xmldata = new \SimpleXMLElement('<?xml version="1.0"?><api></api>');
            static::array_to_xml($return, $xmldata);
            return $xmldata->asXML();
        } elseif ($ext == "json") {
            return encode_json($return);
        }
    }

    /**
     * @return string
     *
     */
    public function getPublicPlanes() {
        if (\Cache::has('vatsim.data'))
            return \Cache::get('vatsim.data');
        else
            return '[]';
    }

    /**
     * @param $facility
     * @param string $ext
     * @param null $limit (ignored)
     */
    public function getRoster($facility, $ext = "json", $limit = null)
    {
        $f = Facility::find($facility);
        $error = 0;
        if (!$f || !$f->active) {
            $return = generate_error("Invalid Facility");
            $error = 1;
        }
        if (is_numeric($ext) && $limit == null) {
            $limit = $ext;
            $ext = "json";
        }
        $ext = strtolower($ext);
        if (!in_array($ext, ["xml", "json"])) {
            $return = generate_error("Invalid format");
            $ext = "json";
            $error = 1;
        }
        if (!$error) {
            $return['status'] = "ok";
            $return['users'] = [];
            foreach (FacilityHelper::getRoster($facility) as $user) {
                $return['users'][] = [
                    'cid' => $user->cid,
                    'fname' => $user->fname,
                    'lname' => $user->lname,
                    'join_date' => $user->facility_join,
                    'promotion_eligible' => ($user->promotionEligible()) ? "1" : "0",
                    'rating' => $user->rating,
                    'rating_short' => RatingHelper::intToShort($user->rating)
                ];
            }
        }
        if ($ext == "xml") {
            $xmldata = new \SimpleXMLElement('<?xml version="1.0"?><api></api>');
            static::array_to_xml($return, $xmldata);
            return $xmldata->asXML();
        } elseif ($ext == "json") {
            return encode_json($return);
        }
    }

    /**
     * @param $data
     * @param $xmldata
     *
     * @deprecated v2 beyond will only return JSON
     */
    private static function array_to_xml($data, &$xmldata) {
        foreach ($data as $key => $value) {
            if (is_numeric($key))
                $key = "item$key";
            if (is_array($value)) {
                $subnode = $xmldata->addChild($key);
                static::array_to_xml($value, $subnode);
            } else {
                $xmldata->addChild($key, htmlspecialchars($value));
            }
        }
    }
}
