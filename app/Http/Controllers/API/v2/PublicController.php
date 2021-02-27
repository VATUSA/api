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
 *     tags={"user","public"},
 *     produces={"application/json"},
 *     @SWG\Parameter(name="limit", in="path", type="integer", description="Limit"),
 *     @SWG\Response(
 *         response="200",
 *         description="OK",
 *         @SWG\Schema(
 *             ref="#/definitions/OK"
 *         ),
 *         examples={"application/json":{{}
 *         "status"="OK",
 *         "testing"=false
 *         }
 *         },
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
        ->orderBy('start_date', 'asc')
        ->get()
        ->toArray();

    return response()->ok([$data]);
}

/**
 *
 * @SWG\Get(
 *     path="/public/news/(limits)",
 *     summary="Get news.",
 *     description="Get news (from Forums) set with a specific limit",
 *     tags={"user","public"},
 *     produces={"application/json"},
 *     @SWG\Parameter(name="limit", in="path", type="integer", description="Limit"),
 *     @SWG\Response(
 *         response="200",
 *         description="OK",
 *         @SWG\Schema(
 *             ref="#/definitions/OK"
 *         ),
 *         examples={"application/json":{{}
 *         "status"="OK",
 *         "testing"=false
 *         }
 *         },
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
    $data = ForumMessages::where('id_board', 47)
        ->orderByDesc('id_msg')
        ->limit($limit)
        ->get()
        ->toArray();

    return response()->ok([$data]);
}

}

?>