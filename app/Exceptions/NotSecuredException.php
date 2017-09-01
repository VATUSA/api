<?php
namespace App\Exceptions;

/**
 * Class FacilityNotFoundException
 * @package App\Exceptions
 */
class NotSecuredException extends \Exception
{
    /**
     * Return HTTP Status
     *
     * @return int
     */
    public function getStatus() { return 403; }
}
