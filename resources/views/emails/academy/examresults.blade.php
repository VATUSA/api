@extends('emails.layout')
@section('content')
    Hello {{ $result['studentName']}},
    <br><br>
    You have successfully submitted your {{ $result['testName'] }} exam.
    <br><br>
    <strong>Attempt:</strong> {{ $result['attemptNum'] }}
    <br>
    <strong>Score:</strong> {{ $result['numCorrect'] }}/{{ $result['totalQuestions'] }} ({{ $result['grade'] }}%)
    <br>
    <strong>Outcome: <span
            style="color: {{ $result['passed'] ? 'green':'red' }}">{{ $result['passed'] ? 'Pass' : 'Fail' }}</span></strong>
    <br><br>
    @if(!$result['passed'])
        @if($result['attemptNum'] < 3)
            Unfortunately, you must retake the exam. The passing grade is an {{ $result['passingGrade'] }}%. You have {{ 3 - $result['attemptNum'] }} attempt{{3 - $result['attemptNum'] === 2 ? 's':''}}  remaining before training staff intervention is required.
        @else
            You have used all three attempts. To retake the exam, you must <a
                href="https://www.vatusa.net/help/ticket/new">open a support ticket</a>.
        @endif
    @else
        Congratulations! You have passed your exam. You will not receive a promotion to the new rating until an OTS has been conducted and passed.
    @endif
    <br><br>
    <table class="button success float-center" align="center"
           style="border-collapse: collapse; border-spacing: 0; float: none; padding: 0; text-align: center; vertical-align: top; width: auto;">
        <tbody>
        <tr style="padding: 0; text-align: left; vertical-align: top;">
            <td style="-moz-hyphens: auto; -webkit-hyphens: auto; Margin: 0; border-collapse: collapse !important; color: #0a0a0a; font-family: Helvetica, Arial, sans-serif; font-size: 16px; font-weight: normal; hyphens: auto; line-height: 1.3; margin: 0; padding: 0; text-align: left; vertical-align: top; word-wrap: break-word;">
                <table
                    style="border-collapse: collapse; border-spacing: 0; padding: 0; text-align: left; vertical-align: top;">
                    <tbody>
                    <tr style="padding: 0; text-align: left; vertical-align: top;">
                        <td style="-moz-hyphens: auto; -webkit-hyphens: auto; Margin: 0; background: #3adb76; border: 0px solid #3adb76; border-collapse: collapse !important; color: #fefefe; font-family: Helvetica, Arial, sans-serif; font-size: 16px; font-weight: normal; hyphens: auto; line-height: 1.3; margin: 0; padding: 0; text-align: left; vertical-align: top; word-wrap: break-word;">
                            <a href="https://academy.vatusa.net/mod/quiz/review.php?attempt={{ $result['attemptId'] }}"
                               style="Margin: 0; border: 0 solid #3adb76; border-radius: 3px; color: #fefefe; display: inline-block; font-family: Helvetica, Arial, sans-serif; font-size: 16px; font-weight: bold; line-height: 1.3; margin: 0; padding: 8px 16px 8px 16px; text-align: left; text-decoration: none;">View
                                Result ⇾</a></td>
                    </tr>
                    </tbody>
                </table>
            </td>
        </tr>
        </tbody>
    </table>
    <br><br>
    If you have any questions, please contact your instructor.
    <br><br>
@endsection