<?php

namespace App\Support;

class Phone
{
    /** "812-3456-7890", "0812...", "+62 812..." => "+6281234567890" */
    public static function normalize(string $raw): string
    {
        $d = preg_replace('/\D+/', '', $raw);
        if ($d === '') {
            return $raw;
        }
        if (str_starts_with($d, '620')) {
            $d = '62' . substr($d, 3);
        } elseif (str_starts_with($d, '62')) {
            // sudah benar
        } elseif (str_starts_with($d, '0')) {
            $d = '62' . substr($d, 1);
        } elseif (str_starts_with($d, '8')) {
            $d = '62' . $d;
        }

        return '+' . $d;
    }
}
