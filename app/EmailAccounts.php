<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * Class EmailAccounts
 * @package App
 *
 * @SWG\Definition(
 *     type="object",
 *     @SWG\Property(property="id", type="integer"),
 *     @SWG\Property(property="facility", type="string", description="First name"),
 *     @SWG\Property(property="username", type="string", description="Last name"),
 * )
 */
class EmailAccounts extends Model
{
    //

    public function fac() {
        return $this->hasOne("App\Facility", "id", "facility");
    }
}
