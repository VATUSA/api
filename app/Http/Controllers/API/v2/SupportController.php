<?php

namespace App\Http\Controllers\API\v2;

use App\Facility;
use App\Helpers\RoleHelper;
use App\KnowledgebaseQuestions;
use App\Role;
use Illuminate\Http\Request;
use App\KnowledgebaseCategories;

/**
 * Class SupportController
 * @package App\Http\Controllers\API\v2
 */
class SupportController extends APIController
{
    //<editor-fold desc="Knowledgebase">
    /**
     * @OA\Get(
     *     path="/support/kb",
     *     summary="Get knowledgebase list.",
     *     description="Get knowledgebase list.",
     *     tags={"support"},
     *     @OA\Response(
     *         response="200",
     *         description="OK",
     *         @OA\Schema(
     *             type="array",
     *             @OA\Items(
     *                 ref="#/components/schemas/KnowledgebaseCategories",
     *             ),
     *         ),
     *     )
     * ),
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getKBs(Request $request) {
        return response()->ok(KnowledgebaseCategories::orderBy('name')->get()->toArray());
    }

    /**
     * @OA\Post(
     *     path="/support/kb",
     *     summary="Create knowledgebase category. [Auth]",
     *     description="Creates knowledgebase category. Requires JWT or Session Cookie and VATUSA Staff role.",
     *     tags={"support"},
     *     security={"jwt","session"},
     *     @OA\RequestBody(
     *        @OA\MediaType(
     *           mediaType="application/x-www-form-urlencoded",
     *           @OA\Schema(
     *               @OA\Parameter(name="name", @OA\Schema(type="string"), description="Name of new category"),
     *          )
     *       )
     *    ),
     *     @OA\Response(
     *         response="400",
     *         description="Malformed request, check format of position, expDate",
     *         @OA\Schema(ref="#/components/schemas/error"),
     *         
     *     ),
     *     @OA\Response(
     *         response="401",
     *         description="Unauthorized",
     *         @OA\Schema(ref="#/components/schemas/error"),
     *         
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Forbidden",
     *         @OA\Schema(ref="#/components/schemas/error"),
     *         
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="OK",
     *         @OA\Schema(
     *           ref="#/components/schemas/OKID",
     *         ),
     *     )
     * )
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function postKB(Request $request) {
        if (!$request->has("name")) return response()->malformed();
        if (!\Auth::check()) return response()->unauthorized();
        if (!RoleHelper::isVATUSAStaff(\Auth::user())) return response()->forbidden();

        $cat = new KnowledgebaseCategories();
        $cat->name = $request->input("name");
        $cat->save();

        return response()->ok(['id' => $cat->id]);
    }

    /**
     * @OA\Put(
     *     path="/support/kb/{id}",
     *     summary="Modify knowledgebase category. [Auth]",
     *     description="Modify knowledgebase category. Requires JWT or Session Cookie and VATUSA Staff role.",
     *     tags={"support"},
     *     security={"jwt","session"},
     *     @OA\Parameter(in="path", name="id", @OA\Schema(type="integer"), description="ID of Knowledgebase Category"),
     *     @OA\RequestBody(
     *       @OA\MediaType(
     *          mediaType="application/x-www-form-urlencoded",
     *          @OA\Schema(
     *             @OA\Parameter(name="name", @OA\Schema(type="string"), description="New name of category"),
     *         )
     *       )
     *     ),
     *     @OA\Response(
     *         response="400",
     *         description="Malformed request, check format of position, expDate",
     *         @OA\Schema(ref="#/components/schemas/error"),
     *         
     *     ),
     *     @OA\Response(
     *         response="401",
     *         description="Unauthorized",
     *         @OA\Schema(ref="#/components/schemas/error"),
     *         
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Forbidden",
     *         @OA\Schema(ref="#/components/schemas/error"),
     *         
     *     ),
     *     @OA\Response(
     *         response="404",
     *         description="Not found",
     *         @OA\Schema(ref="#/components/schemas/error"),
     *         
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="OK",
     *         @OA\Schema(
     *           ref="#/components/schemas/OK",
     *         ),
     *     )
     * )
     */
    public function putKB(Request $request, int $id) {
        if (!$request->has("name")) return response()->malformed();
        if (!\Auth::check()) return response()->unauthorized();
        if (!RoleHelper::isVATUSAStaff(\Auth::user())) return response()->forbidden();

        $cat = KnowledgebaseCategories::find($id);
        if (!$cat) return response()->notfound();

        $cat->name = $request->input("name");
        $cat->save();

        return response()->ok();
    }

    /**
     * @OA\Delete(
     *     path="/support/kb/{id}",
     *     summary="Delete knowledgebase category. [Auth]",
     *     description="Delete knowledgebase category. Requires JWT or Session Cookie and VATUSA Staff role.",
     *     tags={"support"},
     *     security={"jwt","session"},
     *     @OA\Parameter(in="path", name="id", @OA\Schema(type="integer"), description="ID of Knowledgebase Category"),
     *     @OA\Response(
     *         response="400",
     *         description="Malformed request, check format of position, expDate",
     *         @OA\Schema(ref="#/components/schemas/error"),
     *         
     *     ),
     *     @OA\Response(
     *         response="401",
     *         description="Unauthorized",
     *         @OA\Schema(ref="#/components/schemas/error"),
     *         
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Forbidden",
     *         @OA\Schema(ref="#/components/schemas/error"),
     *         
     *     ),
     *     @OA\Response(
     *         response="404",
     *         description="Not found",
     *         @OA\Schema(ref="#/components/schemas/error"),
     *         
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="OK",
     *         @OA\Schema(
     *           ref="#/components/schemas/OK",
     *         ),
     *     )
     * )
     * @param \Illuminate\Http\Request $request
     * @param int                      $id
     *
     * @return \Illuminate\Http\JsonResponse
     * @throws \Exception
     */
    public function deleteKB(Request $request, int $id) {
        if (!$request->has("name")) return response()->malformed();
        if (!\Auth::check()) return response()->unauthorized();
        if (!RoleHelper::isVATUSAStaff(\Auth::user())) return response()->forbidden();

        $cat = KnowledgebaseCategories::find($id);

        foreach($cat->questions as $q) {
            $q->delete();
        }

        if (!$cat) return response()->notfound();

        $cat->delete();

        return response()->ok();
    }

    /**
     * @OA\Post(
     *     path="/support/kb/{categoryId}",
     *     summary="Create knowledgebase question. [Auth]",
     *     description="Creates knowledgebase question. Requires JWT or Session Cookie and VATUSA Staff role.",
     *     tags={"support"},
     *     security={"jwt","session"},
     *     @OA\Parameter(in="path", name="categoryId", @OA\Schema(type="integer"), description="ID of category"),
     *     @OA\RequestBody(
     *       @OA\MediaType(
     *         mediaType="application/x-www-form-urlencoded",
     *        @OA\Schema(
     *     @OA\Parameter(name="question", @OA\Schema(type="string"), description="Question"),
     *     @OA\Parameter(name="answer", @OA\Schema(type="string"), description="Answer"),
     *        )
     *      )
     *     ),
     *     @OA\Response(
     *         response="400",
     *         description="Malformed request, check format of position, expDate",
     *         @OA\Schema(ref="#/components/schemas/error"),
     *         
     *     ),
     *     @OA\Response(
     *         response="401",
     *         description="Unauthorized",
     *         @OA\Schema(ref="#/components/schemas/error"),
     *         
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Forbidden",
     *         @OA\Schema(ref="#/components/schemas/error"),
     *         
     *     ),
     *     @OA\Response(
     *         response="404",
     *         description="Not found",
     *         @OA\Schema(ref="#/components/schemas/error"),
     *         
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="OK",
     *         @OA\Schema(
     *           ref="#/components/schemas/OKID",
     *         ),
     *     )
     * )
     * @param \Illuminate\Http\Request $request
     * @param                          $id
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function postKBQuestion(Request $request, $id) {
        if (!$request->has("name")) return response()->malformed();
        if (!\Auth::check()) return response()->unauthorized();
        if (!RoleHelper::isVATUSAStaff(\Auth::user())) return response()->forbidden();
        $cat = KnowledgebaseCategories::find($id);
        if (!$cat) return response()->notfound();

        $lastQ = KnowledgebaseQuestions::where('category_id', $cat->id)->orderBy('order', 'DESC')->first();

        $q = new KnowledgebaseQuestions();
        $q->category_id = $cat->id;
        $q->question = $request->input("question");
        $q->answer = $request->input("answer");
        $q->order = (!$lastQ) ? 1 : $lastQ + 1;
        $q->updated_by = \Auth::user()->cid;
        $q->save();

        return response()->ok(['id' => $q->id]);
    }

    /**
     * @OA\Put(
     *     path="/support/kb/{categoryid}/{questionid}",
     *     summary="Modify knowledgebase question. [Auth]",
     *     description="Modify knowledgebase question. Requires JWT or Session Cookie and VATUSA Staff Role",
     *     tags={"support"},
     *     security={"jwt","session"},
     *     @OA\Parameter(in="path", name="categoryid", @OA\Schema(type="integer"), description="ID of Knowledgebase
     * Category"),
     *     @OA\Parameter(in="path", name="questionid", @OA\Schema(type="integer"), description="ID of question"),
     *    @OA\RequestBody(
     *      @OA\MediaType(
     *       mediaType="application/x-www-form-urlencoded",
     *      @OA\Schema(
     *     @OA\Parameter(name="question", @OA\Schema(type="string"), description="New question"),
     *     @OA\Parameter(name="answer", @OA\Schema(type="string"), description="New answer"),
     *     @OA\Parameter(name="category", @OA\Schema(type="integer"), description="Move to new
     * category"),
     *     @OA\Parameter(name="order", @OA\Schema(type="integer"), description="New order placement"),
     *     ))),
     *     @OA\Response(
     *         response="400",
     *         description="Malformed request, check format of position, expDate",
     *         @OA\Schema(ref="#/components/schemas/error"),
     *         
     *     ),
     *     @OA\Response(
     *         response="401",
     *         description="Unauthorized",
     *         @OA\Schema(ref="#/components/schemas/error"),
     *         
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Forbidden",
     *         @OA\Schema(ref="#/components/schemas/error"),
     *         
     *     ),
     *     @OA\Response(
     *         response="404",
     *         description="Not found",
     *         @OA\Schema(ref="#/components/schemas/error"),
     *         
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="OK",
     *         @OA\Schema(
     *           ref="#/components/schemas/OK",
     *         ),
     *     )
     * )
     * @param \Illuminate\Http\Request $request
     * @param int                      $cid
     * @param int                      $qid
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function putKBQuestion(Request $request, int $cid, int $qid) {
        if (!$request->has("name")) return response()->malformed();
        if (!\Auth::check()) return response()->unauthorized();
        if (!RoleHelper::isVATUSAStaff(\Auth::user())) return response()->forbidden();
        if (!$cat) return response()->notfound();
        $q = KnowledgebaseQuestions::find($qid);
        if (!$q || $q->category_id != $cat->id) return response()->notFound();

        if ($request->has("question")) {
            $q->question = $request->input("question");
        }

        if ($request->has("answer")) {
            $q->answer = $request->input("answer");
        }

        if ($request->has("category")) {
            $nc = KnowledgebaseCategories::find($request->input("category"));
            if (!$nc) return response()->notfound();
            $q->cat_id = $nc->id;
        }

        if ($request->has("order")) {
            $q->order = $request->input("order");
        }

        $q->updated_by = \Auth::user()->cid;
        $q->save();

        if (isset($nc)) {
            $nc->reorder();
        }
        $cat->reorder();

        return response()->ok();
    }

    /**
     * @OA\Delete(
     *     path="/support/kb/{categoryid}/{questionid}",
     *     summary="Delete knowledgebase question. [Auth]",
     *     description="Delete knowledgebase question. Requires JWT or Session Cookie and VATUSA Staff role.",
     *     tags={"support"},
     *     security={"jwt","session"},
     *     @OA\Parameter(in="path", name="categoryid", @OA\Schema(type="integer"), description="ID of Knowledgebase
     * Category"),
     *     @OA\Parameter(in="path", name="questionid", @OA\Schema(type="integer"), description="ID of question"),
     *     @OA\Response(
     *         response="400",
     *         description="Malformed request, check format of position, expDate",
     *         @OA\Schema(ref="#/components/schemas/error"),
     *     ),
     *     @OA\Response(
     *         response="401",
     *         description="Unauthorized",
     *         @OA\Schema(ref="#/components/schemas/error"),
     *         
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Forbidden",
     *         @OA\Schema(ref="#/components/schemas/error"),
     *         
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="OK",
     *         @OA\Schema(
     *           ref="#/components/schemas/OK",
     *         ),
     *     )
     * )
     * @param \Illuminate\Http\Request $request
     * @param int                      $categoryid
     * @param int                      $questionid
     *
     * @return \Illuminate\Http\JsonResponse
     * @throws \Exception
     */
    public function deleteKBQuestion(Request $request, int $categoryid, int $questionid) {
        if (!$request->has("name")) return response()->malformed();
        if (!\Auth::check()) return response()->unauthorized();
        if (!RoleHelper::isVATUSAStaff(\Auth::user())) return response()->forbidden();

        $cat = KnowledgebaseCategories::find($categoryid);
        if (!$cat) return response()->notfound();
        $q = KnowledgebaseQuestions::find($questionid);
        if (!$q || $q->category_id != $cat->id) return response()->notfound();

        $q->delete();

        return response()->ok();
    }
    //</editor-fold>

    // <editor-fold description="Dept Handling">
    /**
     * @OA\Get(
     *     path="/support/tickets/depts",
     *     summary="Get list of assignable departments.",
     *     description="Get list of assignable departments.",
     *     tags={"support"},
     *     @OA\Response(
     *         response="200",
     *         description="OK",
     *         @OA\Schema(
     *             type="object",
     *             @OA\Property(type="string", property="status"),
     *             @OA\Property(property="depts", type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", @OA\Schema(type="string"), description="ID of Dept"),
     *                     @OA\Property(property="name", @OA\Schema(type="string"), description="Name of Dept"),
     *                 ),
     *             ),
     *         ),
     *     )
     * )
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getTicketDepts(Request $request) {
        $depts = [
            ["id" => "ZHQ", "name" => "VATUSA Headquarters"]
        ];

        $f = Facility::where('active', 1)->orderBy('name')->get();
        foreach ($f as $fac) {
            $depts[] = [
                "id" => $fac->id,
                "name" => $fac->name
            ];
        }

        return response()->ok(["depts" => $depts]);
    }

    /**
     * @OA\Get(
     *     path="/support/tickets/depts/{dept}/staff",
     *     summary="Get list of assignable staff members for department.",
     *     description="Get list of assignable staff members for {dept}.",
     *     tags={"support"},
     *     @OA\Parameter(name="dept", @OA\Schema(type="string"), description="ID for Dept", in="path"),
     *     @OA\Response(
     *         response="200",
     *         description="OK",
     *         @OA\Schema(
     *             type="object",
     *             @OA\Property(type="string", property="status"),
     *             @OA\Property(property="staff", type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="cid", @OA\Schema(type="string"), description="CID of Staff Member"),
     *                     @OA\Property(property="name", @OA\Schema(type="string"), description="Name of Dept"),
     *                     @OA\Property(property="role", @OA\Schema(type="string"), description="Role"),
     *                 ),
     *             ),
     *         ),
     *     )
     * )
     * @param \Illuminate\Http\Request $request
     * @param string                   $dept
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getTicketDeptStaff(Request $request, string $dept) {
        $fac = Facility::find($dept);
        if (!$fac) return response()->notfound();

        $staff = []; $chked = []; $i = 0;
        foreach (
            Role::where('facility', $fac->id)
            ->orderBy(\DB::raw('field(role, "ATM","DATM","TA","EC","FE","WM","INS","MTR")'))
            ->orderBy('role')->get()

            as $role) {

            if (!isset($chked[$role->cid])) {
                $staff[$i] = [
                    'cid' => $role->cid,
                    'role' => $role->role,
                    'name' => $role->user->fullname()
                ];
                $chked[$role->cid] = $i;
                $i += 1;
            } else {
                $id = $chked[$role->cid];
                $staff[$id]['role'] = $staff[$id]['role'] . "," . $role->role;
            }
        }

        return response()->ok(["staff" => $staff]);
    }
    // </editor-fold>
}
