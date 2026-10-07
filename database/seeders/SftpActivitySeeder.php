<?php

namespace Database\Seeders;

use App\Models\SftpActivity;
use Illuminate\Database\Seeder;

class SftpActivitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        SftpActivity::factory()->count(3)->create();
    }
}
