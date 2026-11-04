<?php

declare(strict_types=1);

// Train group and badge. The line decides first, because the category often holds an operator code (NVA, NVB, SBX).
class TrainBadge
{
    const LONG = 'long';
    const REGIONAL = 'regional';
    const SBAHN = 'sbahn';
    const BUS = 'bus';
    const TRAM = 'tram';
    const OTHER = 'other';

    // label => [group, pattern on line or category]
    private const RULES = [
        'ICE'  => [self::LONG, '/^ICE\b/i'],
        'IC'   => [self::LONG, '/^IC\b/i'],
        'EC'   => [self::LONG, '/^EC\b/i'],
        'RE'   => [self::REGIONAL, '/^RE(\d|\b)/i'],
        'RB'   => [self::REGIONAL, '/^RB(\d|\b)/i'],
        'S'    => [self::SBAHN, '/^S(\d|\b)/i'],
        'BUS'  => [self::BUS, '/^Bus\b/i'],
        'TRAM' => [self::TRAM, '/^(STR|Tram)\b/i'],
    ];

    // badge text => [text color, italic]
    private const STYLES = [
        'ICE'  => ['#e3000f', true],
        'IC'   => ['#e3000f', true],
        'EC'   => ['#e3000f', true],
        'RE'   => ['#5a5a5a', true],
        'RB'   => ['#5a5a5a', true],
        'BUS'  => ['#a3007c', false],
        'TRAM' => ['#c00', false],
    ];

    public static function group(string $line, string $category): string
    {
        $label = self::label($line, $category);
        return self::RULES[$label][0] ?? self::OTHER;
    }

    public static function label(string $line, string $category): string
    {
        foreach ([$line, $category] as $text) {
            foreach (self::RULES as $label => [, $pattern]) {
                if (preg_match($pattern, $text) === 1) {
                    return $label;
                }
            }
        }
        return '?';
    }

    // white sign 34×22 as in the old DB icons; S-Bahn as green circle
    public static function svg(string $label): string
    {
        $sign = '<rect x="0.5" y="0.5" width="33" height="21" rx="3" fill="#fff" stroke="#c8c8c8"/>';
        if ($label === 'S') {
            $content = '<circle cx="17" cy="11" r="8" fill="#008d4f"/>'
                . '<text x="17" y="15" text-anchor="middle" font-family="Arial,sans-serif" font-weight="bold" font-size="11" fill="#fff">S</text>';
        } else {
            [$color, $italic] = self::STYLES[$label] ?? ['#5a5a5a', false];
            $size = strlen($label) > 3 ? 9 : (strlen($label) > 2 ? 10 : 11);
            $content = '<text x="17" y="15.5" text-anchor="middle" font-family="Arial,sans-serif" font-weight="bold"'
                . ($italic ? ' font-style="italic"' : '') . ' font-size="' . $size . '" fill="' . $color . '">'
                . htmlspecialchars($label, ENT_QUOTES) . '</text>';
        }
        return '<svg xmlns="http://www.w3.org/2000/svg" width="34" height="22" viewBox="0 0 34 22" role="img" aria-label="'
            . htmlspecialchars($label, ENT_QUOTES) . '">' . $sign . $content . '</svg>';
    }
}
