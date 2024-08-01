<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

To leverage MongoDB with Laravel see the following [resources](https://www.mongodb.com/resources/products/compatibilities/mongodb-laravel-integration)

## Tour Planner with MongoDB Vector Search

This project demonstrates the use of MongoDB Vector Search in a Laravel backend with a Vue.js frontend to create an intelligent tour planning application. It showcases how to leverage vector search capabilities for enhanced search and recommendation features in travel applications.

### Project Structure

- `/tour-planner`: Laravel backend
- `/tour-planner/frontend/tour-planner-frontend`: Vue.js frontend


### Features

- City search with vector-based similarity
- Points of interest recommendation
- Intelligent trip planning using OpenAI integration

## Vector Search and Laravel sdk
The points of interest controller search cities by embeddings generated on city - attraction - desc concat, and gets the searched term as $search
```php
$embedding = $this->generateEmbedding($search);
    
    // Use Atlas Search to search for points of interest within a city
    $points = DB::collection('points_of_interest')
    ->raw(
        function ( $collection) use ($embedding) {
            
            return $collection->aggregate([
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
```
## Retrieval Augmented Generation and Laravel with OpenAI

Using the relevant context from MongoDB to augment the trip planning JSON response

```php
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
```

## Prerequisites

- PHP 8.1+
    - MongoDB Extension and prereqisites.
- Composer
- Node.js and npm
- MongoDB Atlas cluster
- OpenAI API key

## Backend Setup (Laravel)

Navigate to the cloned directory:

```
cd laravel-openai-vector-search
```
Install PHP dependencies:
```
composer install
```

Copy the `.env.example` file to `.env` and configure your environment variables:
```
OPENAI_API_KEY=your_openai_api_key
DB_URI=your_atlas_uri
```

Generate an application key:
```
php artisan key:generate
```

Run database migrations and seeders (if any):
```
php artisan migrate --seed
```

Create Atlas vector search index on database: `trip_planner` collection: `points_of_interest`:

** Index name : vector_index **
```
{
  "fields": [
    {
      "type": "vector",
      "path": "embedding",
      "numDimensions": 1536,
      "similarity": "cosine"
    }
  ]
}
```

Start the Laravel development server:
```
php artisan serve
```

## Frontend Setup (Vue.js)

Navigate to the frontend directory:
```
cd frontend/trip-planner-frontend
npm install
npm run serve
```

The application should be visible at http://localhost:8080 .

## Disclaimer

**Not a MongoDB official product. Use at your own risk**



