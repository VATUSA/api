<?php

namespace Tests\Unit;

use App\Classes\VATUSAMoodle;
use Exception;
use PHPUnit\Framework\TestCase;

/**
 * Pure unit test — does not boot Laravel.
 *
 * Moodle's REST server reports errors as a normal response body with an "exception" key.
 * The bulk writes used by moodle:sync must turn that into a thrown exception, otherwise
 * MoodleSync::flushBulk() counts a rejected write as a success.
 */
class MoodleRequireSuccessTest extends TestCase
{
    public function testPassesThroughSuccessfulResponses(): void
    {
        $this->assertNull(VATUSAMoodle::requireSuccess(null));
        $this->assertSame([], VATUSAMoodle::requireSuccess([]));
        $warnings = ['warnings' => [['warningcode' => 'x', 'message' => 'y']]];
        $this->assertSame($warnings, VATUSAMoodle::requireSuccess($warnings));
    }

    public function testThrowsOnMoodleException(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Moodle invalidparameter: Invalid parameter value detected');

        VATUSAMoodle::requireSuccess([
            'exception' => 'core\\exception\\invalid_parameter_exception',
            'errorcode' => 'invalidparameter',
            'message'   => 'Invalid parameter value detected',
        ]);
    }
}
