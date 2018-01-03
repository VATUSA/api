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
        $this->user = config('database.connections.mysql.username');
        $this->pass = config('database.connections.mysql.password');
        $this->db = config('database.connections.mysql.database');
        $this->host = config('database.connections.mysql.host');
        $this->command->info("Seeding tables...");
        $this->seed("controllers");
        $this->seed("exams");
        $this->seed("facilities");
        $this->seed("knowledgebase", "kb_cats");
        $this->seed("knowledgebase FAQ", "kb_qs");
        $this->seed("promotions");
        $this->seed("ratings");
        $this->seed("roles");
        $this->seed("role_titles");
        $this->seed("training");
        $this->seed("transfers");
        $this->command->info("Done.");
    }

    public function seed($info, $table = null) {
        if ($table == null) $table = $info;
        $this->command->info("Seeding $info...");
        //\DB::unprepared(file_get_contents("database/seeds/$table.sql"));
        exec("mysql -u $this->user -p$this->pass -h $this->host $this->db < database/seeds/$table.sql");

    }
}
