<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * Class EmailAccounts
 * @package App
 *
 * @OA\Schema(
 *     type="object",
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="facility", type="string", description="First name"),
 *     @OA\Property(property="username", type="string", description="Last name"),
 * )
 */
class EmailAccounts extends Model
{
    //

    public function fac() {
        return $this->hasOne("App\Facility", "id", "facility");
    }
}
