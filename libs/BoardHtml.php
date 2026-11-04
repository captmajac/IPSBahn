<?php

declare(strict_types=1);

// Board as HTML box for the old WebFront (dark style as the old script).
// Texts are German on purpose: the HTML box is stored as a value and is not translated by Symcon.
class BoardHtml
{
    const GREEN = '#43A047';
    const ORANGE = '#FB8C00';
    const RED = '#E53935';
    // row backgrounds for trains that cannot or can only just be reached
    const MISSED_BG = '#4a2c2c';
    const TIGHT_BG = '#4a3b26';
    // small info sign before a delayed time
    const INFO = "<svg width='12' height='12' viewBox='0 0 12 12' style='vertical-align:-1px;margin-right:4px;'><circle cx='6' cy='6' r='5.25' fill='none' stroke='#E53935' stroke-width='1.5'/><rect x='5.25' y='5' width='1.5' height='4' fill='#E53935'/><circle cx='6' cy='3.25' r='0.9' fill='#E53935'/></svg>";

    private const NOTICES = [
        'failed'     => 'Aktualisierung fehlgeschlagen',
        'noRealtime' => 'ohne Echtzeitdaten',
        'stale'      => 'Daten veraltet',
    ];

    private const REACH = [
        'ok'     => ['erreichbar', self::GREEN],
        'tight'  => ['knapp', self::ORANGE],
        'missed' => ['nicht erreichbar', self::RED],
    ];

    // meta: board 'dep'|'arr', fetchedAt (0 = never), notice ''|'failed'|'noRealtime'|'stale'|'notConfigured', walk (minutes)
    public static function render(array $rows, array $meta): string
    {
        $html = "<div style='font-family:Arial,sans-serif;font-size:13px;background:#2b2b2b;color:#e0e0e0;border-radius:5px;overflow:hidden;'>";
        if ($meta['notice'] === 'notConfigured') {
            return $html . self::message('Bitte Bahnhof und Zugangsdaten eintragen') . '</div>';
        }
        $html .= "<table width='100%' cellspacing='0' cellpadding='0' style='border-collapse:collapse;'>";
        $html .= "<tr style='background:#3a3a3a;color:#ffffff;'>";
        foreach (['Typ', 'Zug', 'Zeit', 'Status', $meta['board'] === 'arr' ? 'Von' : 'Richtung', 'Gleis'] as $title) {
            $html .= "<th style='padding:10px;text-align:left;border-bottom:1px solid #444;'>$title</th>";
        }
        $html .= '</tr>';
        if ($rows === []) {
            $html .= "<tr><td colspan='6'>" . self::message('Keine passenden Züge in den nächsten 2 Stunden') . '</td></tr>';
        }
        foreach ($rows as $i => $row) {
            $html .= self::row($row, $i, $meta);
        }
        $html .= '</table>' . self::footer($meta) . '</div>';
        return $html;
    }

    private static function row(array $row, int $i, array $meta): string
    {
        $background = ['missed' => self::MISSED_BG, 'tight' => self::TIGHT_BG][$row['reach']] ?? ($i % 2 === 0 ? '#333333' : '#2b2b2b');
        $style = 'background:' . $background . ';' . ($row['cancelled'] || $row['offRoute'] ? 'opacity:0.5;' : '');
        $cell = "<td style='padding:10px;vertical-align:middle;border-bottom:1px solid #444;'>";
        $white = fn (string $text, bool $red) => "<b style='color:" . ($red ? self::RED : '#ffffff') . ";'>" . self::e($text) . '</b>';
        $small = fn (string $text) => "<br/><span style='font-size:11px;color:#a0a0a0;'>" . self::e($text) . '</span>';

        $html = "<tr style='$style'>";
        $html .= $cell . TrainBadge::svg($row['badge']) . '</td>';
        $html .= $cell . $white($row['line'], false) . ($row['operator'] !== '' ? $small($row['operator']) : '') . '</td>';
        $html .= $cell . ($row['delayed'] ? self::INFO : '') . $white(self::time('H:i', $row['time']), $row['delayed']) . $small('in ' . $row['minutesLeft'] . ' min') . '</td>';
        $html .= $cell . self::status($row, $meta) . '</td>';
        $html .= $cell . self::e($row['direction'])
            . ($row['continuesTo'] !== '' ? " <span style='font-size:11px;color:#a0a0a0;'>weiter nach " . self::e($row['continuesTo']) . '</span>' : '') . '</td>';
        $html .= $cell . $white($row['platform'] !== '' ? $row['platform'] : '-', $row['platformChanged']) . '</td>';
        $html .= '</tr>';
        foreach ($row['messages'] as $message) {
            // under the station column, where the reader looks for the train
            $html .= "<tr style='$style'><td colspan='4' style='border-bottom:1px solid #444;'></td>"
                . "<td colspan='2' style='padding:0 10px 8px 10px;color:" . self::ORANGE . ";font-size:12px;border-bottom:1px solid #444;'>"
                . self::e($message) . '</td></tr>';
        }
        return $html;
    }

    // departure with walk time: reachability; otherwise on time or nothing (the red time shows the delay)
    private static function status(array $row, array $meta): string
    {
        if ($row['cancelled']) {
            return self::colored('fällt aus', self::RED);
        }
        if ($meta['board'] === 'dep' && $meta['walk'] > 0) {
            return isset(self::REACH[$row['reach']]) ? self::colored(...self::REACH[$row['reach']]) : '';
        }
        return $row['delayed'] ? '' : self::colored('pünktlich', self::GREEN);
    }

    private static function footer(array $meta): string
    {
        $parts = [];
        if ($meta['fetchedAt'] > 0) {
            $parts[] = 'Stand: ' . self::time('d.m.Y H:i', $meta['fetchedAt']);
        }
        if (isset(self::NOTICES[$meta['notice']])) {
            $parts[] = self::NOTICES[$meta['notice']];
        }
        return "<div style='font-size:11px;color:#a0a0a0;text-align:center;padding:10px;border-top:1px solid #444;'>" . implode(' · ', $parts) . '</div>';
    }

    private static function message(string $text): string
    {
        return "<div style='padding:15px;text-align:center;color:#a0a0a0;'>" . self::e($text) . '</div>';
    }

    private static function colored(string $text, string $color): string
    {
        return "<span style='color:$color;font-weight:bold;'>" . self::e($text) . '</span>';
    }

    // German time like the API, whatever the server's default timezone is
    private static function time(string $format, int $timestamp): string
    {
        return (new DateTime('@' . $timestamp))->setTimezone(new DateTimeZone(TimetablesClient::TIMEZONE))->format($format);
    }

    private static function e(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES);
    }
}
