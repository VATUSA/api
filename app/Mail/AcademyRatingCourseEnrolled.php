<?php

namespace App\Mail;

use App\AcademyExamAssignment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class AcademyRatingCourseEnrolled extends Mailable
{
    use Queueable, SerializesModels;
    
    public $assignment;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(AcademyExamAssignment $assignment)
    {
        $this->assignment = $assignment;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject('[VATUSA] Enrolled in Academy Rating Course')
            ->view('emails.academy.enrolled');
    }
}
