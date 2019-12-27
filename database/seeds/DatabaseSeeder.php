<?php

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    private $user;
    private $pass;
    private $db;
    private $host;
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {

    }

    public function seed($info, $table = null) {
        if ($table == null) $table = $info;
        $this->command->info("Seeding $info...");
        //\DB::unprepared(file_get_contents("database/seeds/$table.sql"));
        exec("mysql -u $this->user -p$this->pass -h $this->host $this->db < database/seeds/$table.sql");
    }
}
