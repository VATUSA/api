<?php
namespace App\Helpers;

use Mail;

/**
 * Class EmailHelper
 * @package App\Helpers
 */
class EmailHelper {
    /**
     * @param $email
     * @param $subject
     * @param $template
     * @param $data
     */
    public static function sendEmail($email, $subject, $template, $data)
    {
        Mail::send($template, $data, function ($msg) use ($data, $email, $subject) {
            $msg->from('no-reply@vatusa.net', "VATUSA Web Services");
            $msg->to($email);
            $msg->subject("[VATUSA] $subject");
        });
    }

    /**
     * @param $email
     * @param $subject
     * @param $template
     * @param $data
     */
    public static function sendWelcomeEmail($email, $subject, $template, $data)
    {
        $welcome = $data['welcome'];
        $welcome = preg_replace("/%fname%/", $data['fname'], $welcome);
        $welcome = preg_replace("/%lname%/", $data['lname'], $welcome);
        static::sendEmail($email, $subject, $template, ['welcome' => $welcome]);
    }
}