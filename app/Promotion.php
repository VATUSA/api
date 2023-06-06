<?php namespace App;
use App\Helpers\Helper;
use App\Helpers\RatingHelper;

/**
 * Class Promotion
 * @package App
 *
 * @OA\Schema(
 *     type="object",
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="cid", type="integer"),
 *     @OA\Property(property="grantor", type="integer", description="CID of grantor, 11111 = system generated or conducted outside of VATUSA"),
 *     @OA\Property(property="to", type="integer", description="Rating based off array where 1=OBS, S1, S2, S3, C1, C2, C3, I1, I2, I3, SUP, ADM"),
 *     @OA\Property(property="from", type="integer", description="Rating based off array where 1=OBS, S1, S2, S3, C1, C2, C3, I1, I2, I3, SUP, ADM"),
 *     @OA\Property(property="created_at", type="string", description="Date rating issued"),
 *     @OA\Property(property="exam", type="string", description="Date of exam"),
 *     @OA\Property(property="examiner", type="integer", description="CERT ID of examiner"),
 *     @OA\Property(property="position", type="string", description="Position worked"),
 * )
 */
class Promotion extends Model {
    protected $table = 'promotions';
    protected $hidden = ['updated_at'];

    public function User() {
        $this->belongsTo('\App\User', 'cid', 'cid');
    }

    public static function process($cid, $grantor, $to, $from = null, $exam = "0000-00-00 00:00:00", $examiner = null, $position = "n/a", $evalId = null) {
        $p = new Promotion();
        $p->cid = $cid;
        $p->grantor = $grantor;
        $p->from = !$from ? User::find($cid)->rating : $from;
        $p->to = $to;
        $p->exam = $exam;
        $p->examiner = $examiner ? $examiner : $grantor;
        $p->position = $position;
        $p->eval_id = $evalId;
        $p->save();


        log_action($cid, "Rating Change: " . RatingHelper::intToShort($p->from) . " to " .
            RatingHelper::intToShort($to) . " issued by " . Helper::nameFromCID($grantor));

        return $p;
    }

    public function evaluation() {
        return $this->belongsTo(OTSEval::class, 'eval_id');
    }
}

