<?php
namespace App;

/**
 * Class Visit
 * @package App
 *
 * @OA\Schema(
 *     type="object",
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="cid", type="integer"),
 *     @OA\Property(property="facility", type="string"),
 *     @OA\Property(property="active", type="integer", description="0 = inactive, 1 = active"),
 *     @OA\Property(property="created_at", type="string"),
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
