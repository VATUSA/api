<?php

namespace App\Mail;

use App\Survey;
use App\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class SurveyAssignment extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $survey;
    public $assignment;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(User $user, Survey $survey, \App\SurveyAssignment $assignment)
    {
        $this->user = $user;
        $this->survey = $survey;
        $this->assignment = $assignment;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->view("tmp_" . $this->assignment->id);
    }
}
