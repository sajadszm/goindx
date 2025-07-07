<?php

namespace Helpers;

class DateHelper {

    /**
     * Generates inline keyboard buttons for selecting a year.
     * @param int $currentSelectedYear Optional.
     * @return array
     */
    public static function getYearSelector(int $currentSelectedYear = null): array {
        $currentYear = (int)date('Y');
        $years = [];
        // Show current year, last year, and next year (if applicable for future entries, though less common for period logging)
        // For period logging, mostly current and past.
        for ($i = 0; $i < 3; $i++) { // Show 3 recent years
            $year = $currentYear - $i;
            $years[] = ['text' => (string)$year, 'callback_data' => 'cycle_select_year:' . $year];
        }
        // Can add a "More" button if more years are needed
        return array_chunk($years, 3); // 3 years per row
    }

    /**
     * Generates inline keyboard buttons for selecting a month.
     * @param int $year The selected year.
     * @param int $currentSelectedMonth Optional.
     * @return array
     */
    public static function getMonthSelector(int $year, int $currentSelectedMonth = null): array {
        $months = [];
        $persianMonths = [
            1 => 'فروردین', 2 => 'اردیبهشت', 3 => 'خرداد',
            4 => 'تیر', 5 => 'مرداد', 6 => 'شهریور',
            7 => 'مهر', 8 => 'آبان', 9 => 'آذر',
            10 => 'دی', 11 => 'بهمن', 12 => 'اسفند'
        ];
        for ($i = 1; $i <= 12; $i++) {
            $months[] = ['text' => $persianMonths[$i], 'callback_data' => "cycle_select_month:{$year}:{$i}"];
        }
        return array_chunk($months, 3); // 3 months per row
    }

    /**
     * Generates inline keyboard buttons for selecting a day.
     * @param int $year The selected year.
     * @param int $month The selected month.
     * @param int $currentSelectedDay Optional.
     * @return array
     */
    public static function getDaySelector(int $year, int $month, int $currentSelectedDay = null): array {
        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
        // Note: This uses Gregorian calendar days. For Persian calendar, a Jalali converter would be needed.
        // For simplicity, we'll stick to Gregorian for now, assuming users understand this.
        // Or, we can assume the input is for the current locale's understanding of month/day.
        $days = [];
        for ($i = 1; $i <= $daysInMonth; $i++) {
            $days[] = ['text' => (string)$i, 'callback_data' => "cycle_select_day:{$year}:{$month}:{$i}"];
        }
        return array_chunk($days, 7); // 7 days per row, like a mini-calendar week
    }

    /**
     * Validates a date.
     * @param int $year
     * @param int $month
     * @param int $day
     * @return bool
     */
    public static function isValidDate(int $year, int $month, int $day): bool {
        if ($month < 1 || $month > 12 || $day < 1) {
            return false;
        }
        return checkdate($month, $day, $year);
    }
}
?>
