<?php

namespace App\CoreAPIModels;

class ControllerRole {
    public string $role;
    public string $facility;

    public static function fromAssoc($data): ControllerRole {
        $role = new ControllerRole();
        $role->role = $data['role'];
        $role->facility = $data['facility'];
        return $role;
    }
}