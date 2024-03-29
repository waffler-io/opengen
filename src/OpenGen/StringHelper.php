<?php

/*
 * This file is part of Waffler.
 *
 * (c) Erick Johnson Almeida de Menezes <erickmenezes.dev@gmail.com>
 *
 * This source file is subject to the MIT licence that is bundled
 * with this source code in the file LICENCE.
 */

namespace Waffler\OpenGen;

/**
 * Class StringHelper.
 *
 * @author ErickJMenezes <erickmenezes.dev@gmail.com>
 */
class StringHelper
{
    /**
     * @var array<string, string>
     */
    private static array $camelCache = [];

    public static function studly(string $value): string
    {
        return ucfirst(self::camelCase($value));
    }

    public static function camelCase(string $value): string
    {
        if (array_key_exists($value, self::$camelCache)) {
            return self::$camelCache[$value];
        }

        $separators = ['-', '_', '/', '\\'];
        $pieces = explode(
            ' ',
            str_replace(
                $separators,
                ' ',
                self::contains($value, $separators) ? strtolower($value) : $value,
            )
        );

        $newPieces = [];
        foreach ($pieces as $piece) {
            $newPieces[] = str_replace([',', ';', '#', '@', '$'], '', ucfirst($piece));
        }

        return self::$camelCache[$value] = lcfirst(implode('', $newPieces));
    }

    public static function contains($haystack, $needles, $ignoreCase = false)
    {
        if ($ignoreCase) {
            $haystack = mb_strtolower($haystack);
            $needles = array_map('mb_strtolower', (array) $needles);
        }

        foreach ((array) $needles as $needle) {
            if ($needle !== '' && str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }
}
