<?php

namespace App\CoreAPI;

use App\Classes\Throwable;

class CoreAPIHelperException extends \Exception {
    public function __construct($method, $uri, $status_code, $detail, $code = 0, Throwable $previous = null) {
        parent::__construct("{$method} {$uri} {$status_code} - {$detail}", $code, $previous);
        $this->status_code = $status_code;
        $this->detail = $detail;
    }

    public function __toString() {
        return __CLASS__ . ": {$this->message}";
    }
}