<?php
namespace App;

/**
 * Class Action
 * @package App
 *
 * @OA\Schema(
 *     type="object",
 *     @OA\Property(property="id", @OA\Schema(type="integer")),
 *     @OA\Property(property="to", @OA\Schema(type="integer"), description="CID log entered for"),
 *     @OA\Property(property="log", @OA\Schema(type="string")),
 *     @OA\Property(property="created_at", @OA\Schema(type="string"), description="Date rating issued"),
 * )
 */
class Action extends Model {
    protected $table = 'action_log';
    protected $hidden = ['updated_at'];
}

