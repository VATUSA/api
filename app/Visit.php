<?php
namespace App;

/**
 * Class Visit
 * @package App
 *
 * @OA\Schema(
 *     type="object",
 *     @OA\Property(property="id", @OA\Schema(type="integer")),
 *     @OA\Property(property="cid", @OA\Schema(type="integer")),
 *     @OA\Property(property="facility", @OA\Schema(type="string")),
 *     @OA\Property(property="active", @OA\Schema(type="integer"), description="0 = inactive, 1 = active"),
 *     @OA\Property(property="created_at", @OA\Schema(type="string")),
 * )
 */
class Visit extends Model
{
    protected $table = 'visits';

    public function user()
    {
        return $this->belongsTo('App\User', 'cid', 'cid');
    }
}
