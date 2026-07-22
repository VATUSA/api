<?php

namespace Tests\Unit;

use App\Classes\VATUSAMoodle;
use App\Console\Commands\MoodleSync;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Pure unit test — does not boot Laravel or touch a database.
 *
 * MoodleSync::diffCohorts() replaced the old clear-then-reassign-every-cohort-every-run
 * behavior with a diff that writes only actual changes. These tests lock in that the diff
 * produces the SAME end state as clear-then-reassign (user ends up in exactly the desired
 * cohorts, no others) while emitting no writes when nothing changed.
 */
class MoodleCohortDiffTest extends TestCase
{
    private function diff(int $id, array $desired, array $current, array $map): array
    {
        // createMock skips VATUSAMoodle's constructor (which would need Laravel config);
        // diffCohorts is pure and never touches $this->moodle.
        $moodle = $this->createMock(VATUSAMoodle::class);
        $command = new MoodleSync($moodle);

        $method = new ReflectionMethod($command, 'diffCohorts');
        $method->setAccessible(true);

        return $method->invoke($command, $id, $desired, $current, $map);
    }

    public function test_steady_state_emits_no_writes(): void
    {
        // User already in exactly the cohorts they should be in -> nothing to do.
        $diff = $this->diff(42, ['S1', 'ZDV'], [5, 10], ['S1' => 5, 'ZDV' => 10]);

        $this->assertSame([], $diff['adds']);
        $this->assertSame([], $diff['removes']);
    }

    public function test_missing_membership_is_added(): void
    {
        $diff = $this->diff(42, ['S1', 'ZDV'], [5], ['S1' => 5, 'ZDV' => 10]);

        $this->assertSame([['uid' => 42, 'cnumber' => 'ZDV']], $diff['adds']);
        $this->assertSame([], $diff['removes']);
    }

    public function test_stale_membership_is_removed(): void
    {
        // User is in cohort 99, which is no longer desired -> remove it.
        $diff = $this->diff(42, ['S1'], [5, 99], ['S1' => 5, 'ZDV' => 10]);

        $this->assertSame([], $diff['adds']);
        $this->assertSame([['uid' => 42, 'cohortid' => 99]], $diff['removes']);
    }

    public function test_add_and_remove_together(): void
    {
        $diff = $this->diff(42, ['S1', 'ZDV'], [10, 99], ['S1' => 5, 'ZDV' => 10]);

        $this->assertSame([['uid' => 42, 'cnumber' => 'S1']], $diff['adds']);
        $this->assertSame([['uid' => 42, 'cohortid' => 99]], $diff['removes']);
    }

    public function test_unknown_desired_idnumber_is_skipped_not_added(): void
    {
        // A desired cohort that does not exist in Moodle can't be added (sync never creates
        // cohorts); it must not appear as an add, and must not spuriously remove anything.
        $diff = $this->diff(42, ['S1', 'NOPE'], [5], ['S1' => 5]);

        $this->assertSame([], $diff['adds']);
        $this->assertSame([], $diff['removes']);
    }

    public function test_duplicate_desired_idnumbers_dedupe(): void
    {
        // computeSyncItems can list the same cohort twice (e.g. facility rating); a user
        // not yet in it should still only be added once.
        $diff = $this->diff(42, ['ZDV', 'ZDV'], [], ['ZDV' => 10]);

        $this->assertSame([['uid' => 42, 'cnumber' => 'ZDV']], $diff['adds']);
        $this->assertSame([], $diff['removes']);
    }
}
