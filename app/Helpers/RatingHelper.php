<?php
namespace App\Helpers;


use App\Rating;

class RatingHelper
{
    public static function intToShort(int $rating): ?string
    {
        $rating = Rating::find($rating);

        return $rating ? $rating->short : null;
    }

    public static function intToLong(int $rating): ?string
    {
        $rating = Rating::find($rating);

        return $rating ? $rating->long : null;
    }

    public static function shortToInt($short): ?int
    {
        $rating = Rating::where('short', $short)->first();

        return $rating ? $rating->id : null;
    }
}