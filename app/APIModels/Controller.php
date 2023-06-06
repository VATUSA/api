<?php

namespace App\APIModels;

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
}