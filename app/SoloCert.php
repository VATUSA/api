<?php
namespace App;

class SoloCert extends Model {
    protected $table = 'solo_certs';

    public function user() {
        return $this->belongsTo('App\User', 'cid', 'cid');
    }
}
