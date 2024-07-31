<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AirRoutesSeeder extends Seeder
{
    public function run()
    {
        $files = [
            'air_routes_1.json',
            'air_routes_2.json',
            'air_routes_3.json',
            'air_routes_4.json'
        ];

        $totalInserted = 0;

        foreach ($files as $file) {
            $this->command->info("Processing file: $file");

            $jsonFile = storage_path("app/$file");

            if (!file_exists($jsonFile)) {
                $this->command->error("File not found: $jsonFile");
                continue;
            }

            try {
                $data = json_decode(file_get_contents($jsonFile), true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new \Exception("JSON decode error: " . json_last_error_msg());
                }

                $chunkSize = 1000; // Adjust based on your system's capabilities
                foreach (array_chunk($data, $chunkSize) as $chunk) {
                    DB::collection('air_routes')->insert($chunk);
                    $totalInserted += count($chunk);
                    $this->command->info("Inserted " . count($chunk) . " records from $file");
                }

                $this->command->info("Completed processing $file");
            } catch (\Exception $e) {
                $this->command->error("Error processing $file: " . $e->getMessage());
            }
        }

        $this->command->info("Seeding completed. Total records inserted: $totalInserted");
    }
}