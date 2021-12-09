<?php
/**
 * MIT License
 *
 * Copyright (c) 2017 Daniel A. Hawton (daniel@hawton.org)
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

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