<?php
namespace App;

/**
 * Class TrainingProgress
 * @package App
 *
 * @SWG\Definition(
 *     type="object",
 *     @SWG\Property(property="cid", type="integer"),
 *     @SWG\Property(property="chapterid", type="integer"),
 *     @SWG\Property(property="date", type="string", description="Date last completed"),
 * )
 */
class TrainingProgress extends Model
{
    protected $table = 'training_progress';
    public $incrementing = false;
    public $timestamps = false;
    protected $dates = ["date"];
}