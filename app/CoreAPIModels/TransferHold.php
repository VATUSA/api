<?php

namespace App\CoreAPIModels;

class TransferHold {
    public int $id;
    public Controller $controller;
    public string $hold;
    public ?string $start_date;
    public ?string $end_date;
    public bool $is_released;
    public ?int $released_by_cid;
    public ?int $created_by_cid;

    public static function fromAssoc($data): TransferHold {
        $hold = new TransferHold();
        $hold->id = $data['id'];
        $hold->controller = Controller::fromAssoc($data['controller']);
        $hold->start_date = $data['start_date'];
        $hold->end_date = $data['end_date'];
        $hold->is_released = $data['is_released'];
        $hold->released_by_cid = $data['released_by_cid'];
        $hold->created_by_cid = $data['created_by_cid'];
        return $hold;
    }
}