<?php

namespace App\CoreAPIModels;

class Controller {
    public int $cid;
    public string $display_name;
    public string $first_name;
    public string $last_name;
    public string $email;
    public string $facility;
    public int $rating;
    public string $rating_short;
    public string $rating_long;
    public ?string $discord_id;
    public bool $in_division;
    public bool $receive_broadcast_emails;
    public bool $prevent_staff_assignment;
    public bool $is_promotion_eligible;
    public bool $is_transfer_eligible;
    public bool $is_visit_eligible;
    public bool $is_controller_interest;
    public array $roles;
    public array $visits;

    public static function fromAssoc($data): Controller {
        $controller = new Controller();
        $controller->cid = $data['cid'];
        $controller->display_name = $data['display_name'];
        $controller->first_name = $data['first_name'];
        $controller->last_name = $data['last_name'];
        $controller->email = $data['email'];
        $controller->facility = $data['facility'];
        $controller->rating = $data['rating'];
        $controller->rating_short = $data['rating_short'];
        $controller->rating_long = $data['rating_long'];
        $controller->discord_id = $data['discord_id'];
        $controller->in_division = $data['in_division'];
        $controller->receive_broadcast_emails = $data['receive_broadcast_emails'];
        $controller->prevent_staff_assignment = $data['prevent_staff_assignment'];
        $controller->is_promotion_eligible = $data['is_promotion_eligible'];
        $controller->is_transfer_eligible = $data['is_transfer_eligible'];
        $controller->is_visit_eligible = $data['is_visit_eligible'];
        $controller->is_controller_interest = $data['is_controller_interest'];
        $controller->roles = [];
        foreach ($data['roles'] as $role) {
            $controller->roles[] = ControllerRole::fromAssoc($role);
        }
        $controller->visits = $data['visits'];
        return $controller;
    }
}