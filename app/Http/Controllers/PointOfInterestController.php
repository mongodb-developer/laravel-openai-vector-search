<?php

namespace App\Http\Controllers;

use App\Models\PointOfInterest;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use OpenAI\Laravel\Facades\OpenAI;

class PointOfInterestController extends Controller
{
    /**
     * Get a list of supported cities.
     *
     * @return JsonResponse
     */
    public function getSupportedCities(): JsonResponse
    {
        $cities = PointOfInterest::distinct('location.city')->get();
        
        return response()->json($cities);
    }

    /**
     * Generate an embedding for the given text using OpenAI's API.
     *
     * @param string $text
     * @return array
     */
    protected function generateEmbedding(string $text): array
    {
        $response = OpenAI::embeddings()->create([
            'model' => 'text-embedding-3-small',
            'input' => $text
        ]);
        return $response->embeddings[0]->embedding;
    }

    /**
     * Generate a possible trip plan using OpenAI's GPT model.
     *
     * @param array $cities
     * @param string $context
     * @param int $days
     * @return string
     */
    protected function generatePossibleTrip(array $cities, string $context, int $days): string
    {
        $result = OpenAI::chat()->create([
            'model' => 'gpt-4o-mini',
            'temperature' => 0,
            'response_format' => ['type' => 'json_object'],
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are a travel agent helping a customer plan a trip to a city. If it will be hard to visit that in the number of days Have a one day plan stating the problem. The customer will provide you with points of interest to visit in json.'
                ],
                [
                    'role' => 'system',
                    'content' => 'take this schema, for flights add orig_airport_code and dest_airport_code: 
    "tripPlan": {
      "destination": [{
        "city": "string",
        "country": "string"
                    }],
      "pointsOfInterest": [
        {
          "name": "string",
          "description": "string",
          "location": {
            "coordinates": [number, number]
          },
          "rating": number
        }
      ],
      // only in relevant direction
      "flights" : [ "src_airport_code": "string",
                "dest_airport_code": "string" 
      ],
      "itinerary": [
        {
          "day": number,
          "destination": "string",
          "activities": [
            {
              "time": "string",
              "activity": "string",
              "duration": "string",
              // if flight
               "src_airport_code": "string",
                dest_airport_code: "string" 
            }
          ]
        }
      ]
    }
 '
                ],
                [
                    'role' => 'user',
                    'content' => 'For cities: ' . json_encode($cities) . '| Take this POIs: ' . $context . ' and build a plan for the a trip of ' . $days . 'days. '
                ]
            ]
        ]);

        return $result->choices[0]->message->content;
    }

    /**
     * Get top points of interest for a given city.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getTopPointsForCity(Request $request): JsonResponse
    {
        $city = $request->query('city');

        if (!$city) {
            return response()->json(['error' => 'City parameter is required'], 400);
        }

        $points = DB::collection('points_of_interest')->whereRaw(['location.city' => $city])
            ->orderBy('rating', 'desc')
            ->project(['name' => 1, 'description' => 1, 'rating' => 1, 'location' => 1])
            ->limit(10)->get();

        return response()->json(['context' => $points]);
    }

    /**
     * Plan a trip based on given cities and number of days.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function planTrip(Request $request): JsonResponse
    {
        try {
            $cities = $request->input('cities');
            $days = $request->input('days');

            if (!$cities) {
                return response()->json(['error' => 'Cities parameter is required'], 400);
            }

            if (!$days) {
                return response()->json(['error' => 'Days parameter is required'], 400);
            }

            $points = DB::collection('points_of_interest')->whereIn('location.city', $cities)
                ->orderBy('rating', 'desc')
                ->project(['name' => 1, 'description' => 1, 'rating' => 1, 'location' => 1])
                ->limit(60)->get();

            if ($points->isEmpty()) {
                return response()->json(['error' => 'No points of interest found for the specified cities'], 404);
            }

            $ai_trip = $this->generatePossibleTrip($cities, $points, $days);
            $ai_trip = json_decode($ai_trip, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Failed to decode AI trip data');
            }

            $flights = [];
            foreach ($ai_trip['tripPlan']['flights'] as $flight) {
                $flightResults = DB::collection('air_routes')
                    ->where('src_airport', $flight['src_airport_code'])
                    ->where('dst_airport', $flight['dest_airport_code'])
                    ->get();
                
                $flights = array_merge($flights, $flightResults->toArray());
            }

            return response()->json([
                'context' => $points, 
                'suggestion' => $ai_trip, 
                'flights' => $flights
            ]);

        } catch (\Exception $e) {
            \Log::error('Error in planTrip: ' . $e->getMessage());
            return response()->json(['error' => 'An error occurred while planning the trip: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Search for points of interest by city using vector search.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function searchByCity(Request $request): JsonResponse
    {   
        $search = $request->query('city');
        if (!$search) {
            return response()->json(['error' => 'City parameter is required'], 400);
        }
        $city = str_replace('%20', ' ', $search);
        
        $embedding = $this->generateEmbedding($search);
        
        $points = DB::collection('points_of_interest')
            ->raw(function ($collection) use ($embedding) {
                return $collection->aggregate([
                    [
                        '$vectorSearch' => [
                            'index' => 'vector_index',
                            'path' => 'embedding',
                            'queryVector' => $embedding,
                            'numCandidates' => 20,
                            'limit' => 5
                        ],
                    ],
                    [
                        '$project' => [
                            'name' => 1,
                            'description' => 1,
                            'rating' => 1,
                            'location' => 1,
                            'score' => ['$meta' => 'vectorSearchScore']
                        ]
                    ]
                ]);
            })->toArray();

        return response()->json($points);
    }
}
