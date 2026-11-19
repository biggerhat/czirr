<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A staple the family always has at home. Grocery lists generated from the meal
 * plan skip ingredients that match one of these exactly (after normalising).
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string $name_key
 */
class PantryItem extends Model
{
    protected $fillable = ['name', 'name_key'];

    protected static function booted(): void
    {
        static::saving(fn (self $item) => $item->name_key = self::normalize($item->name));
    }

    /**
     * Lower-case, drop parenthetical notes and punctuation, collapse spaces and
     * fold a trailing plural so "Eggs" and "egg" or "Salt (kosher)" and "salt" match.
     * Deliberately NOT fuzzy: "sugar" must not hide "brown sugar".
     */
    public static function normalize(string $name): string
    {
        $n = mb_strtolower($name);
        $n = preg_replace('/\([^)]*\)/', ' ', $n) ?? $n;
        $n = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $n) ?? $n;
        $n = trim(preg_replace('/\s+/', ' ', $n) ?? $n);

        if (mb_strlen($n) > 3) {
            if (str_ends_with($n, 'ies')) {
                $n = mb_substr($n, 0, -3).'y';
            } elseif (str_ends_with($n, 'oes')) {
                $n = mb_substr($n, 0, -2);
            } elseif (str_ends_with($n, 's') && ! str_ends_with($n, 'ss')) {
                $n = mb_substr($n, 0, -1);
            }
        }

        return $n;
    }
}
