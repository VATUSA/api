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

    protected $hidden = [
        'apikey',
        'uls_jwk',
        'uls_secret',
        'api_sandbox_key',
        'apiv2_jwk',
        'apiv2_jwk_dev',
        'uls_jwk_dev',
        'welcome_text'
    ];

    public function members()
    {
        return $this->hasMany('App\User','facility', 'id')->orderBy('lname', 'ASC');
    }

    public function visitors()
    {
        return User::whereHas('visits', function($q) {
            $q->where('facility', $this->id);
        })->get();
    }

    public function atm()
    {
        return $this->hasOne('App\User', 'cid', 'atm')->first();
    }

    public function datm()
    {
        return $this->hasOne('App\User', 'cid', 'datm')->first();
    }

    public function ta()
    {
        return $this->hasOne('App\User', 'cid', 'ta')->first();
    }

    public function ec()
    {
        return $this->hasOne('App\User', 'cid', 'ec')->first();
    }

    public function fe()
    {
        return $this->hasOne('App\User', 'cid', 'fe')->first();
    }

    public function wm()
    {
        return $this->hasOne('App\User', 'cid', 'wm')->first();
    }

    public function trainingRecords() {
        return $this->hasMany(TrainingRecord::class);
    }
}
