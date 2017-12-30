<?php

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $this->command->info("Seeding tables...");
        $this->seed("controllers");
        $this->seed("exams");
        $this->seed("facilities");
        $this->seed("knowledgebase", "kb_cats");
        $this->seed("knowledgebase FAQ", "kb_qs");
        $this->seed("promotions");
        $this->seed("roles");
        $this->seed("role_titles");
        $this->seed("training");
        $this->seed("transfers");
        $this->command->info("Done.");
    }

    public function seed($info, $table = null) {
        if ($table == null) $table = $info;
        $this->command->info("Seeding $info...");
        \DB::unprepared(file_get_contents("database/seeds/$table.sql"));
    }
}
