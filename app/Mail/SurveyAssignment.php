<?php

namespace App\Mail;

use App\Survey;
use App\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SurveyAssignment extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $survey;
    public $assignment;
    public $misc;

    /**
     * Create a new message instance.
     */
    public function __construct(User $user, Survey $survey, \App\SurveyAssignment $assignment, array $misc = [])
    {
        $this->user = $user;
        $this->survey = $survey;
        $this->assignment = $assignment;
        $this->misc = $misc;
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
