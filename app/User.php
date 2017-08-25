<?php namespace App;

use Illuminate\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;

class User extends Model implements AuthenticatableContract, CanResetPasswordContract
{
    use Authenticatable, CanResetPassword;

    protected $table = 'controllers';
    public $primaryKey = "cid";
    public $incrementing = false;
    public $timestamps = ['created_at'];
    protected $hidden = ['password', 'remember_token'];

    public function getDates()
    {
        return ['created_at','updated_at','lastactivity'];
    }

    public function fullname()
    {
        return $this->fname . " " . $this->lname;
    }

    public function facility()
    {
        return $this->belongsTo('App\Facility', 'facility')->first();
    }
}

