<?php namespace App;

class Promotion extends Model {
    protected $table = 'promotions';

    public function User() {
        $this->belongsTo('\App\User', 'cid', 'cid');
    }
}

