<?php
namespace App;

/**
 * Class Role
 * @package App
 *
 * @OA\Schema(
 *     type="object",
 *     @OA\Property(property="id", @OA\Schema(type="integer")),
 *     @OA\Property(property="cid", @OA\Schema(type="integer")),
 *     @OA\Property(property="facility", @OA\Schema(type="string")),
 *     @OA\Property(property="role", @OA\Schema(type="string")),
 *     @OA\Property(property="created_at", @OA\Schema(type="string")),
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
