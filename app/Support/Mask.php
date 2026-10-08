<?php

namespace App\Support;

class Mask
{
    public static function email(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return mb_substr($local, 0, 3) . '***@' . $domain;
    }

    public static function phone(string $phone): string
    {
        return substr($phone, 0, 6) . str_repeat('*', max(0, strlen($phone) - 10)) . substr($phone, -4);
    }

    public static function destination(string $channel, string $destination): string
    {
        return $channel === 'email' ? self::email($destination) : self::phone($destination);
    }
}
