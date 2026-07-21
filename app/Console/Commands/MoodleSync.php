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
            $user = User::with('visits', 'academyCompetencies.course', 'roles')->find($this->argument('user'));
            if (!$user) {
                $this->error("Invalid CID");

                return 0;
            }

            $this->sync($user);

            return 0;
        }

        //Syncronize Users
        $moodleIds = $this->moodle->getAllUserIdMap();
        User::with('visits', 'academyCompetencies.course', 'roles')->chunk(1000, function ($users) use ($moodleIds) {
            foreach ($users as $user) {
                if ($moodleIds->has($user->cid)) {
                    $this->sync($user, (int) $moodleIds[$user->cid]);
                }
            }
        });

        return 0;
    }

    /**
     * Synchronize Roles
     *
     * @param \App\User $user
     * @param int|null  $knownId Moodle user id, if already known (bulk sync path) — skips the
     *                           redundant existence-check lookup that the single-user CLI path
     *                           still needs to decide create-vs-update.
     *
     * @throws \Exception
     */
    private function sync(User $user, ?int $knownId = null)
    {
        //Update or Create
        if ($knownId !== null) {
            $id = $knownId;
            $this->moodle->updateUser($user, $id);
        } elseif ($id = $this->moodle->getUserId($user->cid)) {
            //Update Information
            $this->moodle->updateUser($user, $id);
        } else {
            //Create User
            $id = $this->moodle->createUser($user)[0]["id"];
        }
        $facilities = $user->visits->pluck('facility')->merge(collect($user->facility))->unique();

        //Assign Cohorts
        $this->moodle->clearUserCohorts($id);
        $cohorts = [];
        $cohorts[] = Helper::ratingShortFromInt($user->rating); //VATUSA level rating
        if ($user->flag_homecontroller) {
            $cohorts[] = "$user->facility-" . Helper::ratingShortFromInt($user->rating); //Facility level rating
            if (RoleHelper::userIsVATUSAStaff($user, false, true)
                || RoleHelper::userIsInstructor($user)
                || RoleHelper::userIsSeniorStaff($user, null, true)
                || RoleHelper::userIsMentor($user)) {
                $cohorts[] = "TNG"; //Training staff
            }
        }
        $cohorts[] = $user->facility; //Home Facility

        foreach ($user->visits->pluck('facility') as $facility) {
            //Visiting Facilities
            $cohorts[] = $facility . "-V"; //Facility level visitor
            $cohorts[] = "$facility-" . Helper::ratingShortFromInt($user->rating); //Facility level rating
        }
        if (!empty($cohorts)) {
            $this->moodle->assignCohortsBulk(array_map(fn ($cnumber) => ['uid' => $id, 'cnumber' => $cnumber],
                $cohorts));
        }

        //Clear Roles
        $this->moodle->clearUserRoles($id);

        $roles = [];
        //Assign Student Role
        foreach ($facilities as $facility) {
            $roles[] = [
                'uid'     => $id,
                'cid'     => $this->moodle->getCategoryFromShort($facility, true),
                'role'    => "STU",
                'context' => "coursecat"
            ];
        }

        //Assign Category Permissions
        $enrolments = [];
        if (RoleHelper::userIsVATUSAStaff($user, false, true) || RoleHelper::userIsSeniorStaff($user, $user->facility,
                true)) {
            $roles[] = [
                'uid' => $id, 'cid' => VATUSAMoodle::CATEGORY_CONTEXT_VATUSA, 'role' => "INS",
                'context' => "coursecat"
            ];
            $roles[] = [
                'uid'     => $id,
                'cid'     => $this->moodle->getCategoryFromShort($user->facility, true),
                'role'    => "TA",
                'context' => "coursecat"
            ];
            $artccCategories = $this->moodle->getAllSubcategories($this->moodle->getCategoryFromShort($user->facility),
                true);
            foreach ($artccCategories as $category) {
                $courses = $this->moodle->getCoursesInCategory($category);
                foreach ($courses as $course) {
                    $enrolments[] = ['uid' => $id, 'cid' => $course["id"]];
                }
            }
        }
        if (RoleHelper::userIsVATUSAStaff($user, false, true) || RoleHelper::userHas($user, "ZAE", "CBT")) {
            $roles[] = [
                'uid' => $id, 'cid' => VATUSAMoodle::CATEGORY_CONTEXT_VATUSA, 'role' => "CBT",
                'context' => "coursecat"
            ];
        }
        if (RoleHelper::userIsVATUSAStaff($user, false, true) || RoleHelper::userHas($user, $user->facility,
                "FACCBT")) {
            $roles[] = [
                'uid'     => $id,
                'cid'     => $this->moodle->getCategoryFromShort($user->facility, true),
                'role'    => "FACCBT",
                'context' => "coursecat"
            ];
        }
        if (RoleHelper::userIsVATUSAStaff($user, false, true) || RoleHelper::userIsInstructor($user,
                $user->facility)) {
            $roles[] = [
                'uid' => $id, 'cid' => VATUSAMoodle::CATEGORY_CONTEXT_VATUSA, 'role' => "INS",
                'context' => "coursecat"
            ];
            $roles[] = [
                'uid'     => $id,
                'cid'     => $this->moodle->getCategoryFromShort($user->facility, true),
                'role'    => "INS",
                'context' => "coursecat"
            ];
        }
        if (RoleHelper::userIsVATUSAStaff($user, false, true) || RoleHelper::userIsMentor($user)) {
            for ($i = Helper::ratingIntFromShort("S1"); $i <= $user->rating; $i++) {
                $context = "EXAM_CONTEXT_" . Helper::ratingShortFromInt($i);
                $roles[] = [
                    'uid' => $id, 'cid' => $this->moodle->getConstant($context), 'role' => "MTR",
                    'context' => "course"
                ];
            }
        }

        /*if (Role::where("cid", $user->cid)->where("facility", $user->facility)->where("role", "MTR")->exists()) {
            $roles[] = ['uid' => $id, 'cid' => $this->moodle->getCategoryFromShort($user->facility, true), 'role' => "MTR", 'context' => "coursecat"];
        }*/

        if (!empty($roles)) {
            $this->moodle->assignRolesBulk($roles);
        }
        if (!empty($enrolments)) {
            $this->moodle->enrolUsersBulk($enrolments);
        }
    }
}
