<?php

namespace Database\Factories;

use App\Models\Book;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Book>
 */
class BookFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'isbn' => $this->faker->unique()->isbn13(),
            'isbn_10' => $this->faker->isbn10(),
            'title' => 'ダミー書籍：' . $this->faker->realText(20),
            'subtitle' => $this->faker->boolean(50) ? 'サブタイトル：' . $this->faker->realText(30) : null,
            'content' => $this->faker->realText(200),
            'contributor' => $this->faker->name() . ' 著',
            'imprint' => $this->faker->company() . '文庫',
            'publisher' => $this->faker->company() . '出版',
            'image_url' => $this->faker->imageUrl(),
            'price' => $this->faker->numberBetween(500, 5000),
            'published_date' => $this->faker->dateTimeBetween('-5 years', 'now')->format('Y-m-d'),
            'audience_type' => $this->faker->numberBetween(1, 10),
            'audience_code' => $this->faker->numberBetween(1, 10),
            'c_code' => $this->faker->numerify('C###'),
            'subject_text' => $this->faker->word(),
            'amazon_url' => 'https://amazon.co.jp/dp/' . $this->faker->lexify('??????????'),
            'honto_url' => 'https://honto.jp/netstore/pd-book_' . $this->faker->numerify('########') . '.html',
        ];
    }
}
