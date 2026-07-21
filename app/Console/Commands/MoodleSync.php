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

            $items = $this->computeSyncItems($user, $this->resolveMoodleId($user));
            $this->flushItems($items);

            return 0;
        }

        //Syncronize Users
        $moodleIds = $this->moodle->getAllUserIdMap();
        logger()->info("moodle:sync starting bulk pass: {$moodleIds->count()} Moodle-linked users");

        $chunkNum = 0;
        $totals = ['users' => 0, 'updates' => 0, 'cohorts' => 0, 'roles' => 0, 'enrolments' => 0];

        User::with('visits', 'academyCompetencies.course', 'roles')->chunk(1000,
            function ($users) use ($moodleIds, &$chunkNum, &$totals) {
                $chunkNum++;
                $updates = $cohorts = $roles = $enrolments = [];

                foreach ($users as $user) {
                    if (!$moodleIds->has($user->cid)) {
                        continue;
                    }
                    $items = $this->computeSyncItems($user, (int) $moodleIds[$user->cid]);
                    $updates[] = $items['update'];
                    $cohorts = array_merge($cohorts, $items['cohorts']);
                    $roles = array_merge($roles, $items['roles']);
                    $enrolments = array_merge($enrolments, $items['enrolments']);
                }

                $this->flushBulk($chunkNum, 'update', $updates, fn ($items) => $this->moodle->updateUsersBulk($items));
                $this->flushBulk($chunkNum, 'cohorts', $cohorts,
                    fn ($items) => $this->moodle->assignCohortsBulk($items));
                $this->flushBulk($chunkNum, 'roles', $roles, fn ($items) => $this->moodle->assignRolesBulk($items));
                $this->flushBulk($chunkNum, 'enrolments', $enrolments,
                    fn ($items) => $this->moodle->enrolUsersBulk($items));

                $totals['users'] += count($users);
                $totals['updates'] += count($updates);
                $totals['cohorts'] += count($cohorts);
                $totals['roles'] += count($roles);
                $totals['enrolments'] += count($enrolments);

                logger()->info("moodle:sync chunk {$chunkNum}: " . count($users) . " users, "
                    . count($updates) . " updates, " . count($cohorts) . " cohorts, "
                    . count($roles) . " roles, " . count($enrolments) . " enrolments");
            });

        logger()->info("moodle:sync finished bulk pass: {$totals['users']} users seen, "
            . "{$totals['updates']} updates, {$totals['cohorts']} cohorts, "
            . "{$totals['roles']} roles, {$totals['enrolments']} enrolments across {$chunkNum} chunks");

        return 0;
    }

    /**
     * Resolve a user's Moodle id for the single-user CLI path (create-or-update decision).
     *
     * @param \App\User $user
     *
     * @return int
     * @throws \Exception
     */
    private function resolveMoodleId(User $user): int
    {
        if ($id = $this->moodle->getUserId($user->cid)) {
            return $id;
        }

        return $this->moodle->createUser($user)[0]["id"];
    }

    /**
     * Flush one bulk call for a chunk, logging and rethrowing on failure so a mid-run
     * failure points straight at which chunk and which call broke.
     *
     * @param int      $chunkNum
     * @param string   $kind
     * @param array    $items
     * @param callable $call
     *
     * @throws \Exception
     */
    private function flushBulk(int $chunkNum, string $kind, array $items, callable $call)
    {
        if (empty($items)) {
            return;
        }

        try {
            $call($items);
        } catch (\Exception $e) {
            logger()->error("moodle:sync chunk {$chunkNum}: {$kind} bulk call failed: {$e->getMessage()}");
            throw $e;
        }
    }

    /**
     * Flush a single user's computed items immediately (single-user CLI path).
     *
     * @param array $items
     *
     * @throws \Exception
     */
    private function flushItems(array $items)
    {
        $this->moodle->updateUsersBulk([$items['update']]);
        if (!empty($items['cohorts'])) {
            $this->moodle->assignCohortsBulk($items['cohorts']);
        }
        if (!empty($items['roles'])) {
            $this->moodle->assignRolesBulk($items['roles']);
        }
        if (!empty($items['enrolments'])) {
            $this->moodle->enrolUsersBulk($items['enrolments']);
        }
    }

    /**
     * Compute what needs to happen in Moodle for a user — cohorts, roles, and enrolments —
     * without making any HTTP calls itself. Callers accumulate these across many users and
     * flush them as bulk calls (whole-table sync), or flush a single user's items
     * immediately (single-user CLI path). Cohort/role clearing stays per-user since it's a
     * direct DB delete (cheap, not the HTTP bottleneck this split targets).
     *
     * @param \App\User $user
     * @param int       $id Moodle user id
     *
     * @return array{update: array, cohorts: array, roles: array, enrolments: array}
     * @throws \Exception
     */
    private function computeSyncItems(User $user, int $id): array
    {
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

        return [
            'update'     => ['id' => $id, 'fname' => $user->fname, 'lname' => $user->lname, 'email' => $user->email],
            'cohorts'    => array_map(fn ($cnumber) => ['uid' => $id, 'cnumber' => $cnumber], $cohorts),
            'roles'      => $roles,
            'enrolments' => $enrolments,
        ];
    }
}
