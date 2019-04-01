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
     * @param \Illuminate\Http\Request $request
     *
     * @return array|string
     *
     *
     * @SWG\Get(
     *     path="/cbt",
     *     summary="Get blocks filtered by facility.",
     *     description="Get CBT Blocks filtered by facility.",
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
    public function getBlocks(Request $request)
    {
        $facility = $request->input("facility", null);
        $blocks = TrainingBlock::orderBy('facility', 'ASC')->orderBy('order', 'ASC');
        if ($facility) {
            $blocks = $blocks->where('facility', $facility);
        }

        return response()->ok(['blocks' => $blocks->get()->toArray()]);
    }

    /**
     * @param $id
     *
     * @return array|string
     *
     * @SWG\Delete(
     *     path="/cbt/(id)",
     *     summary="Delete block. [Auth]",
     *     description="Delete block. Requires JWT or Session Cookie (required role: ATM, DATM, TA, VATUSA STAFF)",
     *     produces={"application/json"},
     *     tags={"cbt"},
     *     security={"jwt","session"},
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
    public function deleteBlock($id)
    {
        if (!\Auth::check()) {
            return response()->unauthenticated();
        }

        $block = TrainingBlock::find($id);
        if (!$block) {
            return response()->notfound();
        }

        if ($block->facility === "ZAE" && !RoleHelper::isVATUSAStaff()) {
            return response()->forbidden();
        } elseif (!RoleHelper::isVATUSAStaff() && !RoleHelper::has(\Auth::user()->cid, $block->facility,
                ['ATM', 'DATM', 'TA'])) {
            return response()->forbidden();
        }

        if (!isTest()) {
            $block->deleteBlock();
        }

        return response()->ok();
    }

    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return array|string
     *
     * @SWG\Post(
     *     path="/cbt",
     *     summary="Create new block. [Auth]",
     *     description="Create new block. Requires JWT or Session Cookie (required role: ATM, DATM, TA, VATUSA STAFF)",
     *     produces={"application/json"},
     *     tags={"cbt"},
     *     security={"jwt","session"},
     *     @SWG\Parameter(name="facility", in="formData", required=true, type="string", description="Facility IATA
     *                                     ID"),
     *     @SWG\Parameter(name="name", in="formData", required=true, type="string", description="Name of block"),
     *     @SWG\Parameter(name="level", in="formData", required=false, type="string", description="Rating level - ALL,
     *                                  S1, C1, I1, Staff, Senior Staff"),
     *     @SWG\Parameter(name="visible", in="formData", required=false, type="boolean", description="Block is
     *                                    visible"),
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
    public function postBlock(Request $request)
    {
        if (!\Auth::check()) {
            return response()->unauthenticated();
        }

        $default = [
            'level'   => 'ALL',
            'visible' => 1,
            'order'   => 1,
        ];

        $lastblock = TrainingBlock::where('facility', $request->input('facility'))->orderBy('order',
            'DESC')->limit(1)->first();
        if ($lastblock) {
            $default['order'] = $lastblock->order + 1;
        }

        if ($request->input("facility") === "ZAE" && !RoleHelper::isVATUSAStaff()) {
            return response()->forbidden();
        } elseif (!RoleHelper::isVATUSAStaff() && !RoleHelper::has(\Auth::user()->cid, $request->input("facility"),
                ['ATM', 'DATM', 'TA'])) {
            return response()->forbidden();
        }

        if ($request->has("level") && in_array($request->input("level"),
                ["ALL", "S1", "C1", "I1", "Staff", "Senior Staff"])) {
            $default['level'] = $request->input("level");
        }
        if ($request->has("visible") && ($request->input("visible") == 1 || $request->input("visible") == 0)) {
            $default['visible'] = $request->input("visible");
        }

        if (!isTest()) {
            $block = new TrainingBlock();
            $block->facility = $request->input("facility", \Auth::user()->facility);
            $block->name = $request->input("name", "New Block");
            $block->level = $default['level'];
            $block->visible = $default['visible'];
            $block->save();
        }

        return response()->ok();
    }

    /**
     *
     * @SWG\Put(
     *     path="/cbt/(id)",
     *     summary="Edit block. [Auth]",
     *     description="Edit CBT Block. Requires JWT or Session Cookie (required role: ATM, DATM, TA, VATUSA STAFF)",
     *     produces={"application/json"},
     *     tags={"cbt"},
     *     security={"jwt","session"},
     *     @SWG\Parameter(name="id", in="path", type="integer", required=true, description="Block ID"),
     *     @SWG\Parameter(name="sortOrder", in="formData", type="integer", description="Order location, sort lowest to
     *                                      highest"),
     *     @SWG\Parameter(name="name", in="formData", type="string", description="Name of block"),
     *     @SWG\Parameter(name="visible", in="formData", type="boolean", description="Whether or not it is
     *                                    active/public"),
     *     @SWG\Parameter(name="level", in="formData", type="string", description="Access level (plain text options:
     *                                  All, S1, C1, I1, Staff, Senior Staff)"),
     * @SWG\Response(
     *         response="400",
     *         description="Malformed",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Malformed"}},
     *     ),
     * @SWG\Response(
     *         response="401",
     *         description="Unauthorized",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Unauthorized"}},
     *     ),
     * @SWG\Response(
     *         response="403",
     *         description="Forbidden",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Forbidden"}},
     *     ),
     * @SWG\Response(
     *         response="200",
     *         description="OK",
     *         @SWG\Schema(ref="#/definitions/OK"),
     *         examples={"application/json":{"status"="OK"}}
     *     )
     * )
     * @param \Illuminate\Http\Request $request
     * @param int                      $id
     *
     * @return \Illuminate\Http\Response
     */
    public function putBlock(Request $request, $id)
    {
        if (!\Auth::check()) {
            return response()->unauthenticated();
        }

        $block = TrainingBlock::find($id);
        if (!$block) {
            return response()->notfound();
        }

        if ($block->facility === "ZAE" && !RoleHelper::isVATUSAStaff()) {
            return response()->forbidden();
        } elseif (!RoleHelper::isVATUSAStaff() && !RoleHelper::has(\Auth::user()->cid, $block->facility,
                ['ATM', 'DATM', 'TA'])) {
            return response()->forbidden();
        }

        if ($request->has("sortOrder")) {
            if (!is_numeric($request->has("sortOrder"))) {
                return response()->malformed();
            }
            $block->order = $request->input("sortOrder");
        }

        if ($request->has("name")) {
            if (strlen($request->input("name")) < 1) {
                return response()->malformed();
            }
            $block->name = $request->input("name");
        }

        if ($request->has("level")) {
            if (!in_array($request->input("level"), ['S1', 'C1', 'I1', 'Staff', 'Senior Staff'])) {
                return response()->malformed();
            }

            $block->level = $request->input("level");
        }

        if ($request->has("visible")) {
            if ($request->input("visible") != 0 && $request->input("visible") != 1) {
                return response()->malformed();
            }

            $block->visible = $request->input("visible");
        }

        if (!isTest()) {
            $block->save();
        }

        return response()->ok();
    }

    /**
     *
     * @SWG\Get(
     *     path="/cbt/(blockId)",
     *     summary="Get chapters in block.",
     *     description="Get Chapters for specified CBT Block.",
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
     *                     @SWG\Property(property="sortOrder", type="integer", description="Order location, sort lowest
     *                                                         to highest"),
     *                     @SWG\Property(property="name", type="string", description="Name of chapter"),
     *                     @SWG\Property(property="active", type="boolean", description="Whether or not it is
     *                                                      active/public"),
     *                     @SWG\Property(property="link", type="string", description="Link to object"),
     *                 ),
     *             ),
     *         ),
     *     )
     * ),
     * @param $id
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getChapters($id)
    {
        $block = TrainingBlock::find($id);
        if (!$block) {
            return response()->notfound();
        }

        return response()->ok(["chapters" => $block->chapters->toArray()]);
    }

    /**
     * @param $blockId
     * @param $chapterId
     *
     * @return array|string
     *
     * @SWG\Delete(
     *     path="/cbt/(blockId)/(chapterId)",
     *     summary="Delete chapter. [Auth]",
     *     description="Delete chapter. Requires JWT or Session Cookie (required role: ATM, DATM, TA, VATUSA STAFF)",
     *     produces={"application/json"},
     *     tags={"cbt"},
     *     security={"jwt","session"},
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
    public function deleteChapter($blockId, $chapterId)
    {
        if (!\Auth::check()) {
            return response()->unauthenticated();
        }

        $chapter = TrainingChapter::find($chapterId);
        if (!$chapter) {
            return response()->notfound();
        }
        if ($chapter->blockid != $blockId) {
            return response()->notfound();
        }

        if ($chapter->block->facility === "ZAE" && !RoleHelper::isVATUSAStaff()) {
            return response()->forbidden();
        } elseif (!RoleHelper::isVATUSAStaff() && !RoleHelper::has(\Auth::user()->cid, $chapter->block->facility,
                ['ATM', 'DATM', 'TA'])) {
            return response()->forbidden();
        }

        if (!isTest()) {
            $chapter->deleteChapter();
        }

        return response()->ok();
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @param int                      $id
     *
     * @return array|string
     *
     *
     * @SWG\Post(
     *     path="/cbt/(blockId)",
     *     summary="Create new chapter. [Auth]",
     *     description="Create new chapter. Requires JWT or Session Cookie (required role: ATM, DATM, TA, VATUSA
     *     STAFF)", produces={"application/json"}, tags={"cbt"}, security={"jwt","session"},
     * @SWG\Parameter(name="blockId", in="path", required=true, type="integer", description="Block ID"),
     * @SWG\Parameter(name="facility", in="formData", required=true, type="string", description="Facility IATA
     *                                     ID"),
     * @SWG\Parameter(name="name", in="formData", required=true, type="string", description="Name of block"),
     * @SWG\Parameter(name="url", in="formData", required=true, type="string", description="URL of chapter
     *                                object"),
     * @SWG\Response(
     *         response="401",
     *         description="Unauthorized",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Unauthorized"}},
     *     ),
     * @SWG\Response(
     *         response="403",
     *         description="Forbidden",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Forbidden"}},
     *     ),
     * @SWG\Response(
     *         response="404",
     *         description="Not found",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Not found"}},
     *     ),
     * @SWG\Response(
     *         response="200",
     *         description="OK",
     *         @SWG\Schema(ref="#/definitions/OK"),
     *         examples={"application/json":{"status"="OK"}}
     *     )
     * )
     */
    public function postChapter(Request $request, $id)
    {
        if (!\Auth::check()) {
            return response()->unauthenticated();
        }

        $block = TrainingBlock::find($id);
        if (!$block) {
            return response()->notfound();
        }
        if ($block->facility === "ZAE" && !RoleHelper::isVATUSAStaff()) {
            return response()->forbidden();
        } elseif (!RoleHelper::isVATUSAStaff() && !RoleHelper::has(\Auth::user()->cid, $block->facility,
                ['ATM', 'DATM', 'TA'])) {
            return response()->forbidden();
        }
        $x = 1;
        $prevchap = TrainingChapter::where('blockid', $id)->orderBy("order", "DESC")->limit(1)->first();
        if ($prevchap) {
            $x = $prevchap->order + 1;
        }

        if (!isTest()) {
            $chapter = new TrainingChapter();
            $chapter->blockid = $id;
            $chapter->order = $x;
            $chapter->name = $request->input("name", "New Training Chapter");
            $chapter->url = $request->input("url");
            $chapter->visible = $request->input("visible");
            $chapter->save();
        }

        return response()->ok();
    }

    /**
     *
     *
     * @SWG\Put(
     *     path="/cbt/(blockId)/(chapterId)",
     *     summary="Edit chapter. [Auth]",
     *     description="Edit CBT Chapter. Requires JWT or Session Cookie (required role: ATM, DATM, TA, VATUSA STAFF)",
     *     produces={"application/json"},
     *     tags={"cbt"},
     *     security={"jwt","session"},
     *     @SWG\Parameter(name="blockId", in="path", required=true, type="string", description="Block ID"),
     *     @SWG\Parameter(name="chapterId", in="path", type="integer", description="Chapter ID"),
     *     @SWG\Parameter(name="sortOrder", in="formData", type="integer", description="Order location, sort lowest to
     *                                      highest"),
     *     @SWG\Parameter(name="name", in="formData", type="string", description="Name of block"),
     *     @SWG\Parameter(name="active", in="formData", type="boolean", description="Whether or not it is
     *                                   active/public"),
     *     @SWG\Parameter(name="url", in="formData", type="string", description="Link to object (PDF, YouTube, or other
     *                                embeddable object"),
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
     * @param \Illuminate\Http\Request $request
     * @param                          $bid
     * @param                          $cid
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function putChapter(Request $request, $bid, $cid)
    {
        if (!\Auth::check()) {
            return response()->unauthenticated();
        }

        $chapter = TrainingChapter::find($cid);
        if (!$chapter || $chapter->blockid != $bid) {
            return response()->notfound();
        }
        if ($chapter->block->facility === "ZAE" && !RoleHelper::isVATUSAStaff()) {
            return response()->forbidden();
        } elseif (!RoleHelper::isVATUSAStaff() && !RoleHelper::has(\Auth::user()->cid, $chapter->block->facility,
                ['ATM', 'DATM', 'TA'])) {
            return response()->forbidden();
        }

        if ($request->has("sortOrder")) {
            if (!is_numeric($request->input("sortOrder"))) {
                return response()->malformed();
            }

            $chapter->order = $request->input("sortOrder");
        }
        if ($request->has("visible")) {
            if (!is_numeric($request->input("visible"))) {
                return response()->malformed;
            }
            $chapter->visible = $request->input("visible");
        }

        if ($request->has("url")) {
            if (!filter_var($request->input("url"), FILTER_VALIDATE_URL)) {
                return response()->malformed();
            }

            $chapter->url = $request->input("url");
        }

        if (!isTest()) {
            $chapter->save();
        }

        return response()->ok();
    }
}
