<?php
namespace App\Exceptions;

/**
 * Class ReturnPathNotFoundException
 * @package App\Exceptions
 */
class ReturnPathNotFoundException extends \Exception
{
    /**
     * Return HTTP Status
     *
     * @return int
     */
    public function getStatus() { return 404; }
}
