<?php

namespace App\APIModels;

class TransferHold {
    public int $id;
    public Controller $controller;
    public string $hold;
    public ?string $start_date;
    public ?string $end_date;
    public bool $is_released;
    public ?int $released_by_cid;
    public ?int $created_by_cid;
}