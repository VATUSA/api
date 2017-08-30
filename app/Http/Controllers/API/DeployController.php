<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DeployController extends Controller
{
    public function getDeploy(Request $request)
    {
        exec("cd " . base_path() . " && sh deploy.sh", $output, $return);
        return implode($output);
    }
}
