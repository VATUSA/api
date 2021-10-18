<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class LegacyExamResult extends Mailable
{
    use Queueable, SerializesModels;

    public $data, $passed;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(array $data, bool $passed)
    {
        $this->data = $data;
        $this->passed = $passed;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject('[VATUSA] Exam ' . ($this->passed ? "Passed" : "Failed"))->view($this->passed ? "emails.exam.passed" : "emails.exam.failed");
    }
}
