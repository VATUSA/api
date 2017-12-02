<?php

namespace App\Http\Controllers\API\v2;

/**
 * Class CBTController
 * @package App\Http\Controllers\API\v2
 */
class CBTController extends APIController
{
    /**
     * @param string $facility
     * @param string $role
     * @return array|string
     *
     * @TODO
     *
     * @SWG\Get(
     *     path="/cbt/blocks",
     *     summary="Get CBT Blocks filtered by facility (or all)",
     *     description="Get CBT Blocks filtered by facility (or all)",
     *     produces={"application/json"},
     *     tags={"cbt"},
     *     @SWG\Parameter(name="facility", in="query", type="string", description="Filter by facility id"),
     *     @SWG\Response(
     *         response="200",
     *         description="OK",
     *         @SWG\Schema(
     *             type="array",
     *             @SWG\Items(
     *                 type="object",
     *                 @SWG\Property(property="id", type="integer", description="Block ID"),
     *                 @SWG\Property(property="facility", type="string", description="Facility IATA ID"),
     *                 @SWG\Property(property="sortOrder", type="integer", description="Order location, sort lowest to highest"),
     *                 @SWG\Property(property="name", type="string", description="Name of block"),
     *                 @SWG\Property(property="active", type="boolean", description="Whether or not it is active/public"),
     *                 @SWG\Property(property="access", type="string", description="Access level (plain text options: All, Student, C1, I1, Staff, Senior Staff)"),
     *             ),
     *         ),
     *     )
     * ),
     */
    public function getBlocks($facility, $role) {

    }

    /**
     * @return array|string
     *
     * @TODO
     *
     * @SWG\Delete(
     *     path="/cbt/blocks/(id)",
     *     summary="Delete block. Requires JWT, API Key, or Session Cookie",
     *     description="Delete block. Requires JWT, API Key, or Session Cookie (required role: (N/A for API Key) ATM, DATM, TA, VATUSA STAFF)",
     *     produces={"application/json"},
     *     tags={"cbt"},
     *     security={"jwt","session","apikey"},
     *     @SWG\Parameter(name="id", in="path", required=true, type="integer", description="Block ID"),
     *     @SWG\Response(
     *         response="401",
     *         description="Unauthenticated",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Unauthenticated"}},
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

    }
    /**
     * @param int $cid
     * @param string $facility
     * @param string $role
     * @return array|string
     *
     * @TODO
     *
     * @SWG\Put(
     *     path="/cbt/blocks",
     *     summary="Create new block. Requires JWT, API Key, or Session Cookie",
     *     description="Create new block. Requires JWT, API Key or Session Cookie (required role: (N/A for API Key) ATM, DATM, TA, VATUSA STAFF)",
     *     produces={"application/json"},
     *     tags={"cbt"},
     *     security={"jwt","session","apikey"},
     *     @SWG\Parameter(name="facility", in="formData", required=true, type="string", description="Facility IATA ID"),
     *     @SWG\Parameter(name="name", in="formData", required=true, type="string", description="Name of block"),
     *     @SWG\Response(
     *         response="401",
     *         description="Unauthenticated",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Unauthenticated"}},
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
    public function putBlock($cid, $facility, $role) {

    }

    /**
     *
     * @TODO
     *
     * @SWG\Post(
     *     path="/cbt/blocks/(id)",
     *     summary="Edit CBT Block. Requires JWT, API Key, or Session Cookie",
     *     description="Edit CBT Block. Requires JWT, API Key, or Session Cookie (required role: (N/A for API Key) ATM, DATM, TA, VATUSA STAFF)",
     *     produces={"application/json"},
     *     tags={"cbt"},
     *     security={"jwt","session","apikey"},
     *     @SWG\Parameter(name="id", in="path", type="integer", description="Block ID"),
     *     @SWG\Parameter(name="sortOrder", in="formData", type="integer", description="Order location, sort lowest to highest"),
     *     @SWG\Parameter(name="name", in="formData", type="string", description="Name of block"),
     *     @SWG\Parameter(name="active", in="formData", type="boolean", description="Whether or not it is active/public"),
     *     @SWG\Parameter(name="access", in="formData", type="string", description="Access level (plain text options: All, Student, C1, I1, Staff, Senior Staff)"),
     *     @SWG\Response(
     *         response="401",
     *         description="Unauthenticated",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Unauthenticated"}},
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
    public function postBlock($cid) {

    }

    /**
     * @param string $facility
     * @param string $role
     * @return array|string
     *
     * @TODO
     *
     * @SWG\Get(
     *     path="/cbt/blocks/(blockId)/chapters",
     *     summary="Get Chapters for blockId",
     *     description="Get Chapters for blockId",
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
    public function getChapter() {

    }

    /**
     * @return array|string
     *
     * @TODO
     *
     * @SWG\Delete(
     *     path="/cbt/blocks/(blockId)/chapter/(chapterId)",
     *     summary="Delete chapter. Requires JWT or Session Cookie",
     *     description="Delete chapter. Requires JWT or Session Cookie (required role: ATM, DATM, TA, VATUSA STAFF)",
     *     produces={"application/json"},
     *     tags={"cbt"},
     *     security={"jwt","session","apikey"},
     *     @SWG\Parameter(name="blockId", in="path", required=true, type="integer", description="Block ID"),
     *     @SWG\Parameter(name="chapterId", in="path", required=true, type="integer", description="Chapter ID"),
     *     @SWG\Response(
     *         response="401",
     *         description="Unauthenticated",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Unauthenticated"}},
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

    }
    /**
     * @param int $cid
     * @param string $facility
     * @param string $role
     * @return array|string
     *
     * @TODO
     *
     * @SWG\Put(
     *     path="/cbt/blocks/(blockId)/chapters",
     *     summary="Create new chapter. Requires JWT, API Key, or Session Cookie",
     *     description="Create new chapter. Requires JWT, API Key, or Session Cookie (required role: (N/A for apikey) ATM, DATM, TA, VATUSA STAFF)",
     *     produces={"application/json"},
     *     tags={"cbt"},
     *     security={"jwt","session","apikey"},
     *     @SWG\Parameter(name="blockId", in="path", required=true, type="integer", description="Block ID"),
     *     @SWG\Parameter(name="facility", in="formData", required=true, type="string", description="Facility IATA ID"),
     *     @SWG\Parameter(name="name", in="formData", required=true, type="string", description="Name of block"),
     *     @SWG\Response(
     *         response="401",
     *         description="Unauthenticated",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Unauthenticated"}},
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
    public function putChapter($cid, $facility, $role) {

    }

    /**
     *
     * @TODO
     *
     * @SWG\Post(
     *     path="/cbt/blocks/(blockId)/chapters/(chapterId)",
     *     summary="Assign new role. Requires JWT or Session Cookie",
     *     description="Assign new role. Requires JWT or Session Cookie (required role: ATM, DATM, TA, VATUSA STAFF)",
     *     produces={"application/json"},
     *     tags={"cbt"},
     *     security={"jwt","session","apikey"},
     *     @SWG\Parameter(name="blockId", in="path", required=true, type="string", description="Block ID"),
     *     @SWG\Parameter(name="chapterId", in="path", type="integer", description="Chapter ID"),
     *     @SWG\Parameter(name="sortOrder", in="formData", type="integer", description="Order location, sort lowest to highest"),
     *     @SWG\Parameter(name="name", in="formData", type="string", description="Name of block"),
     *     @SWG\Parameter(name="active", in="formData", type="boolean", description="Whether or not it is active/public"),
     *     @SWG\Parameter(name="link", in="formData", type="string", description="Link to object (PDF, YouTube, or other embeddable object"),
     *     @SWG\Response(
     *         response="401",
     *         description="Unauthenticated",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Unauthenticated"}},
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
    public function postChapter($cid) {

    }
}
