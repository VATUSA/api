<?php
namespace App;

/**
 * Class Role
 * @package App
 *
 * @OA\Schema(
 *     type="object",
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="cid", type="integer"),
 *     @OA\Property(property="facility", type="string"),
 *     @OA\Property(property="role", type="string"),
 *     @OA\Property(property="created_at", type="string"),
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

    public function fac() {
        return $this->hasOne("App\Facility", "id", "facility");
    }
}
