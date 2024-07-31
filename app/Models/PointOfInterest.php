<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class PointOfInterest extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'points_of_interest';

    protected $fillable = [
        'name',
        'description',
        'type',
        'rating',
        'location',
        'embedding',
    ];

    protected $casts = [
        'location' => 'object',
        'embedding' => 'array',
    ];
}