<?php
namespace App\Exceptions;

/**
 * Class FacilityNotFoundException
 * @package App\Exceptions
 */
class JWTTokenException extends \Exception
{
    /**
     * Return HTTP Status
     *
     * @return int
     */
    public function getStatus() { return 403; }
}
