@extends('emails.layout')
@section('content')
    Hello {{ $assignment->student->fullname() }},
    <br><br>
    You have been enrolled in the <strong>{{ $assignment->course_name }} ({{ $assignment->rating->short }})</strong> rating course at the VATUSA Academy. This course will teach you the fundamentals of your new, prospective rating and will culminate in an end-of-course exam.
    <br><br>
    You must complete the course and exam within 30 days. If you do not meet this requirement, you will have to be re-enrolled by your instructor. Additionally, you have three attempts to pass the final exam. If this is not met, you must <a href="https://www.vatusa.net/help/ticket/new">open a support ticket</a>.
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
                            <a href="https://academy.vatusa.net/course/view.php?id={{ $assignment->course_id }}"
                               style="Margin: 0; border: 0 solid #3adb76; border-radius: 3px; color: #fefefe; display: inline-block; font-family: Helvetica, Arial, sans-serif; font-size: 16px; font-weight: bold; line-height: 1.3; margin: 0; padding: 8px 16px 8px 16px; text-align: left; text-decoration: none;">VATUSA
                                Academy ⇾</a></td>
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