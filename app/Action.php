<?php
namespace App;

/**
 * Class Action
 * @package App
 *
 * @OA\Schema(
 *     type="object",
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="to", type="integer", description="CID log entered for"),
 *     @OA\Property(property="log", type="string"),
 *     @OA\Property(property="created_at", type="string", description="Date rating issued"),
 * )
 */
class Action extends Model {
    protected $table = 'action_log';
    protected $hidden = ['updated_at'];
}

