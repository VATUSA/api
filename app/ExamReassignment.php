<?php
namespace App;

class ExamReassignment
    extends Model
{
    public $timestamps = false;
    protected $table = "exam_reassignments";
    protected $dates = ['reassign_date'];
}
