<?php

declare(strict_types=1);

// Delay reasons of the DB Timetables API (messages with t="d"), from the code list of the API documentation.
// Quality messages (t="q", e.g. Wi-Fi, coach order) are not shown on a home board and are left out.
class MessageCodes
{
    private const TEXTS = [
        2  => 'Polizeiliche Ermittlung',
        3  => 'Feuerwehreinsatz an der Strecke',
        4  => 'Kurzfristiger Personalausfall',
        5  => 'Ärztliche Versorgung eines Fahrgastes',
        6  => 'Betätigen der Notbremse',
        7  => 'Personen im Gleis',
        8  => 'Notarzteinsatz am Gleis',
        9  => 'Streikauswirkungen',
        10 => 'Tiere im Gleis',
        11 => 'Unwetter',
        12 => 'Warten auf ein verspätetes Schiff',
        13 => 'Pass- und Zollkontrolle',
        14 => 'Technische Störung am Bahnhof',
        15 => 'Beeinträchtigung durch Vandalismus',
        16 => 'Entschärfung einer Fliegerbombe',
        17 => 'Beschädigung einer Brücke',
        18 => 'Umgestürzter Baum im Gleis',
        19 => 'Unfall an einem Bahnübergang',
        20 => 'Tiere im Gleis',
        21 => 'Warten auf Fahrgäste aus einem anderen Zug',
        22 => 'Witterungsbedingte Störung',
        23 => 'Feuerwehreinsatz auf Bahngelände',
        24 => 'Verspätung im Ausland',
        25 => 'Warten auf weitere Wagen',
        28 => 'Gegenstände im Gleis',
        29 => 'Ersatzverkehr mit Bus ist eingerichtet',
        31 => 'Bauarbeiten',
        32 => 'Verzögerung beim Ein- und Ausstieg',
        33 => 'Reparatur an der Oberleitung',
        34 => 'Reparatur an einem Signal',
        35 => 'Streckensperrung',
        36 => 'Reparatur am Zug',
        37 => 'Reparatur am Wagen',
        38 => 'Reparatur an der Strecke',
        39 => 'Anhängen von zusätzlichen Wagen',
        40 => 'Defektes Stellwerk',
        41 => 'Technische Störung an einem Bahnübergang',
        42 => 'Außerplanmäßige Geschwindigkeitsbeschränkung',
        43 => 'Verspätung eines vorausfahrenden Zuges',
        44 => 'Warten auf einen entgegenkommenden Zug',
        45 => 'Vorfahrt eines anderen Zuges',
        46 => 'Vorfahrt eines anderen Zuges',
        47 => 'Verspätete Bereitstellung des Zuges',
        48 => 'Verspätung aus vorheriger Fahrt',
        55 => 'Technische Störung an einem anderen Zug',
        56 => 'Warten auf Fahrgäste aus einem Bus',
        57 => 'Zusätzlicher Halt zum Ein- und Ausstieg',
        58 => 'Umleitung des Zuges',
        59 => 'Schnee und Eis',
        60 => 'Reduzierte Geschwindigkeit wegen Sturm',
        61 => 'Türstörung',
        62 => 'Behobene technische Störung am Zug',
        63 => 'Technische Untersuchung am Zug',
        64 => 'Weichenreparatur',
        65 => 'Erdrutsch',
        66 => 'Hochwasser',
        99 => 'Verzögerungen im Betriebsablauf',
    ];

    public static function text(int $code): ?string
    {
        return self::TEXTS[$code] ?? null;
    }
}
