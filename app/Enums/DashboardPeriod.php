<?php

namespace App\Enums;

use Carbon\CarbonImmutable;

/**
 * The admin dashboard's reporting periods. Each is a half-open window
 * [from, to) ending "now", in the app timezone (Asia/Tokyo), so "Today"
 * and "QTD" start at JST midnight.
 *
 * The comparison window is the same window shifted back by one period --
 * like-for-like: Today vs yesterday up to the same time of day, 7D vs the
 * 7 days before it, QTD vs the same stretch of the previous quarter, YTD
 * vs the same stretch of last year.
 */
enum DashboardPeriod: string
{
    case Today = 'today';
    case SevenDays = '7d';
    case ThirtyDays = '30d';
    case QuarterToDate = 'qtd';
    case YearToDate = 'ytd';

    public const DEFAULT = self::ThirtyDays;

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable} [from, to)
     */
    public function range(CarbonImmutable $now): array
    {
        $from = match ($this) {
            self::Today => $now->startOfDay(),
            // Calendar days including today.
            self::SevenDays => $now->startOfDay()->subDays(6),
            self::ThirtyDays => $now->startOfDay()->subDays(29),
            self::QuarterToDate => $now->startOfQuarter(),
            self::YearToDate => $now->startOfYear(),
        };

        return [$from, $now];
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable} [from, to)
     */
    public function previousRange(CarbonImmutable $now): array
    {
        [$from, $to] = $this->range($now);

        return [$this->shiftBack($from), $this->shiftBack($to)];
    }

    private function shiftBack(CarbonImmutable $moment): CarbonImmutable
    {
        return match ($this) {
            self::Today => $moment->subDay(),
            self::SevenDays => $moment->subDays(7),
            self::ThirtyDays => $moment->subDays(30),
            self::QuarterToDate => $moment->subQuarterNoOverflow(),
            self::YearToDate => $moment->subYearNoOverflow(),
        };
    }
}
