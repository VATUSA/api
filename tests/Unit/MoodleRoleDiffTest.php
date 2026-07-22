<?php

namespace Tests\Unit;

use App\Classes\VATUSAMoodle;
use App\Console\Commands\MoodleSync;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Pure unit test — does not boot Laravel or touch a database.
 *
 * MoodleSync::diffRoles() replaced the old clear-then-reassign-every-role-every-run behavior
 * with a diff that writes only actual changes. A role assignment's identity is
 * (roleid, contextid). These tests lock in that the diff produces the SAME end state as
 * clear-then-reassign (user ends up with exactly the desired managed roles, no others) while
 * emitting no writes when nothing changed.
 */
class MoodleRoleDiffTest extends TestCase
{
    /** Role short name => id, mirroring VATUSAMoodle::$roleIds for the mock. */
    private const ROLE_IDS = ['TA' => 1, 'INS' => 4, 'STU' => 5, 'MTR' => 9, 'CBT' => 10, 'FACCBT' => 11];

    private function diff(int $id, array $desired, array $current): array
    {
        $moodle = $this->createMock(VATUSAMoodle::class);
        $moodle->method('roleIdFor')->willReturnCallback(fn ($role) => self::ROLE_IDS[$role] ?? null);
        $command = new MoodleSync($moodle);

        $method = new ReflectionMethod($command, 'diffRoles');
        $method->setAccessible(true);

        return $method->invoke($command, $id, $desired, $current);
    }

    private function desired(int $id, string $role, ?int $cid, string $context = 'coursecat'): array
    {
        return ['uid' => $id, 'cid' => $cid, 'role' => $role, 'context' => $context];
    }

    private function current(int $roleid, int $contextid, int $contextlevel = 40): array
    {
        return ['roleid' => $roleid, 'contextid' => $contextid, 'contextlevel' => $contextlevel];
    }

    public function test_steady_state_emits_no_writes(): void
    {
        // Desired INS@43 already assigned -> nothing to do.
        $diff = $this->diff(42, [$this->desired(42, 'INS', 43)], [$this->current(4, 43)]);

        $this->assertSame([], $diff['adds']);
        $this->assertSame([], $diff['removes']);
    }

    public function test_missing_role_is_added(): void
    {
        $diff = $this->diff(42, [$this->desired(42, 'INS', 43), $this->desired(42, 'TA', 100)],
            [$this->current(4, 43)]);

        $this->assertSame([$this->desired(42, 'TA', 100)], $diff['adds']);
        $this->assertSame([], $diff['removes']);
    }

    public function test_stale_managed_role_is_removed(): void
    {
        // User currently has MTR@500 (contextlevel course=50) that is no longer desired.
        $diff = $this->diff(42, [$this->desired(42, 'INS', 43)],
            [$this->current(4, 43), $this->current(9, 500, 50)]);

        $this->assertSame([], $diff['adds']);
        $this->assertSame([['uid' => 42, 'roleid' => 9, 'contextid' => 500, 'contextlevel' => 50]],
            $diff['removes']);
    }

    public function test_add_and_remove_together(): void
    {
        $diff = $this->diff(42, [$this->desired(42, 'INS', 43)], [$this->current(1, 100)]);

        $this->assertSame([$this->desired(42, 'INS', 43)], $diff['adds']);
        $this->assertSame([['uid' => 42, 'roleid' => 1, 'contextid' => 100, 'contextlevel' => 40]],
            $diff['removes']);
    }

    public function test_unknown_role_is_skipped_not_added(): void
    {
        // A role short name with no id mapping can't be assigned; must not appear as an add
        // and must not spuriously remove an existing assignment.
        $diff = $this->diff(42, [$this->desired(42, 'INS', 43), $this->desired(42, 'BOGUS', 99)],
            [$this->current(4, 43)]);

        $this->assertSame([], $diff['adds']);
        $this->assertSame([], $diff['removes']);
    }

    public function test_null_context_desired_role_is_skipped(): void
    {
        // getCategoryFromShort() can return null (facility not in Moodle); such a role can't
        // be assigned and must not be diffed.
        $diff = $this->diff(42, [$this->desired(42, 'TA', null)], [$this->current(4, 43)]);

        $this->assertSame([], $diff['adds']);
        // The existing INS@43 is not desired -> removed (proves null-cid role didn't shadow it).
        $this->assertSame([['uid' => 42, 'roleid' => 4, 'contextid' => 43, 'contextlevel' => 40]],
            $diff['removes']);
    }

    public function test_duplicate_desired_roles_dedupe(): void
    {
        // computeSyncItems can list INS@43 twice (staff branch + instructor branch); a user
        // not yet holding it should be added only once.
        $diff = $this->diff(42, [$this->desired(42, 'INS', 43), $this->desired(42, 'INS', 43)], []);

        $this->assertSame([$this->desired(42, 'INS', 43)], $diff['adds']);
        $this->assertSame([], $diff['removes']);
    }
}
