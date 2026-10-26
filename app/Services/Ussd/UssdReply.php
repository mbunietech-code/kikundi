<?php

namespace App\Services\Ussd;

final class UssdReply
{
    private function __construct(public readonly string $text, public readonly bool $end) {}

    public static function con(string $text): self
    {
        return new self($text, false);
    }

    public static function end(string $text): self
    {
        return new self($text, true);
    }
}
