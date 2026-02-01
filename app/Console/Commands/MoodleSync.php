<?php

namespace App\Console\Commands;

use App\Helpers\Helper;
use App\Helpers\RoleHelper;
use App\Classes\VATUSAMoodle;
use App\Facility;
use App\Role;
use App\User;
use Illuminate\Console\Command;

class MoodleSync extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'moodle:sync 
                            {user? : CID of a single user to sync}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync Moodle Roles and Cohorts';

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

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        if ($this->argument('user')) {
            $user = User::find($this->argument('user'));
            if (!$user) {
                $this->error("Invalid CID");

                return 0;
            }

            $this->sync($user);

            return 0;
        }

        //Syncronize Users
        User::with('visits', 'academyCompetencies.course', 'roles')->chunk(1000, function ($users) {
            foreach ($users as $user) {
                if ($this->moodle->getUserId($user->cid)) {
                    $this->sync($user);
                }
            }
        });

        return 0;
    }

    /**
     * Synchronize Roles
     *
     * @param \App\User $user
     *
     * @throws \Exception
     */
    private function sync(User $user)
    {
        //Update or Create
        if ($id = $this->moodle->getUserId($user->cid)) {
            //Update Information
            $this->moodle->updateUser($user, $id);
        } else {
            //Create User
            $id = $this->moodle->createUser($user)[0]["id"];
        }
        $facilities = $user->visits->pluck('facility')->merge(collect($user->facility))->unique();

        //Assign Cohorts
        $this->moodle->clearUserCohorts($id);
        $this->moodle->assignCohort($id,
            Helper::ratingShortFromInt($user->rating)); //VATUSA level rating
        if ($user->flag_homecontroller) {
            $this->moodle->assignCohort($id,
                "$user->facility-" . Helper::ratingShortFromInt($user->rating)); //Facility level rating
            if (RoleHelper::userIsVATUSAStaff($user, false, true)
                || RoleHelper::userIsInstructor($user)
                || RoleHelper::userIsSeniorStaff($user, null, true)
                || RoleHelper::userIsMentor($user)) {
                $this->moodle->assignCohort($id, "TNG"); //Training staff
            }
        }
        $this->moodle->assignCohort($id, $user->facility); //Home Facility

        foreach ($user->visits->pluck('facility') as $facility) {
            //Visiting Facilities
            $this->moodle->assignCohort($id, $facility . "-V"); //Facility level visitor
            $this->moodle->assignCohort($id,
                "$facility-" . Helper::ratingShortFromInt($user->rating)); //Facility level rating
        }
        //Clear Roles
        $this->moodle->clearUserRoles($id);

        //Assign Student Role
        foreach ($facilities as $facility) {
            $this->moodle->assignRole($id, $this->moodle->getCategoryFromShort($facility, true), "STU", "coursecat");
        }

        //Assign Category Permissions
        if (RoleHelper::userIsVATUSAStaff($user, false, true) || RoleHelper::userIsSeniorStaff($user, $user->facility,
                true)) {
            $this->moodle->assignRole($id, VATUSAMoodle::CATEGORY_CONTEXT_VATUSA, "INS", "coursecat");
            $this->moodle->assignRole($id, $this->moodle->getCategoryFromShort($user->facility, true), "TA",
                "coursecat");
            $artccCategories = $this->moodle->getAllSubcategories($this->moodle->getCategoryFromShort($user->facility),
                true);
            foreach ($artccCategories as $category) {
                $courses = $this->moodle->getCoursesInCategory($category);
                foreach ($courses as $course) {
                    $this->moodle->enrolUser($id, $course["id"]);
                }
            }
        }
        if (RoleHelper::userIsVATUSAStaff($user, false, true) || RoleHelper::userHas($user, "ZAE", "CBT")) {
            $this->moodle->assignRole($id, VATUSAMoodle::CATEGORY_CONTEXT_VATUSA, "CBT", "coursecat");
        }
        if (RoleHelper::userIsVATUSAStaff($user, false, true) || RoleHelper::userHas($user, $user->facility,
                "FACCBT")) {
            $this->moodle->assignRole($id, $this->moodle->getCategoryFromShort($user->facility, true), "FACCBT",
                "coursecat");
        }
        if (RoleHelper::userIsVATUSAStaff($user, false, true) || RoleHelper::userIsInstructor($user,
                $user->facility)) {
            $this->moodle->assignRole($id, VATUSAMoodle::CATEGORY_CONTEXT_VATUSA, "INS", "coursecat");
            $this->moodle->assignRole($id, $this->moodle->getCategoryFromShort($user->facility, true), "INS",
                "coursecat");
        }
        if (RoleHelper::userIsVATUSAStaff($user, false, true) || RoleHelper::userIsMentor($user)) {
            for ($i = Helper::ratingIntFromShort("S1"); $i <= $user->rating; $i++) {
                $context = "EXAM_CONTEXT_" . Helper::ratingShortFromInt($i);
                $this->moodle->assignRole($id, $this->moodle->getConstant($context), "MTR", "course");
            }
        }

        /*if (Role::where("cid", $user->cid)->where("facility", $user->facility)->where("role", "MTR")->exists()) {
            $this->moodle->assignRole($id, $this->moodle->getCategoryFromShort($user->facility, true), "MTR",
                "coursecat");
        }*/

    }
}
