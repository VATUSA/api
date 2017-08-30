<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DeployController extends Controller
{
    public function getDeploy(Request $request)
    {
        $payload = decode_json($request->input("payload"));
        if (!$payload) {
            \Log::warn("Invalid payload received from github push event: $payload");
            return;
        }
        exec("cd " . base_path() . " && sh deploy.sh", $output, $return);
        \Log::info("Deploy ran: " . base64_encode($output) . ", return $return");
        return implode($output);
    }
}
