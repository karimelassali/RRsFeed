<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\RssFeedModel;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Model>
 */
class FeedFactory extends Factory
{
    protected $model = RssFeedModel::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'source' => $this->faker->url,
            'title' => $this->faker->sentence,
            'link' => $this->faker->url,
            'description' => $this->faker->paragraph,
            'pubDate' => $this->faker->dateTime,
        ];
    }
}
