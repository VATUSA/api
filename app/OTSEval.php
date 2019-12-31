<?php

namespace App;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;

class OTSEval extends Model
{
    public function trainingRecord()
    {
        return $this->belongsTo(TrainingRecord::class);
        //Optional. No relationship if the eval is created independently.
        //On training record display, search for independent evals (denoted with *) by mapping
        //position to level (APP = S3).
    }

    public function student() {
        return $this->belongsTo(User::class, 'student_id', 'cid');
    }

    public function instructor() {
        return $this->belongsTo(User::class, 'instructor_id', 'cid');
    }

    public function getContent()
    {
        //TODO might need more here
        try {
            $content = File::get(storage_path('app/otsEvals/' . $this->filename . '.json'));
        } catch (FileNotFoundException $e) {
            $content = null;
        }

        return $content;
    }
}
