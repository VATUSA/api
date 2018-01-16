<?php

namespace App\Http\Controllers\API\v2;

use App\Facility;
use App\Helpers\RoleHelper;
use App\KnowledgebaseQuestions;
use App\Role;
use Carbon\Carbon;
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
     * @SWG\Get(
     *     path="/support/kb",
     *     summary="(DONE) Get knowledgebase list",
     *     description="(DONE) Get knowledgebase list",
     *     produces={"application/json"},
     *     tags={"support"},
     *     @SWG\Response(
     *         response="200",
     *         description="OK",
     *         @SWG\Schema(
     *             type="array",
     *             @SWG\Items(
     *                 ref="#/definitions/KnowledgebaseCategories",
     *             ),
     *         ),
     *     )
     * ),
     */
    public function getKBs(Request $request) {
        return response()->ok(KnowledgebaseCategories::orderBy('name')->get()->toArray());
    }

    /**
     * @SWG\Post(
     *     path="/support/kb",
     *     summary="(DONE) Create knowledgebase category. Requires JWT or Session Cookie",
     *     description="(DONE) Creates knowledgebase category. Requires JWT or Session Cookie",
     *     produces={"application/json"},
     *     tags={"support"},
     *     security={"jwt","session"},
     *     @SWG\Parameter(in="formData", name="name", type="string", description="(DONE) Name of new category"),
     *     @SWG\Response(
     *         response="400",
     *         description="Malformed request, check format of position, expDate",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={{"application/json":{"status"="error","message"="Invalid position"}},{"application/json":{"status"="error","message"="Invalid expDate"}}},
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
     *         @SWG\Schema(
     *           ref="#/definitions/OKID",
     *         ),
     *     )
     * )
     */
    public function postKB(Request $request) {
        if (!$request->has("name")) return response()->malformed();
        if (!\Auth::check()) return response()->unauthorized();
        if (!RoleHelper::isVATUSAStaff()) return response()->forbidden();

        $cat = new KnowledgebaseCategories();
        $cat->name = $request->input("name");
        $cat->save();

        return response()->ok(['id' => $cat->id]);
    }

    /**
     * @SWG\Put(
     *     path="/support/kb/{id}",
     *     summary="(DONE) Modify knowledgebase category. Requires JWT or Session Cookie",
     *     description="(DONE) Modify knowledgebase category. Requires JWT or Session Cookie",
     *     produces={"application/json"},
     *     tags={"support"},
     *     security={"jwt","session"},
     *     @SWG\Parameter(in="path", name="id", type="integer", description="(DONE) ID of Knowledgebase Category"),
     *     @SWG\Parameter(in="formData", name="name", type="string", description="(DONE) New name of category"),
     *     @SWG\Response(
     *         response="400",
     *         description="Malformed request, check format of position, expDate",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={{"application/json":{"status"="error","message"="Invalid position"}},{"application/json":{"status"="error","message"="Invalid expDate"}}},
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
     *         response="404",
     *         description="Not found",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Not found"}},
     *     ),
     *     @SWG\Response(
     *         response="200",
     *         description="OK",
     *         @SWG\Schema(
     *           ref="#/definitions/OK",
     *         ),
     *     )
     * )
     */
    public function putKB(Request $request, int $id) {
        if (!$request->has("name")) return response()->malformed();
        if (!\Auth::check()) return response()->unauthorized();
        if (!RoleHelper::isVATUSAStaff()) return response()->forbidden();

        $cat = KnowledgebaseCategories::find($id);
        if (!$cat) return response()->notfound();

        $cat->name = $request->input("name");
        $cat->save();

        return response()->ok();
    }

    /**
     * @SWG\Delete(
     *     path="/support/kb/{id}",
     *     summary="(DONE) Delete knowledgebase category. Requires JWT or Session Cookie",
     *     description="(DONE) Delete knowledgebase category. Requires JWT or Session Cookie",
     *     produces={"application/json"},
     *     tags={"support"},
     *     security={"jwt","session"},
     *     @SWG\Parameter(in="path", name="id", type="integer", description="(DONE) ID of Knowledgebase Category"),
     *     @SWG\Response(
     *         response="400",
     *         description="Malformed request, check format of position, expDate",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={{"application/json":{"status"="error","message"="Invalid position"}},{"application/json":{"status"="error","message"="Invalid expDate"}}},
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
     *         response="404",
     *         description="Not found",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Not found"}},
     *     ),
     *     @SWG\Response(
     *         response="200",
     *         description="OK",
     *         @SWG\Schema(
     *           ref="#/definitions/OK",
     *         ),
     *     )
     * )
     */
    public function deleteKB(Request $request, int $id) {
        if (!$request->has("name")) return response()->malformed();
        if (!\Auth::check()) return response()->unauthorized();
        if (!RoleHelper::isVATUSAStaff()) return response()->forbidden();

        $cat = KnowledgebaseCategories::find($id);

        foreach($cat->questions as $q) {
            $q->delete();
        }

        if (!$cat) return response()->notfound();

        $cat->delete();

        return response()->ok();
    }

    /**
     * @SWG\Post(
     *     path="/support/kb/{categoryId}",
     *     summary="(DONE) Create knowledgebase question. Requires JWT or Session Cookie",
     *     description="(DONE) Creates knowledgebase question. Requires JWT or Session Cookie",
     *     produces={"application/json"},
     *     tags={"support"},
     *     security={"jwt","session"},
     *     @SWG\Parameter(in="path", name="categoryId", type="integer", description="(DONE) ID of category"),
     *     @SWG\Parameter(in="formData", name="question", type="string", description="(DONE) Question"),
     *     @SWG\Parameter(in="formData", name="answer", type="string", description="(DONE) Answer"),
     *     @SWG\Response(
     *         response="400",
     *         description="Malformed request, check format of position, expDate",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={{"application/json":{"status"="error","message"="Invalid position"}},{"application/json":{"status"="error","message"="Invalid expDate"}}},
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
     *         response="404",
     *         description="Not found",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Not found"}},
     *     ),
     *     @SWG\Response(
     *         response="200",
     *         description="OK",
     *         @SWG\Schema(
     *           ref="#/definitions/OKID",
     *         ),
     *     )
     * )
     */
    public function postKBQuestion(Request $request, $id) {
        if (!$request->has("name")) return response()->malformed();
        if (!\Auth::check()) return response()->unauthorized();
        if (!RoleHelper::isVATUSAStaff()) return response()->forbidden();
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
     * @SWG\Put(
     *     path="/support/kb/{categoryid}/{questionid}",
     *     summary="(DONE) Modify knowledgebase question. Requires JWT or Session Cookie",
     *     description="(DONE) Modify knowledgebase question. Requires JWT or Session Cookie",
     *     produces={"application/json"},
     *     tags={"support"},
     *     security={"jwt","session"},
     *     @SWG\Parameter(in="path", name="categoryid", type="integer", description="(DONE) ID of Knowledgebase Category"),
     *     @SWG\Parameter(in="path", name="questionid", type="integer", description="(DONE) ID of question"),
     *     @SWG\Parameter(in="formData", name="question", type="string", description="(DONE) New question"),
     *     @SWG\Parameter(in="formData", name="answer", type="string", description="(DONE) New answer"),
     *     @SWG\Parameter(in="formData", name="category", type="integer", description="(DONE) Move to new category"),
     *     @SWG\Parameter(in="formData", name="order", type="integer", description="(DONE) New order placement"),
     *     @SWG\Response(
     *         response="400",
     *         description="Malformed request, check format of position, expDate",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={{"application/json":{"status"="error","message"="Invalid position"}},{"application/json":{"status"="error","message"="Invalid expDate"}}},
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
     *         response="404",
     *         description="Not found",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Not found"}},
     *     ),
     *     @SWG\Response(
     *         response="200",
     *         description="OK",
     *         @SWG\Schema(
     *           ref="#/definitions/OK",
     *         ),
     *     )
     * )
     */
    public function putKBQuestion(Request $request, int $cid, int $qid) {
        if (!$request->has("name")) return response()->malformed();
        if (!\Auth::check()) return response()->unauthorized();
        if (!RoleHelper::isVATUSAStaff()) return response()->forbidden();
        $cat = KnowledgebaseCategories::find($cid);
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
     * @SWG\Delete(
     *     path="/support/kb/{categoryid}/{questionid}",
     *     summary="(DONE) Delete knowledgebase question. Requires JWT or Session Cookie",
     *     description="(DONE) Delete knowledgebase question. Requires JWT or Session Cookie",
     *     produces={"application/json"},
     *     tags={"support"},
     *     security={"jwt","session"},
     *     @SWG\Parameter(in="path", name="categoryid", type="integer", description="(DONE) ID of Knowledgebase Category"),
     *     @SWG\Parameter(in="path", name="questionid", type="integer", description="(DONE) ID of question"),
     *     @SWG\Response(
     *         response="400",
     *         description="Malformed request, check format of position, expDate",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={{"application/json":{"status"="error","message"="Invalid position"}},{"application/json":{"status"="error","message"="Invalid expDate"}}},
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
     *         @SWG\Schema(
     *           ref="#/definitions/OK",
     *         ),
     *     )
     * )
     */
    public function deleteKBQuestion(Request $request, int $categoryid, int $questionid) {
        if (!$request->has("name")) return response()->malformed();
        if (!\Auth::check()) return response()->unauthorized();
        if (!RoleHelper::isVATUSAStaff()) return response()->forbidden();

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
     * @SWG\Get(
     *     path="/support/tickets/depts",
     *     summary="(DONE) Get list of assignable departments",
     *     description="(DONE) Get list of assignable departments",
     *     produces={"application/json"},
     *     tags={"support"},
     *     @SWG\Response(
     *         response="200",
     *         description="OK",
     *         @SWG\Schema(
     *             type="object",
     *             @SWG\Property(type="string", property="status"),
     *             @SWG\Property(property="depts", type="array",
     *                 @SWG\Items(
     *                     type="object",
     *                     @SWG\Property(property="id", type="string", description="ID of Dept"),
     *                     @SWG\Property(property="name", type="string", description="Name of Dept"),
     *                 ),
     *             ),
     *         ),
     *     )
     * )
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
     * @SWG\Get(
     *     path="/support/tickets/depts/{dept}/staff",
     *     summary="(DONE) Get list of assignable staff members for {dept}",
     *     description="(DONE) Get list of assignable staff members for {dept}",
     *     produces={"application/json"},
     *     tags={"support"},
     *     @SWG\Parameter(name="dept", type="string", description="ID for Dept", in="path"),
     *     @SWG\Response(
     *         response="200",
     *         description="OK",
     *         @SWG\Schema(
     *             type="object",
     *             @SWG\Property(type="string", property="status"),
     *             @SWG\Property(property="staff", type="array",
     *                 @SWG\Items(
     *                     type="object",
     *                     @SWG\Property(property="cid", type="string", description="CID of Staff Member"),
     *                     @SWG\Property(property="name", type="string", description="Name of Dept"),
     *                     @SWG\Property(property="role", type="string", description="Role"),
     *                 ),
     *             ),
     *         ),
     *     )
     * )
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
