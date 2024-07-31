<?php

namespace App\Http\Controllers;

use App\Models\PointOfInterest;
use DB;
use Illuminate\Http\Request;
use OpenAI\Laravel\Facades\OpenAI;


class PointOfInterestController extends Controller
{
    public function getSupportedCities()
    {
        $cities = PointOfInterest::distinct('location.city')->get();
        
        return response()->json($cities);
    }

    protected function generateEmbedding($text)
{
        $response = OpenAI::embeddings()->create([
            'model' => 'text-embedding-3-small',
            'input' => $text
        ]);
        return $response->embeddings[0]->embedding;
    
}

    protected function generatePossibleTrip($cities,$context, $days){
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
              ...
          ]

        }
          ...
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
    public function getTopPointsForCity(Request $request)
    {
        $city = $request->query('city');

        if (!$city) {
            return response()->json(['error' => 'City parameter is required'], 400);
        }

        $points = DB::collection('points_of_interest')->whereRaw(['location.city' => $city])
            ->orderBy('rating', 'desc')
            ->project(['name' => 1, 'description' => 1, 'rating' => 1, 'location' => 1])
           
            ->limit(10)->get();

        
            // $points = $points->transform(function ($point) {
            //     if (empty($point['embedding'])) {
            //         $embeddingText = $point['location']['city'] . ' ' . $point['name'] . ' ' . $point['description'];
            //         $embedding = $this->generateEmbedding($embeddingText);
                    
            //         // Update the database
            //         DB::collection('points_of_interest')->where('_id', $point['_id'])->update(['embedding' => $embedding]);
                    
            //     }
            //     return $point;
            // });

    
        

        return response()->json(['context' => $points]);
       //'suggestion' => $ai_trip ]);
    

    }


    public function planTrip(Request $request)
{
    try {
        // Retrieve from request body
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

        $ai_trip = $this->generatePossibleTrip($cities,$points, $days);
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
    public function searchByCity(Request $request)
{   
    $city = $request->query('city');
    if (!$city) {
        return response()->json(['error' => 'City parameter is required'], 400);
    }
    // parse url encoding to spaces
    $city = str_replace('%20', ' ', $city);
    
    $embedding = $this->generateEmbedding($city);
    
    // Use Atlas Search to search for points of interest within a city
    $points = DB::collection('points_of_interest')
    ->raw(
        function ( $collection) use ($embedding) {
            
            return $collection->aggregate([
                    // [
                    //     '$search' => [
                    //         'index' => 'default',
                    //         'regex' => [
                    //             'path' => 'location.city',
                    //             'query' => ".*{$city}.*"
                    //         ]
                    //     ]
                    // ],
                    [
                        '$vectorSearch' => [
                            'index' => 'vector_index',
                            'path' => 'embedding',
                            'queryVector' => $embedding,
                            'numCandidates' => 20,
                            'limit' => 5
                            ],
                        ]
                    ,
                    ['$project' => [
                        'name' => 1,
                        'description' => 1,
                        'rating' => 1,
                        'location' => 1,
                        'score' => ['$meta' => 'vectorSearchScore']
                    ]]
                ]
                );
        },
    )->toArray();

    
    return response()->json($points);
   
}



}