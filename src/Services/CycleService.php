<?php

namespace Services;

use DateTime;
use DateInterval;
use Helpers\EncryptionHelper; // For decrypting user data if passed directly

class CycleService {
    private $cycleInfo;

    // Default average lengths if not provided by user
    const DEFAULT_AVG_PERIOD_LENGTH = 5;
    const DEFAULT_AVG_CYCLE_LENGTH = 28;

    /**
     * Constructor can optionally take decrypted cycleInfo.
     * @param array|null $decryptedCycleInfo Decrypted cycle information from the user's record.
     *                                      Expected keys: 'period_start_dates' (array),
     *                                                     'average_period_length' (int, optional),
     *                                                     'average_cycle_length' (int, optional).
     */
    public function __construct(?array $decryptedCycleInfo = null) {
        $this->cycleInfo = $decryptedCycleInfo;
    }

    /**
     * Sets the cycle information for calculations.
     * @param array $decryptedCycleInfo
     */
    public function setCycleInfo(array $decryptedCycleInfo): void {
        $this->cycleInfo = $decryptedCycleInfo;
    }

    private function getAveragePeriodLength(): int {
        return $this->cycleInfo['average_period_length'] ?? self::DEFAULT_AVG_PERIOD_LENGTH;
    }

    private function getAverageCycleLength(): int {
        return $this->cycleInfo['average_cycle_length'] ?? self::DEFAULT_AVG_CYCLE_LENGTH;
    }

    /**
     * Gets the most recent period start date.
     * @return DateTime|null
     */
    private function getMostRecentPeriodStartDate(): ?DateTime {
        if (empty($this->cycleInfo['period_start_dates'])) {
            return null;
        }
        // Assumes period_start_dates are sorted descending (most recent first)
        try {
            return new DateTime($this->cycleInfo['period_start_dates'][0]);
        } catch (\Exception $e) {
            error_log("Error creating DateTime from period_start_dates: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Calculates the current day of the menstrual cycle.
     * Day 1 is the first day of the last period.
     * Returns null if no period start date is available.
     * @return int|null
     */
    public function getCurrentCycleDay(): ?int {
        $lastPeriodStartDate = $this->getMostRecentPeriodStartDate();
        if (!$lastPeriodStartDate) {
            return null;
        }

        $today = new DateTime('today');
        // If today is before the last period start date (e.g. data entry error), return null or handle as error
        if ($today < $lastPeriodStartDate) {
            return null;
        }

        $interval = $lastPeriodStartDate->diff($today);
        return $interval->days + 1; // Day 1 is the start day itself
    }

    /**
     * Estimates the current phase of the menstrual cycle.
     * Phases: 'menstruation', 'follicular', 'ovulation', 'luteal', 'unknown'.
     * @return string
     */
    public functiongetCurrentCyclePhase(): string {
        $currentCycleDay = $this->getCurrentCycleDay();
        if ($currentCycleDay === null) {
            return 'unknown';
        }

        $avgPeriodLength = $this->getAveragePeriodLength();
        $avgCycleLength = $this->getAverageCycleLength();

        // Ovulation typically occurs around 14 days BEFORE the next cycle starts.
        // Or, for a simpler model, mid-cycle, but the former is more accurate for luteal phase length.
        $estimatedOvulationDay = $avgCycleLength - 14;
        if ($estimatedOvulationDay <= 0) $estimatedOvulationDay = floor($avgCycleLength / 2); // Fallback for very short cycles

        if ($currentCycleDay <= $avgPeriodLength) {
            return 'menstruation'; // Period
        } elseif ($currentCycleDay < $estimatedOvulationDay - 2) { // -2 days before ovulation window
            return 'follicular'; // Follicular phase (post-period, pre-ovulation)
        } elseif ($currentCycleDay >= $estimatedOvulationDay - 2 && $currentCycleDay <= $estimatedOvulationDay + 2) {
            // Ovulation window (e.g., 5 days: ovulation day, 2 days before, 2 days after)
            // A common fertile window is considered ~6 days (5 days before ovulation + day of ovulation)
            // Let's define ovulation phase as a shorter period around the estimated day.
            return 'ovulation';
        } elseif ($currentCycleDay <= $avgCycleLength) {
            return 'luteal'; // Luteal phase (post-ovulation, pre-period)
        } else {
            // This means currentCycleDay is beyond the average cycle length,
            // implying a new cycle should have started or is late.
            // Could also be 'luteal' or 'pre-menstrual' depending on definition.
            // For now, if it's past the average, it might be heading to next menstruation or unknown.
            return 'luteal'; // Or 'unknown' if we want to be strict past avg cycle length
        }
    }

    /**
     * Estimates the start date of the next period.
     * Returns null if no period start date is available.
     * @return DateTime|null
     */
    public function getEstimatedNextPeriodStartDate(): ?DateTime {
        $lastPeriodStartDate = $this->getMostRecentPeriodStartDate();
        if (!$lastPeriodStartDate) {
            return null;
        }

        $avgCycleLength = $this->getAverageCycleLength();
        $nextPeriodDate = clone $lastPeriodStartDate;
        $nextPeriodDate->add(new DateInterval("P{$avgCycleLength}D"));

        // If the estimated next period is in the past relative to today (meaning user is overdue and hasn't logged)
        // then calculate the one after that.
        $today = new DateTime('today');
        while ($nextPeriodDate < $today) {
            $nextPeriodDate->add(new DateInterval("P{$avgCycleLength}D"));
        }

        return $nextPeriodDate;
    }

    /**
     * Estimates the approximate ovulation window (start and end dates).
     * The fertile window is typically about 5-6 days ending on the day of ovulation.
     * Ovulation is ~14 days before the next period.
     * @return array|null ['start' => DateTime, 'end' => DateTime] or null
     */
    public function getEstimatedOvulationWindow(): ?array {
        $nextPeriodStartDate = $this->getEstimatedNextPeriodStartDate();
        if (!$nextPeriodStartDate) {
            return null;
        }

        // Estimated ovulation day is typically 14 days before the start of the next period.
        $estimatedOvulationDate = clone $nextPeriodStartDate;
        $estimatedOvulationDate->sub(new DateInterval('P14D'));

        // Fertile window can be considered ~5 days before ovulation + day of ovulation.
        // For simplicity, let's define a window around the estimated ovulation day.
        // e.g., Ovulation day +/- 2 days, or Ovulation day -4 to Ovulation day +1
        $windowStart = clone $estimatedOvulationDate;
        $windowStart->sub(new DateInterval('P3D')); // Start of fertile window (approx)

        $windowEnd = clone $estimatedOvulationDate;
        $windowEnd->add(new DateInterval('P2D')); // End of fertile window (approx)

        // Ensure window start is not before the most recent period ended (approx)
        $lastPeriodStartDate = $this->getMostRecentPeriodStartDate();
        if ($lastPeriodStartDate) {
            $lastPeriodEndDate = clone $lastPeriodStartDate;
            $lastPeriodEndDate->add(new DateInterval('P' . ($this->getAveragePeriodLength() -1) . 'D'));
            if ($windowStart < $lastPeriodEndDate) {
                $windowStart = clone $lastPeriodEndDate;
                $windowStart->add(new DateInterval('P1D')); // Start fertile window day after period ends
            }
        }


        return [
            'start_date' => $windowStart,
            'ovulation_date_estimated' => $estimatedOvulationDate,
            'end_date' => $windowEnd,
        ];
    }

    /**
     * Get number of days until next estimated period.
     * @return int|null Null if cannot estimate, 0 if today is estimated start. Negative if overdue.
     */
    public function getDaysUntilNextPeriod(): ?int {
        $nextPeriodStartDate = $this->getEstimatedNextPeriodStartDate();
        if (!$nextPeriodStartDate) {
            return null;
        }
        $today = new DateTime('today');
        $interval = $today->diff($nextPeriodStartDate);

        // $interval->days is always positive. Use $interval->invert to check direction.
        $days = $interval->days;
        if ($interval->invert) { // Date is in the past
            return -$days;
        }
        return $days;
    }
}

?>
