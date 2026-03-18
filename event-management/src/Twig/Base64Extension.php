<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class Base64Extension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('base64_decode', [$this, 'base64Decode']),
            new TwigFilter('json_decode', [$this, 'jsonDecode']),
        ];
    }

    public function base64Decode(string $data): string
    {
        return base64_decode($data);
    }

    public function jsonDecode(string $data): array
    {
        return json_decode($data, true) ?? [];
    }
}