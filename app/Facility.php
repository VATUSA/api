<?php

namespace App;

/**
 * Class Facility
 * @package App
 *
 * @SWG\Definition(
 *     type="object",
 *     @SWG\Property(property="id", type="string", description="Facility IATA ID"),
 *     @SWG\Property(property="name", type="string"),
 *     @SWG\Property(property="url", type="string"),
 *     @SWG\Property(property="region", type="integer", description="Region represented by ATD (ie 7 = VATUSA7)"),
 * )
 */
class Facility extends Model
{
    protected $table = 'facilities';
    public $timestamps = false;
    public $incrementing = false;   // id is IATA of facility
    protected $visible = ["id", "name", "url", "region"];

    public function members()
    {
        return $this->hasMany('App\User','facility', 'id');
    }

    public function returnPaths() {
        return $this->hasMany(ReturnPaths::class);
    }

    public function trainingRecords() {
        return $this->hasMany(TrainingRecord::class);
    }
}
