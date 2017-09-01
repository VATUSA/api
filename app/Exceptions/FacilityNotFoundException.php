<?php
namespace App\Exceptions;

/**
 * Class FacilityNotFoundException
 * @package App\Exceptions
 */
class FacilityNotFoundException extends \Exception
{
    /**
     * Return HTTP Status
     *
     * @return int
     */
    public function getStatus() { return 404; }
}
