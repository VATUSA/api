<?php

namespace App\Http\Controllers\API\v2;
use App\Helpers\RoleHelper;
use App\TrainingBlock;
use App\TrainingChapter;
use Illuminate\Http\Request;

/**
 * Class CBTController
 * @package App\Http\Controllers\API\v2
 */
class CBTController extends APIController
{
    /**
     * @return array|string
     *
     *
     * @SWG\Get(
     *     path="/cbt",
     *     summary="(DONE) Get CBT Blocks filtered by facility (or all)",
     *     description="(DONE) Get CBT Blocks filtered by facility (or all)",
     *     produces={"application/json"},
     *     tags={"cbt"},
     *     @SWG\Parameter(name="facility", in="path", type="string", description="Filter by facility id"),
     *     @SWG\Response(
     *         response="200",
     *         description="OK",
     *         @SWG\Schema(
     *             type="object",
     *             @SWG\Property(
     *                 property="blocks",
     *                 type="array",
     *                 @SWG\Items(
     *                     ref="#/definitions/TrainingBlock"
     *                 ),
     *             ),
     *         ),
     *     )
     * ),
     */
    public function getBlocks(Request $request) {
        $facility = $request->input("facility", null);
        $blocks = TrainingBlock::orderBy('facility', 'ASC')->orderBy('order', 'ASC');
        if ($facility) {
            $blocks = $blocks->where('facility', $facility);
        }

        return response()->ok(['blocks' => $blocks->get()->toArray()]);
    }

    /**
     * @return array|string
     *
     * @SWG\Delete(
     *     path="/cbt/(id)",
     *     summary="(DONE) Delete block. Requires JWT, API Key, or Session Cookie",
     *     description="(DONE) Delete block. Requires JWT, API Key, or Session Cookie (required role: (N/A for API Key) ATM, DATM, TA, VATUSA STAFF)",
     *     produces={"application/json"},
     *     tags={"cbt"},
     *     security={"jwt","session","apikey"},
     *     @SWG\Parameter(name="id", in="path", required=true, type="integer", description="Block ID"),
     *     @SWG\Response(
     *         response="401",
     *         description="Unauthorized",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Unauthorized"}},
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Forbidden",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Forbidden"}},
     *     ),
     *     @SWG\Response(
     *         response="404",
     *         description="Not found",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Not found"}},
     *     ),
     *     @SWG\Response(
     *         response="200",
     *         description="OK",
     *         @SWG\Schema(ref="#/definitions/OK"),
     *         examples={"application/json":{"status"="OK"}}
     *     )
     * )
     */
    public function deleteBlock($id) {
        if (!\Auth::check()) return response()->unauthenicated();

        $block = TrainingBlock::find($id);
        if (!$block) return response()->notfound();

        if ($block->facility === "ZAE" && !RoleHelper::isVATUSAStaff()) return response()->forbidden();
        elseif (!RoleHelper::isVATUSAStaff() && !RoleHelper::has(\Auth::user()->cid, $block->facility, ['ATM','DATM','TA'])) {
            return response()->forbidden();
        }

        $block->deleteBlock();

        return response()->ok();
    }
    /**
     * @param int $cid
     * @param string $facility
     * @param string $role
     * @return array|string
     *
     * @SWG\Post(
     *     path="/cbt",
     *     summary="(DONE) Create new block. Requires JWT, API Key, or Session Cookie",
     *     description="(DONE) Create new block. Requires JWT, API Key or Session Cookie (required role: (N/A for API Key) ATM, DATM, TA, VATUSA STAFF)",
     *     produces={"application/json"},
     *     tags={"cbt"},
     *     security={"jwt","session","apikey"},
     *     @SWG\Parameter(name="facility", in="formData", required=true, type="string", description="Facility IATA ID"),
     *     @SWG\Parameter(name="name", in="formData", required=true, type="string", description="Name of block"),
     *     @SWG\Response(
     *         response="401",
     *         description="Unauthorized",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Unauthorized"}},
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Forbidden",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Forbidden"}},
     *     ),
     *     @SWG\Response(
     *         response="200",
     *         description="OK",
     *         @SWG\Schema(ref="#/definitions/OK"),
     *         examples={"application/json":{"status"="OK"}}
     *     )
     * )
     */
    public function postBlock(Request $request) {
        if (!\Auth::check()) return response()->unauthenicated();
        $default = [
            'level' => 'ALL',
            'visible' => 1,
            'order' => 1,
        ];

        $lastblock = TrainingBlock::where('facility', $request->input('facility'))->orderBy('order', 'DESC')->limit(1)->first();
        if ($lastblock) {
            $default['order'] = $lastblock->order + 1;
        }

        if ($request->input("facility") === "ZAE" && !RoleHelper::isVATUSAStaff()) return response()->forbidden();
        elseif (!RoleHelper::isVATUSAStaff() && !RoleHelper::has(\Auth::user()->cid, $request->input("facility"), ['ATM','DATM','TA'])) {
            return response()->forbidden();
        }

        if ($request->has("level") && in_array($request->input("level"), ["ALL","S1","C1","I1","Staff", "Senior Staff"])) {
            $default['level'] = $request->input("level");
        }
        if ($request->has("visible") && ($request->input("visible") == 1 ||$request->input("visible") == 0)) {
            $default['visible'] = $request->input("visible");
        }

        $block = new TrainingBlock();
        $block->facility = $request->input("facility", \Auth::user()->facility);
        $block->name = $request->input("name", "New Block");
        $block->level = $default['level'];
        $block->visible = $default['visible'];
        $block->save();

        return response()->ok();
    }

    /**
     *
     * @SWG\Put(
     *     path="/cbt/(id)",
     *     summary="(DONE) Edit CBT Block. Requires JWT, API Key, or Session Cookie",
     *     description="(DONE) Edit CBT Block. Requires JWT, API Key, or Session Cookie (required role: (N/A for API Key) ATM, DATM, TA, VATUSA STAFF)",
     *     produces={"application/json"},
     *     tags={"cbt"},
     *     security={"jwt","session","apikey"},
     *     @SWG\Parameter(name="id", in="path", type="integer", description="Block ID"),
     *     @SWG\Parameter(name="sortOrder", in="formData", type="integer", description="Order location, sort lowest to highest"),
     *     @SWG\Parameter(name="name", in="formData", type="string", description="Name of block"),
     *     @SWG\Parameter(name="visible", in="formData", type="boolean", description="Whether or not it is active/public"),
     *     @SWG\Parameter(name="level", in="formData", type="string", description="Access level (plain text options: All, S1, C1, I1, Staff, Senior Staff)"),
     *     @SWG\Response(
     *         response="400",
     *         description="Malformed",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Malformed"}},
     *     ),
     *     @SWG\Response(
     *         response="401",
     *         description="Unauthorized",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Unauthorized"}},
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Forbidden",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Forbidden"}},
     *     ),
     *     @SWG\Response(
     *         response="200",
     *         description="OK",
     *         @SWG\Schema(ref="#/definitions/OK"),
     *         examples={"application/json":{"status"="OK"}}
     *     )
     * )
     */
    public function putBlock(Request $request, $id) {
        if (!\Auth::check()) return response()->unauthenicated();

        $block = TrainingBlock::find($id);
        if (!$block) return response()->notfound();

        if ($block->facility === "ZAE" && !RoleHelper::isVATUSAStaff()) return response()->forbidden();
        elseif (!RoleHelper::isVATUSAStaff() && !RoleHelper::has(\Auth::user()->cid, $block->facility, ['ATM','DATM','TA'])) {
            return response()->forbidden();
        }

        if ($request->has("sortOrder")) {
            if (!is_numeric($request->has("sortOrder"))) return response()->malformed();
            $block->order = $request->input("sortOrder");
        }

        if ($request->has("name")) {
            if(strlen($request->input("name")) < 1) return response()->malformed();
            $block->name = $request->input("name");
        }

        if ($request->has("level")) {
            if (!in_array($request->input("level"), ['S1','C1','I1','Staff','Senior Staff'])) return response()->malformed();

            $block->level = $request->input("level");
        }

        if ($request->has("visible")) {
            if ($request->input("visible") != 0 && $request->input("visible") != 1) return response()->malformed();

            $block->visible = $request->input("visible");
        }

        $block->save();
        return response()->ok();
    }

    /**
     *
     * @SWG\Get(
     *     path="/cbt/(blockId)",
     *     summary="(DONE) Get Chapters for blockId",
     *     description="(DONE) Get Chapters for blockId",
     *     produces={"application/json"},
     *     tags={"cbt"},
     *     @SWG\Parameter(name="blockId", in="path", type="integer", description="Block ID"),
     *     @SWG\Response(
     *         response="200",
     *         description="OK",
     *         @SWG\Schema(
     *             type="object",
     *             @SWG\Property(property="blockId", type="integer", description="Block ID"),
     *             @SWG\Property(property="blockName", type="string", description="Block name"),
     *             @SWG\Property(property="facility", type="string", description="Facility IATA ID"),
     *             @SWG\Property(
     *                 property="chapters",
     *                 type="array",
     *                 @SWG\Items(
     *                     type="object",
     *                     @SWG\Property(property="id", type="integer", description="Chapter ID"),
     *                     @SWG\Property(property="sortOrder", type="integer", description="Order location, sort lowest to highest"),
     *                     @SWG\Property(property="name", type="string", description="Name of chapter"),
     *                     @SWG\Property(property="active", type="boolean", description="Whether or not it is active/public"),
     *                     @SWG\Property(property="link", type="string", description="Link to object"),
     *                 ),
     *             ),
     *         ),
     *     )
     * ),
     */
    public function getChapters($id) {
        $block = TrainingBlock::find($id);
        if (!$block) return response()->notfound();

        return response()->ok(["chapters" => $block->chapters->toArray()]);
    }

    /**
     * @return array|string
     *
     * @SWG\Delete(
     *     path="/cbt/(blockId)/(chapterId)",
     *     summary="(DONE) Delete chapter. Requires JWT or Session Cookie",
     *     description="(DONE) Delete chapter. Requires JWT or Session Cookie (required role: ATM, DATM, TA, VATUSA STAFF)",
     *     produces={"application/json"},
     *     tags={"cbt"},
     *     security={"jwt","session","apikey"},
     *     @SWG\Parameter(name="blockId", in="path", required=true, type="integer", description="Block ID"),
     *     @SWG\Parameter(name="chapterId", in="path", required=true, type="integer", description="Chapter ID"),
     *     @SWG\Response(
     *         response="401",
     *         description="Unauthorized",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Unauthorized"}},
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Forbidden",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Forbidden"}},
     *     ),
     *     @SWG\Response(
     *         response="404",
     *         description="Not found",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Not found"}},
     *     ),
     *     @SWG\Response(
     *         response="200",
     *         description="OK",
     *         @SWG\Schema(ref="#/definitions/OK"),
     *         examples={"application/json":{"status"="OK"}}
     *     )
     * )
     */
    public function deleteChapter($blockId, $chapterId) {
        if (!\Auth::check()) return response()->unauthenicated();

        $chapter = TrainingChapter::find($id);
        if (!$chapter) return response()->notfound();
        if ($chapter->blockid != $blockId) return response()->notfound();

        if ($chapter->block->facility === "ZAE" && !RoleHelper::isVATUSAStaff()) return response()->forbidden();
        elseif (!RoleHelper::isVATUSAStaff() && !RoleHelper::has(\Auth::user()->cid, $chapter->block->facility, ['ATM','DATM','TA'])) {
            return response()->forbidden();
        }

        $chapter->deleteChapter();

        return response()->ok();
    }
    /**
     * @param int $id
     * @return array|string
     *
     *
     * @SWG\Post(
     *     path="/cbt/(blockId)",
     *     summary="(DONE) Create new chapter. Requires JWT, API Key, or Session Cookie",
     *     description="(DONE) Create new chapter. Requires JWT, API Key, or Session Cookie (required role: (N/A for apikey) ATM, DATM, TA, VATUSA STAFF)",
     *     produces={"application/json"},
     *     tags={"cbt"},
     *     security={"jwt","session","apikey"},
     *     @SWG\Parameter(name="blockId", in="path", required=true, type="integer", description="Block ID"),
     *     @SWG\Parameter(name="facility", in="formData", required=true, type="string", description="Facility IATA ID"),
     *     @SWG\Parameter(name="name", in="formData", required=true, type="string", description="Name of block"),
     *     @SWG\Parameter(name="url", in="formData", required=true, type="string", description="URL of chapter object"),
     *     @SWG\Response(
     *         response="401",
     *         description="Unauthorized",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Unauthorized"}},
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Forbidden",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Forbidden"}},
     *     ),
     *     @SWG\Response(
     *         response="404",
     *         description="Not found",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Not found"}},
     *     ),
     *     @SWG\Response(
     *         response="200",
     *         description="OK",
     *         @SWG\Schema(ref="#/definitions/OK"),
     *         examples={"application/json":{"status"="OK"}}
     *     )
     * )
     */
    public function postChapter(Request $request, $id) {
        $block = TrainingBlock::find($id);
        if (!\Auth::check()) return response()->unauthorized;
        if (!$block) return response()->notfound();
        if ($block->facility === "ZAE" && !RoleHelper::isVATUSAStaff()) return response()->forbidden();
        elseif (!RoleHelper::isVATUSAStaff() && !RoleHelper::has(\Auth::user()->cid, $block->facility, ['ATM','DATM','TA'])) {
            return response()->forbidden();
        }
        $x = 1;
        $prevchap = TrainingChapter::where('blockid', $id)->orderBy("order", "DESC")->limit(1)->first();
        if ($prevchap) { $x = $prevchap->order + 1; }

        $chapter = new TrainingChapter();
        $chapter->blockid = $id;
        $chapter->order = $x;
        $chapter->name = $request->input("name", "New chapter");
        $chapter->url = $request->input("url");
        $chapter->visible = $request->input("visible");
        $chapter->save();

        return response()->ok();
    }

    /**
     *
     *
     * @SWG\Put(
     *     path="/cbt/(blockId)/(chapterId)",
     *     summary="(DONE) Edit CBT Chapter. Requires JWT or Session Cookie",
     *     description="(DONE) Edit CBT Chapter. Requires JWT or Session Cookie (required role: ATM, DATM, TA, VATUSA STAFF)",
     *     produces={"application/json"},
     *     tags={"cbt"},
     *     security={"jwt","session","apikey"},
     *     @SWG\Parameter(name="blockId", in="path", required=true, type="string", description="Block ID"),
     *     @SWG\Parameter(name="chapterId", in="path", type="integer", description="Chapter ID"),
     *     @SWG\Parameter(name="sortOrder", in="formData", type="integer", description="Order location, sort lowest to highest"),
     *     @SWG\Parameter(name="name", in="formData", type="string", description="Name of block"),
     *     @SWG\Parameter(name="active", in="formData", type="boolean", description="Whether or not it is active/public"),
     *     @SWG\Parameter(name="url", in="formData", type="string", description="Link to object (PDF, YouTube, or other embeddable object"),
     *     @SWG\Response(
     *         response="401",
     *         description="Unauthorized",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Unauthorized"}},
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Forbidden",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Forbidden"}},
     *     ),
     *     @SWG\Response(
     *         response="200",
     *         description="OK",
     *         @SWG\Schema(ref="#/definitions/OK"),
     *         examples={"application/json":{"status"="OK"}}
     *     )
     * )
     */
    public function putChapter(Request $request, $bid, $cid) {
        $chapter = TrainingChapter::find($cid);
        if (!$chapter || $chapter->blockid != $bid) return response()->notfound();

        if (!\Auth::check()) return response()->unauthorized;
        if ($chapter->block->facility === "ZAE" && !RoleHelper::isVATUSAStaff()) return response()->forbidden();
        elseif (!RoleHelper::isVATUSAStaff() && !RoleHelper::has(\Auth::user()->cid, $chapter->block->facility, ['ATM','DATM','TA'])) {
            return response()->forbidden();
        }

        if ($request->has("sortOrder")) {
            if (!is_numeric($request->input("sortOrder"))) return response()->malformed();

            $chapter->order = $request->input("sortOrder");
        }
        if ($request->has("visible")) {
            if (!is_numeric($request->input("visible"))) return response()->malformed;
            $chapter->visible = $request->input("visible");
        }

        if ($request->has("url")) {
            if (!filter_var($request->input("url"), FILTER_VALIDATE_URL)) return response()->malformed();

            $chapter->url = $request->input("url");
        }

        $chapter->save();
        return response()->ok();
    }
}
