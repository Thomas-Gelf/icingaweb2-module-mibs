<?php

namespace Icinga\Module\Mibs;

class Formatting
{
    public static function stringCleanup(?string $string): ?string
    {
        if ($string === null) {
            return null;
        }

        $string = preg_replace('/^"/s', '', $string);
        $string = preg_replace('/"$/s', '', $string);
        // TOD: check for initial indentation, strip only that many spaces
        return preg_replace('/^ {16}/m', '    ', $string);
    }

    public static function isValidOid($oid)
    {
        return preg_match('/^[\d.]+$/', $oid);
    }
}
