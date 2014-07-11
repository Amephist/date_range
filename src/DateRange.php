<?php
namespace Grubie\Libs;

use \DateTimeImmutable;
use \BadFunctionCallException;
use \DatePeriod;
use \DateInterval;

class DateRange
{
    protected $start_date;
    protected $end_date;

    /**
     * @param  string|DateTimeImmutable $start_date
     * @param  string|DateTimeImmutable $end_date
     * @returns DateRange
     * @throws BadFunctionCallException
     */

    public function __construct($start_date, $end_date)
    {
        if ($start_date > $end_date) {
            throw new BadFunctionCallException('start_date should be lower than end_date');
        }
        if (is_string($start_date)) {
            $this->start_date = new DateTimeImmutable($start_date);
        } else {
            $this->start_date = $start_date;
        }
        if (is_string($end_date)) {
            $this->end_date = new DateTimeImmutable($end_date);
        } else {
            $this->end_date = $end_date;
        }

        return $this;
    }

    public function __toString()
    {
        return $this->start_date->format('Y-m-d') . '<->' . $this->end_date->format('Y-m-d');
    }

    /**
     * @return DateTimeImmutable
     */
    public function getStart()
    {
        return $this->start_date;
    }

    /**
     * @return DateTimeImmutable
     */
    public function getEnd()
    {
        return $this->end_date;
    }

    /**
     * Converts the DateRange to a DatePeriod defined by $interval, defaults to 1 day
     * @param  string     $interval
     * @return DatePeriod
     */
    public function asPeriod($interval = 'P1D')
    {
        return new DatePeriod(
            $this->start_date,
            new DateInterval($interval),
            $this->end_date->modify('+1 day')
        );
    }

    /**
     * Simple comparison of DateRanges
     * @param  DateRange $range
     * @return bool
     */
    public function isEquivalentTo(DateRange $range)
    {
        return ($this->getStart() == $range->getStart() and $this->getEnd() == $range->getEnd());
    }

    /**
     * Return the number of days for this range
     * @return int
     */
    public function countDays()
    {
        return intval($this->start_date->diff($this->end_date)->format("%a")) + 1;
    }

    /**
     * Static methods
     */

    /**
     * Intersect a DateRange with another DateRange, returns a DateRange or NULL
     * @param  DateRange      $left
     * @param  DateRange      $right
     * @return DateRange|null
     */
    public static function intersect(DateRange $left, DateRange $right)
    {
        // Swap left and right to get the smaller first, helps up on the speed of the iteration plus it is requested for the second comparison to work properly.
        if ($left->countDays() > $right->countDays()) {
            list($left, $right) = array($right, $left);
        }

        if ($left->getEnd() < $right->getStart() or $left->getStart() > $right->getEnd()) {
            return null;
        } else {
            if ($left->getStart() >= $right->getStart() and $left->getEnd() <= $right->getEnd()) {
                return $left;
            } else {
                $period = $left->asPeriod();
                $start = $end = null;
                foreach ($period as $entry) {
                    if ($entry >= $right->getStart() and $entry <= $right->getEnd()) {
                        if (!$start) {
                            $start = $entry;
                        }
                        $end = $entry;
                    } elseif ($entry > $right->getEnd()) {
                        break;
                    }
                }

                return new DateRange($start, $end);
            }
        }
    }

    /**
     * Joins a DateRange with another DateRange, returns either DateRange or NULL if no join is possible
     * @param  DateRange      $left
     * @param  DateRange      $right
     * @return DateRange|null
     */
    public static function join(DateRange $left, DateRange $right)
    {
        // Swap left and right to get the starter one first.
        if ($left->getStart() > $right->getStart()) {
            list($left, $right) = array($right, $left);
        }
        if ($left->getEnd()->modify('+1 day') >= $right->getStart() and $left->getEnd() <= $right->getEnd()) {
            return new DateRange($left->getStart(), $right->getEnd());
        } elseif ($left->getEnd() >= $right->getStart() and
            $left->getStart() <= $right->getStart() and
            $left->getEnd() >= $right->getEnd()
        ) {
            return new DateRange($left->getStart(), $left->getEnd());
        }

        return null;
    }

    /**
     * Subtract from the first DateRange another DateRange and returns an array with the outcome
     * The outcome can be either an empty array, a single DateRange or two DateRanges
     * @param  DateRange $minuend
     * @param  DateRange $subtrahend
     * @return array
     */
    public static function subtract(DateRange $minuend, DateRange $subtrahend)
    {
        if ($minuend->getEnd() < $subtrahend->getStart() or
            $minuend->getStart() > $subtrahend->getEnd() or
            ($minuend->getStart() == $subtrahend->getStart() and $minuend->getEnd() == $subtrahend->getEnd())
        ) {
            return array();
        } elseif ($subtrahend->getStart() > $minuend->getStart()
            and $subtrahend->getStart() <= $minuend->getEnd()
            and $subtrahend->getEnd() >= $minuend->getEnd()
        ) {
            return [new DateRange($minuend->getStart(), $subtrahend->getStart()->modify('-1 day'))];
        } elseif ($subtrahend->getEnd() >= $minuend->getStart()
            and $subtrahend->getStart() <= $minuend->getStart()
            and $subtrahend->getEnd() >= $minuend->getStart()
        ) {
            return [new DateRange($subtrahend->getEnd()->modify('+1 day'), $minuend->getEnd())];
        } else {
            return [
                new DateRange($minuend->getStart(), $subtrahend->getStart()->modify('-1 day')),
                new DateRange($subtrahend->getEnd()->modify('+1 day'), $minuend->getEnd())
            ];
        }
    }

    /**
     * Joins DateRanges if they overlap on some point.
     * @param  array $ranges
     * @return array
     */
    public static function joinRanges(Array $ranges)
    {
        for ($i = 0; $i < count($ranges); $i++) {
            $cmp = $ranges[$i];
            if ($cmp) { //Iterate over all of them unless we set them as null below because we joined them
                $j = $i;
                foreach (array_slice($ranges, $i + 1) as $range) { //The array gets smaller on each run
                    $j++;
                    if ($range) {
                        $result = self::join($cmp, $range);
                        if ($result) {
                            $ranges[$i] = $cmp = $result;
                            $ranges[$j] = null; // Removes entries that got joined (avoids reprocessing)
                        }
                    }
                }
            }
        }

        return self::cleanupSort($ranges);
    }

    /**
     * @param  array $left_ranges
     * @param  array $right_ranges
     * @return array
     */
    public static function intersectRanges(Array $left_ranges, Array $right_ranges)
    {
        $ranges = array();
        foreach ($left_ranges as $left_range) {
            foreach ($right_ranges as $right_range) {
                array_push($ranges, self::intersect($left_range, $right_range));
            }
        }

        return self::joinRanges(self::cleanupSort($ranges));
    }

    /**
     * Auxiliar function to cleanup null values, restore index and sort results
     * @param  array $arr
     * @return array
     */
    public static function cleanupSort(Array $arr)
    {
        $arr = array_filter($arr);
        sort($arr);

        return $arr;
    }
}