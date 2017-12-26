<?php
namespace App;



class Role extends Model {
    protected $table = 'roles';
    public $timestamps = false;

    public function user() {
        return $this->belongsTo('App\User', 'cid', 'cid');
    }
}
