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

    /** @var int Max items sent in a single Moodle bulk call — 1000-user chunks can accumulate
     *           several thousand cohort/role entries, which was timing out Moodle's 30s
     *           HTTP_TIMEOUT_SECONDS bound on dense chunks. Flush in smaller sub-batches. */
    private const FLUSH_BATCH_SIZE = 200;

    /** @var int Attempts per sub-batch before giving up on it and moving on */
    private const MAX_ATTEMPTS = 2;

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
        $totals = ['users' => 0, 'updates' => 0, 'cohorts' => 0, 'roles' => 0, 'enrolments' => 0, 'failedBatches' => 0];

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

                $failed = 0;
                $failed += $this->flushBulk($chunkNum, 'update', $updates,
                    fn ($items) => $this->moodle->updateUsersBulk($items));
                $failed += $this->flushBulk($chunkNum, 'cohorts', $cohorts,
                    fn ($items) => $this->moodle->assignCohortsBulk($items));
                $failed += $this->flushBulk($chunkNum, 'roles', $roles,
                    fn ($items) => $this->moodle->assignRolesBulk($items));
                $failed += $this->flushBulk($chunkNum, 'enrolments', $enrolments,
                    fn ($items) => $this->moodle->enrolUsersBulk($items));

                $totals['users'] += count($users);
                $totals['updates'] += count($updates);
                $totals['cohorts'] += count($cohorts);
                $totals['roles'] += count($roles);
                $totals['enrolments'] += count($enrolments);
                $totals['failedBatches'] += $failed;

                logger()->info("moodle:sync chunk {$chunkNum}: " . count($users) . " users, "
                    . count($updates) . " updates, " . count($cohorts) . " cohorts, "
                    . count($roles) . " roles, " . count($enrolments) . " enrolments"
                    . ($failed > 0 ? ", {$failed} sub-batches failed" : ""));
            });

        logger()->info("moodle:sync finished bulk pass: {$totals['users']} users seen, "
            . "{$totals['updates']} updates, {$totals['cohorts']} cohorts, "
            . "{$totals['roles']} roles, {$totals['enrolments']} enrolments across {$chunkNum} chunks, "
            . "{$totals['failedBatches']} sub-batches permanently failed");

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
     * Flush a bulk call for a chunk, split into FLUSH_BATCH_SIZE-sized sub-batches (a
     * 1000-user chunk can accumulate several thousand cohort/role entries, too large for
     * Moodle to process within the 30s HTTP timeout in one call). Each sub-batch gets
     * MAX_ATTEMPTS tries; a sub-batch that still fails is logged and skipped rather than
     * aborting the rest of the chunk or the run — the next scheduled run will pick up
     * anything missed, since moodle:sync recomputes full state every time.
     *
     * @param int      $chunkNum
     * @param string   $kind
     * @param array    $items
     * @param callable $call
     *
     * @return int Number of sub-batches that failed after all attempts
     */
    private function flushBulk(int $chunkNum, string $kind, array $items, callable $call): int
    {
        $failures = 0;

        foreach (array_chunk($items, self::FLUSH_BATCH_SIZE) as $batchNum => $batch) {
            for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
                try {
                    $call($batch);
                    continue 2;
                } catch (\Exception $e) {
                    if ($attempt < self::MAX_ATTEMPTS) {
                        logger()->warning("moodle:sync chunk {$chunkNum} {$kind} batch {$batchNum}: "
                            . "attempt {$attempt} failed ({$e->getMessage()}), retrying");
                        continue;
                    }
                    logger()->error("moodle:sync chunk {$chunkNum} {$kind} batch {$batchNum}: "
                        . "failed after {$attempt} attempts, skipping: {$e->getMessage()}");
                    $failures++;
                }
            }
        }

        return $failures;
    }

    /**
     * Flush a single user's computed items immediately (single-user CLI path). Still routed
     * through flushBulk() for consistent retry behavior, though a single user's item counts
     * never approach FLUSH_BATCH_SIZE.
     *
     * @param array $items
     */
    private function flushItems(array $items)
    {
        $this->flushBulk(0, 'update', [$items['update']], fn ($items) => $this->moodle->updateUsersBulk($items));
        $this->flushBulk(0, 'cohorts', $items['cohorts'], fn ($items) => $this->moodle->assignCohortsBulk($items));
        $this->flushBulk(0, 'roles', $items['roles'], fn ($items) => $this->moodle->assignRolesBulk($items));
        $this->flushBulk(0, 'enrolments', $items['enrolments'],
            fn ($items) => $this->moodle->enrolUsersBulk($items));
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
