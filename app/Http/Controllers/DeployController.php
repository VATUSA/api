<?php
namespace App\Http\Controllers;

class DeployController extends Controller
{
    public function getDeploy(Request $request) {
        if ($request->header("HTTP_X_GITHUB_EVENT") == "push") {
            $payload = json_parse($request->input("payload"));
            if (!$payload) {
                \Log::warn("Invalid payload received from github push event: $payload");
                return;
            }
            system("cd " . base_path() . "; sh deploy.sh");
        }
    }
}