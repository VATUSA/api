<?php

namespace App\Console\Commands;

use App\Classes\VATUSAMoodle;
use App\Facility;
use Illuminate\Console\Command;

class MakeCohorts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'moodle:makecohorts';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Make ARTCC Cohorts';

    /** @var \App\Classes\VATUSAMoodle instance */
    private $moodle;

    /**
     * Create a new command instance.
     *
     * @param \App\Classes\VATUSAMoodle $moodle
     */
    public function __construct(VATUSAMoodle $moodle)
    {
        parent::__construct();
        $this->moodle = $moodle;
    }

    private $ratings = [
        'OBS' => 'Observers',
        'S1'  => 'Students',
        'S2'  => 'Student 2s',
        'S3'  => 'Senior Students',
        'C1'  => 'Controllers',
        'C3'  => 'Senior Controllers',
        'I1'  => 'Instructors',
        'I3'  => 'Senior Instructors',
        'SUP' => 'Supervisors',
        'ADM' => 'Administrators (VATSIM)'
    ];

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        //For each ARTCC Category context, make a cohort with each rating (ex. ZSE-S1)
        //IDs = 51-71 Alphabetically by name
        $i = 51;
        foreach (Facility::where('active', 1)->orderBy('name')->get() as $facility) {
            foreach ($this->ratings as $rating => $name) {
                $this->moodle->createCohort("$facility->id-$rating", $name, "id", $i);
            }
            $i++;
        }
    }
}
