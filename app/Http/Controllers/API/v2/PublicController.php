<?php

namespace App\Http\Controllers\API\v2;

use App\ForumCalendar;
use App\ForumMessages;
use Cache;
use Carbon\Carbon;
use Illuminate\Http\Request;

class PublicController extends APIController
{

    /**
     *
     * @OA\Get(
     *     path="/public/events/(limit)",
     *     summary="Get events.",
     *     description="Get events (from Forums) set with a specific limit",
     *     tags={"public"},
     *     @OA\Parameter(name="limit", in="path", @OA\Schema(type="integer"), description="Limit"),
     *     @OA\Response(
     *         response="200",
     *         description="OK",
     *         @OA\Schema(
     *             ref="#/components/schemas/OK"
     *         ),
     *         
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
            ->where('start_date', '>=', Carbon::now()->subHours(24))
            ->orderBy('start_date')
            ->get()
            ->toArray();

        return response()->api($data);
    }

    /**
     *
     * @OA\Get(
     *     path="/public/news/(limits)",
     *     summary="Get news.",
     *     description="Get news (from Forums) set with a specific limit",
     *     tags={"public"},
     *     @OA\Parameter(name="limit", in="path", @OA\Schema(type="integer"), description="Limit"),
     *     @OA\Response(
     *         response="200",
     *         description="OK",
     *         @OA\Schema(
     *             ref="#/components/schemas/OK"
     *         ),
     *         
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

    /**
     *
     * @OA\Get(
     *     path="/public/planes",
     *     summary="Get planes for TMU.",
     *     description="Get online planes, used for TMU",
     *     tags={"public"},
     *     @OA\Response(
     *         response="200",
     *         description="OK",
     *         @OA\Schema(
     *             ref="#/components/schemas/OK"
     *         ),
     *         
     *     )
     * )
     * @return \Illuminate\Http\Response
     */
    public function getPlanes()
    {
        if (Cache::has('vatsim.data')) {
            return response()->api(json_decode(Cache::get('vatsim.data')));
        } else {
            return response()->api([]);
        }
    }
}
