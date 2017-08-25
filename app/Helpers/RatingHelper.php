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


class RatingHelper {
    public static $ratings = [
        "", "OBS", "S1", "S2", "S3", "C1", "C2", "C3", "I1", "I2", "I3", "SUP", "ADM"
    ];
    public static $rating_titles = [
        "",
        "Observer",
        "Student 1",
        "Student 2",
        "Student 3",
        "Controller",
        "not used",
        "Senior Controller",
        "Instructor",
        "not used",
        "Senior Instructor",
        "Supervisor",
        "Administrator"
    ];

    public static function intToShort($rating) {
        if (isset($ratings[$rating])) { return static::$ratings[$rating]; }
        else { return false; }
    }

    public static function intToLong($rating) {
        if (isset($rating_titles[$rating])) { return static::$rating_titles[$rating]; }
        else { return false; }
    }

    public static function shortToInt($short) {
        foreach (static::$ratings as $key => $value) {
            if ($short == $value) { return $key; }
        }

        return false;
    }
}