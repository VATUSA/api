<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class VATSIMFlights extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'vatsim:flights';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update cache of VATSIM flights';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->get("https://status.vatsim.net/status.json", ['timeout' => 10]); // 10 second timeout
            $status = $response->getBody()->getContents();
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            \Log::error("VATSIMFlights: Error retrieving VATSIM status.json: " . $e->getMessage());
            return;
        }

        if (!$status) {
            \Log::notice("VATSIMFlights: There was an error retrieving VATSIM data from status.json. Status is empty.");
            return;
        }
        $server = json_decode($status, true)["data"]["v3"][0] ?? null;
        if (!$server) {
            \Log::notice("VATSIMFlights: There was an error retrieving VATSIM data from status.json. The array is invalid or server URL missing.");
            return;
        }

        try {
            $response = $client->get($server, ['timeout' => 20]); // 20 second timeout for main data
            $data = $response->getBody()->getContents();
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            \Log::error("VATSIMFlights: Error retrieving VATSIM data from server ($server): " . $e->getMessage());
            return;
        }

        if (!$data) {
            \Log::notice("VATSIMFlights: There was an error retrieving VATSIM data from server ($server). Data is empty.");
            return;
        }
        $vdata = json_decode($data, true);
        $pilots = $vdata["pilots"];
        foreach ($pilots as $pilot) {
            if (!isset($pilot["latitude"], $pilot["longitude"]) || $pilot["latitude"] < 0 || $pilot["longitude"] > -20) {
                continue;    // Only log part of our hemisphere
            }
            $planes[] = [
                'callsign' => $pilot["callsign"] ?? "",
                'cid'      => $pilot["cid"] ?? 0,
                'type'     => $pilot["flight_plan"]["aircraft_short"] ?? "",
                'dep'      => $pilot["flight_plan"]["departure"] ?? "",
                'arr'      => $pilot["flight_plan"]["arrival"] ?? "",
                'route'    => $pilot["flight_plan"]["route"] ?? "",
                'lat'      => $pilot["latitude"] ?? "",
                'lon'      => $pilot["longitude"] ?? "",
                'hdg'      => $pilot["heading"] ?? 0,
                'spd'      => $pilot["groundspeed"] ?? 0,
                'alt'      => $pilot["altitude"] ?? 0
            ];
        }

        \Cache::put("vatsim.data", json_encode($planes ?? [], JSON_NUMERIC_CHECK), 60);      // Keep 1 minute
    }
}
