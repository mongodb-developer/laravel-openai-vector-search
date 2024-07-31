<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use OpenAI\Laravel\Facades\OpenAI;

class PointsOfInterestSeeder extends Seeder
{
    public function run()
    {
        $jsonFile = storage_path('app/points_of_interest.json');
        $data = json_decode(file_get_contents($jsonFile), true);

        foreach ($data as &$poi) {
            $embeddingText = ' ' . $poi['location']['city'] . ' ' . $poi['name'] . ' ' . $poi['description'] ;
            $embedding = $this->generateEmbedding($embeddingText);
            $poi['embedding'] = $embedding;

            // To avoid rate limiting, add a small delay between API calls
            usleep(100000); // 100ms delay
        }

        DB::collection('points_of_interest')->insert($data);
    }

    private function generateEmbedding($text)
    {
        $response = OpenAI::embeddings()->create([
            'model' => 'text-embedding-3-small',
            'input' => $text,
        ]);

        return $response->embeddings[0]->embedding;
    }
}