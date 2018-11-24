<?php
namespace App;

class SoloCert extends Model {
    protected $table = 'solo_certs';

    protected $fillable = ['cid', 'position', 'expires'];

    public function user() {
        return $this->belongsTo('App\User', 'cid', 'cid');
    }
}
