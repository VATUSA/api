<?php
namespace App;

/**
 * Class Action
 * @package App
 *
 * @SWG\Definition(
 *     type="object",
 *     @SWG\Property(property="id", type="integer"),
 *     @SWG\Property(property="to", type="integer", description="CID log entered for"),
 *     @SWG\Property(property="log", type="string"),
 *     @SWG\Property(property="created_at", type="string", description="Date rating issued"),
 * )
 */
class Action extends Model {
    protected $table = 'action';
    protected $hidden = ['updated_at'];
}

