<?php

namespace Tests\Unit;

use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

/**
 * Pure unit test — does not boot the Laravel application or touch a database.
 *
 * VATUSAMoodle::getAllUserIdMap() builds its map via
 * DB::table('user')->pluck('id', 'idnumber'), where the Moodle DB's `idnumber`
 * column is a VARCHAR holding the VATUSA CID as a string. MoodleSync::handle()
 * then looks entries up with the integer $user->cid. This test locks in the
 * assumption that swap relies on: PHP normalizes numeric string array keys to
 * integers, so Collection::has()/offsetGet() with an int CID still matches a
 * map keyed from string column values.
 */
class MoodleUserIdMapTest extends TestCase
{
    public function test_int_cid_lookup_matches_string_keyed_map(): void
    {
        // Simulates DB::connection('moodle')->table('user')->pluck('id', 'idnumber'),
        // where idnumber is fetched as a string from a VARCHAR column.
        $moodleIds = new Collection([
            '1234567' => 42,
            '7654321' => 99,
        ]);

        $this->assertTrue($moodleIds->has(1234567));
        $this->assertSame(42, $moodleIds[1234567]);

        $this->assertFalse($moodleIds->has(9999999));
    }

    public function test_users_absent_from_moodle_are_skipped(): void
    {
        $moodleIds = new Collection(['1234567' => 42]);

        $cidsToSync = collect([1234567, 2222222, 7654321])
            ->filter(fn ($cid) => $moodleIds->has($cid))
            ->values();

        $this->assertSame([1234567], $cidsToSync->all());
    }
}
