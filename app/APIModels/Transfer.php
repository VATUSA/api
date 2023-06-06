<?php

namespace App\APIModels;

class Transfer {
    public int $id;
    public Controller $controller;
    public string $to_facility;
    public string $from_facility;
    public string $reason;
    public string $created_at;
    public bool $approved;
    public int $approved_by;
    public string $approved_reason;
    public string $approved_at;
}