<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class EmailAccounts extends Model
{
    //

    public function fac() {
        return $this->hasOne("App\Facility", "id", "facility");
    }
}
