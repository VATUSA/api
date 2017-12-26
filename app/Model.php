<?php
namespace App;

use Illuminate\Database\Eloquent\Model as EloquentModel;

class Model extends EloquentModel
{
    protected function serializeDate(\DateTimeInterface $date) {
        return $date->format(\DateTime::RFC3339);
    }
}