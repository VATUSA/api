<?php
namespace App;

/**
 * Class Role
 * @package App
 *
 * @SWG\Definition(
 *     type="object",
 *     @SWG\Property(property="id", type="integer"),
 *     @SWG\Property(property="cid", type="integer"),
 *     @SWG\Property(property="facility", type="string"),
 *     @SWG\Property(property="role", type="string"),
 *     @SWG\Property(property="created_at", type="string"),
 * )
 */
class Role extends Model
{
    protected $table = 'roles';
    public $timestamps = ['created_at'];

    public function user() {
        return $this->belongsTo('App\User', 'cid', 'cid');
    }

    public function setUpdatedAtAttribute($value) {
        // to Disable updated_at
    }
}