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

    /** @var int Microseconds to sleep between successive bulk sub-batch calls. Moodle's
     *           cohort/role writes trigger its own course cache-invalidation (mdl_course.cacherev),
     *           which was observed serializing against itself under back-to-back calls and
     *           causing 30s+ lock waits. Pacing our own call rate keeps concurrent invalidations
     *           low enough for Moodle's DB to keep up. */
    private const FLUSH_PACING_USEC = 300000;

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

            $id = $this->resolveMoodleId($user);
            $items = $this->computeSyncItems($user, $id);
            $this->flushItems($id, $items);

            return 0;
        }

        //Syncronize Users
        $moodleIds = $this->moodle->getAllUserIdMap();
        $cohortIdMap = $this->moodle->getCohortIdMap();
        logger()->info("moodle:sync starting bulk pass: {$moodleIds->count()} Moodle-linked users");

        $chunkNum = 0;
        $totals = ['users' => 0, 'cohortAdds' => 0, 'cohortRemoves' => 0, 'roles' => 0,
                   'enrolments' => 0, 'failedBatches' => 0];

        User::with('visits', 'academyCompetencies.course', 'roles')->chunk(1000,
            function ($users) use ($moodleIds, $cohortIdMap, &$chunkNum, &$totals) {
                $chunkNum++;

                // Resolve this chunk's users to their Moodle ids up front, then read all
                // their current cohort memberships in one query so we can diff desired vs.
                // current and only write actual changes (see diffCohorts()).
                $chunkUsers = [];
                foreach ($users as $user) {
                    if ($moodleIds->has($user->cid)) {
                        $chunkUsers[(int) $moodleIds[$user->cid]] = $user;
                    }
                }
                $currentCohorts = $this->moodle->getCohortMembershipsForUsers(array_keys($chunkUsers));

                $updates = $cohortAdds = $cohortRemoves = $roles = $enrolments = [];
                foreach ($chunkUsers as $id => $user) {
                    $items = $this->computeSyncItems($user, $id);
                    $updates[] = $items['update'];

                    $diff = $this->diffCohorts($id, $items['cohortIdnumbers'],
                        $currentCohorts[$id] ?? [], $cohortIdMap);
                    $cohortAdds = array_merge($cohortAdds, $diff['adds']);
                    $cohortRemoves = array_merge($cohortRemoves, $diff['removes']);

                    $roles = array_merge($roles, $items['roles']);
                    $enrolments = array_merge($enrolments, $items['enrolments']);
                }

                $failed = 0;
                $failed += $this->flushBulk($chunkNum, 'update', $updates,
                    fn ($items) => $this->moodle->updateUsersBulk($items));
                usleep(self::FLUSH_PACING_USEC);
                $failed += $this->flushBulk($chunkNum, 'cohort-removes', $cohortRemoves,
                    fn ($items) => $this->moodle->removeCohortsBulk($items));
                usleep(self::FLUSH_PACING_USEC);
                $failed += $this->flushBulk($chunkNum, 'cohort-adds', $cohortAdds,
                    fn ($items) => $this->moodle->assignCohortsBulk($items));
                usleep(self::FLUSH_PACING_USEC);
                $failed += $this->flushBulk($chunkNum, 'roles', $roles,
                    fn ($items) => $this->moodle->assignRolesBulk($items));
                usleep(self::FLUSH_PACING_USEC);
                $failed += $this->flushBulk($chunkNum, 'enrolments', $enrolments,
                    fn ($items) => $this->moodle->enrolUsersBulk($items));

                $totals['users'] += count($chunkUsers);
                $totals['cohortAdds'] += count($cohortAdds);
                $totals['cohortRemoves'] += count($cohortRemoves);
                $totals['roles'] += count($roles);
                $totals['enrolments'] += count($enrolments);
                $totals['failedBatches'] += $failed;

                logger()->info("moodle:sync chunk {$chunkNum}: " . count($chunkUsers) . " users, "
                    . count($cohortAdds) . " cohort adds, " . count($cohortRemoves) . " cohort removes, "
                    . count($roles) . " roles, " . count($enrolments) . " enrolments"
                    . ($failed > 0 ? ", {$failed} sub-batches failed" : ""));
            });

        logger()->info("moodle:sync finished bulk pass: {$totals['users']} users seen, "
            . "{$totals['cohortAdds']} cohort adds, {$totals['cohortRemoves']} cohort removes, "
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
     * Moodle to process within the 30s HTTP timeout in one call).
     *
     * A failed sub-batch is logged and skipped — deliberately NOT retried. A write that
     * timed out on our side usually keeps running server-side, holding row locks (e.g. the
     * mdl_course.cacherev bump every cohort/role write triggers); firing a retry just piles
     * a second writer onto the same contended rows and makes the pileup worse. moodle:sync
     * recomputes full desired state every run, so anything skipped self-heals next run.
     *
     * @param int      $chunkNum
     * @param string   $kind
     * @param array    $items
     * @param callable $call
     *
     * @return int Number of sub-batches that failed
     */
    private function flushBulk(int $chunkNum, string $kind, array $items, callable $call): int
    {
        $failures = 0;

        foreach (array_chunk($items, self::FLUSH_BATCH_SIZE) as $batchNum => $batch) {
            if ($batchNum > 0) {
                usleep(self::FLUSH_PACING_USEC);
            }

            try {
                $call($batch);
            } catch (\Exception $e) {
                logger()->error("moodle:sync chunk {$chunkNum} {$kind} batch {$batchNum}: "
                    . "failed, skipping (will retry next run): {$e->getMessage()}");
                $failures++;
            }
        }

        return $failures;
    }

    /**
     * Diff a user's desired cohort set (idnumbers) against their current memberships
     * (cohort ids), so we only add memberships that are missing and remove ones that no
     * longer apply — instead of clearing and re-inserting every cohort every run. The old
     * clear-then-reassign produced the same end state but rewrote ~all memberships every
     * pass, and each write triggers a mdl_course.cacherev bump that serializes on a tiny
     * hot table; diffing collapses steady-state writes to near zero.
     *
     * End state is identical to the previous clear-then-reassign: the user ends up in
     * exactly the desired cohorts and no others.
     *
     * @param int                 $id               Moodle user id
     * @param string[]            $desiredIdnumbers Desired cohort idnumbers (may repeat)
     * @param int[]               $currentCohortIds Cohort ids the user currently belongs to
     * @param array<string,int>   $cohortIdMap      idnumber => cohort id
     *
     * @return array{adds: array, removes: array}
     */
    private function diffCohorts(int $id, array $desiredIdnumbers, array $currentCohortIds, array $cohortIdMap): array
    {
        // Resolve desired idnumbers to cohort ids, skipping any that don't exist in Moodle
        // (the sync never creates cohorts, so an unknown idnumber was a no-op add before too).
        $desiredIds = [];
        foreach (array_unique($desiredIdnumbers) as $idnumber) {
            if (isset($cohortIdMap[$idnumber])) {
                $desiredIds[$cohortIdMap[$idnumber]] = $idnumber;
            }
        }

        $adds = $removes = [];
        foreach ($desiredIds as $cohortId => $idnumber) {
            if (!in_array($cohortId, $currentCohortIds, true)) {
                $adds[] = ['uid' => $id, 'cnumber' => $idnumber];
            }
        }
        foreach ($currentCohortIds as $cohortId) {
            if (!isset($desiredIds[$cohortId])) {
                $removes[] = ['uid' => $id, 'cohortid' => $cohortId];
            }
        }

        return ['adds' => $adds, 'removes' => $removes];
    }

    /**
     * Flush a single user's computed items immediately (single-user CLI path). Reads the
     * one user's current cohort memberships to run the same diff the bulk path uses; item
     * counts never approach FLUSH_BATCH_SIZE here.
     *
     * @param int   $id    Moodle user id
     * @param array $items
     */
    private function flushItems(int $id, array $items)
    {
        $current = $this->moodle->getCohortMembershipsForUsers([$id])[$id] ?? [];
        $diff = $this->diffCohorts($id, $items['cohortIdnumbers'], $current, $this->moodle->getCohortIdMap());

        $this->flushBulk(0, 'update', [$items['update']], fn ($items) => $this->moodle->updateUsersBulk($items));
        $this->flushBulk(0, 'cohort-removes', $diff['removes'],
            fn ($items) => $this->moodle->removeCohortsBulk($items));
        $this->flushBulk(0, 'cohort-adds', $diff['adds'], fn ($items) => $this->moodle->assignCohortsBulk($items));
        $this->flushBulk(0, 'roles', $items['roles'], fn ($items) => $this->moodle->assignRolesBulk($items));
        $this->flushBulk(0, 'enrolments', $items['enrolments'],
            fn ($items) => $this->moodle->enrolUsersBulk($items));
    }

    /**
     * Compute what needs to happen in Moodle for a user — desired cohorts, roles, and
     * enrolments — without making any HTTP calls itself. Callers accumulate these across
     * many users and flush them as bulk calls (whole-table sync), or flush a single user's
     * items immediately (single-user CLI path). Cohorts are returned as desired idnumbers
     * and diffed against current membership by the caller (see diffCohorts()); roles are
     * still cleared per-user here via a direct DB delete and fully reassigned.
     *
     * @param \App\User $user
     * @param int       $id Moodle user id
     *
     * @return array{update: array, cohortIdnumbers: string[], roles: array, enrolments: array}
     * @throws \Exception
     */
    private function computeSyncItems(User $user, int $id): array
    {
        $facilities = $user->visits->pluck('facility')->merge(collect($user->facility))->unique();

        //Desired Cohorts (diffed against current membership by the caller — no unconditional clear)
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
            'update'          => ['id' => $id, 'fname' => $user->fname, 'lname' => $user->lname, 'email' => $user->email],
            'cohortIdnumbers' => $cohorts,
            'roles'           => $roles,
            'enrolments'      => $enrolments,
        ];
    }
}
