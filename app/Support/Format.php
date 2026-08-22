<?php

namespace App\Support;

class Format
{
    public static function bytes(?int $bytes, int $precision = 2): string
    {
        $bytes = (int) $bytes;

        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        $power = min((int) floor(log($bytes, 1024)), count($units) - 1);

        return round($bytes / 1024 ** $power, $precision).' '.$units[$power];
    }

    public static function money(?int $amount): string
    {
        return number_format((int) $amount).' '.match (config('panel.currency')) {
            'IRT' => 'تومان',
            'IRR' => 'ریال',
            'USD' => 'دلار',
            default => (string) config('panel.currency'),
        };
    }

    /** تبدیل تاریخ میلادی به شمسی. */
    public static function jalali(?\DateTimeInterface $date, bool $withTime = false): string
    {
        if (! $date) {
            return '—';
        }

        $date = \Carbon\Carbon::instance($date)->setTimezone(config('app.timezone'));

        if (class_exists(\IntlDateFormatter::class)) {
            $formatter = new \IntlDateFormatter(
                'fa_IR@calendar=persian',
                \IntlDateFormatter::NONE,
                \IntlDateFormatter::NONE,
                config('app.timezone'),
                \IntlDateFormatter::TRADITIONAL,
                $withTime ? 'yyyy/MM/dd HH:mm' : 'yyyy/MM/dd',
            );

            return (string) $formatter->format($date);
        }

        [$jy, $jm, $jd] = self::toJalali(
            (int) $date->format('Y'),
            (int) $date->format('n'),
            (int) $date->format('j'),
        );

        $out = sprintf('%04d/%02d/%02d', $jy, $jm, $jd);

        return $withTime ? $out.' '.$date->format('H:i') : $out;
    }

    /**
     * الگوریتم استاندارد تبدیل تقویم میلادی به هجری شمسی.
     *
     * @return array{0: int, 1: int, 2: int}
     */
    private static function toJalali(int $gy, int $gm, int $gd): array
    {
        $offsets = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];

        $gy2 = $gm > 2 ? $gy + 1 : $gy;
        $days = 355666 + (365 * $gy) + (int) (($gy2 + 3) / 4) - (int) (($gy2 + 99) / 100)
            + (int) (($gy2 + 399) / 400) + $gd + $offsets[$gm - 1];

        $jy = -1595 + (33 * (int) ($days / 12053));
        $days %= 12053;

        $jy += 4 * (int) ($days / 1461);
        $days %= 1461;

        if ($days > 365) {
            $jy += (int) (($days - 1) / 365);
            $days = ($days - 1) % 365;
        }

        if ($days < 186) {
            $jm = 1 + (int) ($days / 31);
            $jd = 1 + ($days % 31);
        } else {
            $jm = 7 + (int) (($days - 186) / 30);
            $jd = 1 + (($days - 186) % 30);
        }

        return [$jy, $jm, $jd];
    }
}
