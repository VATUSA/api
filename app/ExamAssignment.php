<?php
namespace App;


class ExamAssignment extends Model
{
    public $timestamps = false;
    protected $table = "exam_assignments";
    protected $dates = ['assigned_date', 'expire_date'];

    public static function hasAssignment($cid, $exam, $incl_reassign = true)
    {
        $as = $ras = 0;

        $as = DB::table("exam_assignments")->where("exam_id", $exam)->where("cid", $cid)->count();
        if ($incl_reassign)
            $ras = DB::table("exam_reassignments")->where("exam_id", $exam)->where("cid", $cid)->count();

        return (($as >= 1) || ($ras >= 1));
    }

    public function exam()
    {
        return $this->belongsTo('App\Exam','exam_id','id');
    }
}
