<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class BackfillNullConsolidationHours extends Migration
{
    /**
     * Rows created by controller:eligibility were left with a NULL
     * has_consolidation_hours, which the hours check never selected.
     *
     * @return void
     */
    public function up()
    {
        DB::table('controller_eligibility_cache')
            ->whereNull('has_consolidation_hours')
            ->update(['has_consolidation_hours' => false]);
    }

    /**
     * @return void
     */
    public function down()
    {
        //
    }
}
