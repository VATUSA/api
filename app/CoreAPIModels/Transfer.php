<?php

namespace App\CoreAPIModels;

class Transfer {
    public int $id;
    public Controller $controller;
    public string $to_facility;
    public string $from_facility;
    public string $reason;
    public string $created_at;
    public ?bool $approved;
    public ?int $approved_by;
    public ?string $approved_reason;
    public string $approved_at;

    public static function fromAssoc($data): Transfer {
        $transfer = new Transfer();
        $transfer->id = $data['id'];
        $transfer->controller = Controller::fromAssoc($data['controller']);
        $transfer->to_facility = $data['to_facility'];
        $transfer->from_facility = $data['from_facility'];
        $transfer->reason = $data['reason'];
        $transfer->created_at = $data['created_at'];
        $transfer->approved = $data['approved'];
        $transfer->approved_by = $data['approved_by'];
        $transfer->approved_reason = $data['approved_reason'];
        $transfer->approved_at = $data['approved_at'];
        return $transfer;
    }
}