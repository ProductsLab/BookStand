<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Book extends Model
{
    protected $fillable = [
        'isbn',
        'isbn_10',
        'title',
        'subtitle',
        'content',
        'contributor',
        'imprint',
        'publisher',
        'image_url',
        'price',
        'published_date',
        'audience_type',
        'audience_code',
        'c_code',
        'subject_text',
        'amazon_url',
        'honto_url',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'published_date' => 'date',
            'audience_type' => 'integer',
            'audience_code' => 'integer',
        ];
    }
}
