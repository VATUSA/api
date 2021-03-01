<?php

namespace App\Http\Controllers\API\v2;

use App\ForumCalendar;
use App\ForumMessages;
use Carbon\Carbon;
use Illuminate\Http\Request;

class PublicController extends APIController
{

    /**
     *
     * @SWG\Get(
     *     path="/public/events/(limit)",
     *     summary="Get events.",
     *     description="Get events (from Forums) set with a specific limit",
     *     tags={"public"},
     *     produces={"application/json"},
     *     @SWG\Parameter(name="limit", in="path", type="integer", description="Limit"),
     *     @SWG\Response(
     *         response="200",
     *         description="OK",
     *         @SWG\Schema(
     *             ref="#/definitions/OK"
     *         ),
     *         examples={"application/json":{"id_event":760,"start_date":"2000-03-30","end_date":"2000-03-30","id_board":0,"id_topic":0,"title":"FNOklahoma
               City","id_member":1021}}
     *     )
     * )
     * @param \Illuminate\Http\Request $request
     * @param                          $limit
     *
     * @return \Illuminate\Http\Response
     * @throws \Exception
     */

    public function getEvents(Request $request, $limit = 100)
    {
        $data = ForumCalendar::limit($limit)
            ->where('end_date', '>=', Carbon::now())
            ->orderBy('start_date')
            ->get()
            ->toArray();

        return response()->api($data);
    }

    /**
     *
     * @SWG\Get(
     *     path="/public/news/(limits)",
     *     summary="Get news.",
     *     description="Get news (from Forums) set with a specific limit",
     *     tags={"public"},
     *     produces={"application/json"},
     *     @SWG\Parameter(name="limit", in="path", type="integer", description="Limit"),
     *     @SWG\Response(
     *         response="200",
     *         description="OK",
     *         @SWG\Schema(
     *             ref="#/definitions/OK"
     *         ),
     *         examples={"application/json":{"id_msg":45163,"id_topic":10004,"id_board":47,"poster_time":1614395041,"id_member":2906,"id_msg_modified":45163,"subject":"Position Posting: VATUSA Web Team","poster_name":1275302,"poster_email":"","poster_ip":"","smileys_enabled":1,"modified_time":0,"modified_name":"","body":"","icon":"xx","approved":1}}
     *     )
     * )
     * @param \Illuminate\Http\Request $request
     * @param                          $limit
     *
     * @return \Illuminate\Http\Response
     * @throws \Exception
     */
    public function getNews(Request $request, $limit = 100)
    {
        $data = ForumMessages::where('smf_messages.id_board', 47)
            ->join("smf_topics", "id_msg", "id_first_msg")
            ->orderByDesc('id_msg')
            ->limit($limit)
            ->get()
            ->makeHidden(['poster_email', 'poster_ip'])
            ->toArray();

        return response()->api($data);
    }

}
