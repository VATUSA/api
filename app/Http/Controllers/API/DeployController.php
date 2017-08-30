<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DeployController extends Controller
{
    public function getDeploy(Request $request) {
        if ($request->header("HTTP_X_GITHUB_EVENT") == "push") {
            $payload = json_parse($request->input("payload"));
            if (!$payload) {
                \Log::warn("Invalid payload received from github push event: $payload");
                return;
            }
            exec("cd " . base_path() . " && sh deploy.sh", $output, $return);
            \Log::info("Deploy ran: " . base64_encode($output) . ", return $return");
            return implode($output);
        }
    }
}
