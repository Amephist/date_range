<?php

namespace Grubie\Libs;

use DateTime;
use BadFunctionCallException;
use DatePeriod;
use DateInterval;

class DateRange
{
    /**
     * @var \DateTime
     */
    protected $start_date;

    /**
     * @var \DateTime
     */
    protected $end_date;

    /**
     * @param int|string|DateTime $start_date
     * @param int|string|DateTime $end_date
     * @returns DateRange
     *
     * @throws BadFunctionCallException
     */
    public function __construct($start_date, $end_date)
    {
        if (is_string($start_date)) {
            $this->start_date = new DateTime($start_date);
        } elseif (is_integer($start_date)) {
            $this->start_date = DateTime::createFromFormat('U', $start_date);
        } else {
            $this->start_date = $start_date;
        }
        if (is_string($end_date)) {
            $this->end_date = new DateTime($end_date);
        } elseif (is_integer($end_date)) {
            $this->end_date = DateTime::createFromFormat('U', $end_date);
        } else {
            $this->end_date = $end_date;
        }

        if ($this->start_date > $this->end_date) {
            throw new BadFunctionCallException(
                'start_date should be lower or equal to end_date, ' . $this->__toString() . ' provided');
        }

        return $this;
    }

    public function __toString()
    {
        return $this->getIsoStart() . '|' . $this->getIsoEnd();
    }

    /**
     * @return DateTime
     */
    public function getStart()
    {
        return clone $this->start_date;
    }

    /**
     * @return DateTime
     */
    public function getEnd()
    {
        return clone $this->end_date;
    }

    /**
     * Returns ISO 8601 start date formatted text.
     *
     * @return string
     */
    public function getIsoStart()
    {
        return $this->start_date->format('Y-m-d');
    }

    /**
     * Returns ISO 8601 end date formatted text.
     *
     * @return string
     */
    public function getIsoEnd()
    {
        return $this->end_date->format('Y-m-d');
    }

    /**
     * Converts the DateRange to a DatePeriod defined by $interval, defaults to 1 day.
     *
     * @param string $interval
     *
     * @return DatePeriod
     */
    public function asPeriod($interval = 'P1D')
    {
        $end_date = clone $this->end_date;

        return new DatePeriod(
            $this->start_date,
            new DateInterval($interval),
            $end_date->modify('+1 day')
        );
    }

    /**
     * Simple comparison of DateRanges.
     *
     * @param DateRange $range
     *
     * @return bool
     */
    public function isEquivalentTo(DateRange $range)
    {
        return ($this->getStart() == $range->getStart() and $this->getEnd() == $range->getEnd());
    }

    /**
     * Return the number of days for this range.
     *
     * @return int
     */
    public function countDays()
    {
        return intval($this->start_date->diff($this->end_date)->format('%a')) + 1;
    }

    /**
     * Returns if a DateTime object is within the current range.
     *
     * @param DateTime $date
     *
     * @return bool
     */
    public function includes(DateTime $date)
    {
        return ($date >= $this->start_date && $date <= $this->end_date);
    }

    /**
     * Static methods.
     */

    /**
     * Intersect a DateRange with another DateRange, returns a DateRange or NULL.
     *
     * @param DateRange $left
     * @param DateRange $right
     *
     * @return DateRange|null
     */
    public static function intersect(DateRange $left, DateRange $right)
    {
        // Swap left and right to get the smaller first, helps up on the speed of the iteration plus it is requested for the second comparison to work properly.
        if ($left->countDays() > $right->countDays()) {
            list($left, $right) = array($right, $left);
        }

        if ($left->getEnd() < $right->getStart() or $left->getStart() > $right->getEnd()) {
            return;
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

                return new self($start, $end);
            }
        }
    }

    /**
     * Joins a DateRange with another DateRange, returns either DateRange or NULL if no join is possible.
     *
     * @param DateRange $left
     * @param DateRange $right
     *
     * @return DateRange|null
     */
    public static function join(DateRange $left, DateRange $right)
    {
        // Swap left and right to get the starter one first.
        if ($left->getStart() > $right->getStart()) {
            list($left, $right) = array($right, $left);
        }
        if ($left->getEnd()->modify('+1 day') >= $right->getStart() and $left->getEnd() <= $right->getEnd()) {
            return new self($left->getStart(), $right->getEnd());
        } elseif ($left->getEnd() >= $right->getStart() and
            $left->getStart() <= $right->getStart() and
            $left->getEnd() >= $right->getEnd()
        ) {
            return new self($left->getStart(), $left->getEnd());
        }

        return;
    }

    /**
     * Subtract from the first DateRange another DateRange and returns an array with the outcome
     * The outcome can be either an empty array, a single DateRange or two DateRanges.
     *
     * @param DateRange $minuend
     * @param DateRange $subtrahend
     *
     * @return DateRange[]
     */
    public static function subtract(DateRange $minuend, DateRange $subtrahend)
    {
        if ($minuend->getEnd() < $subtrahend->getStart() or
            $minuend->getStart() > $subtrahend->getEnd() or
            ($minuend->getStart() >= $subtrahend->getStart() and $minuend->getEnd() <= $subtrahend->getEnd())
        ) {
            return array();
        } elseif ($subtrahend->getStart() > $minuend->getStart()
            and $subtrahend->getStart() <= $minuend->getEnd()
            and $subtrahend->getEnd() >= $minuend->getEnd()
        ) {
            return array(new self($minuend->getStart(), $subtrahend->getStart()->modify('-1 day')));
        } elseif ($subtrahend->getEnd() >= $minuend->getStart()
            and $subtrahend->getStart() <= $minuend->getStart()
            and $subtrahend->getEnd() >= $minuend->getStart()
        ) {
            return array(new self($subtrahend->getEnd()->modify('+1 day'), $minuend->getEnd()));
        } else {
            return array(
                new self($minuend->getStart(), $subtrahend->getStart()->modify('-1 day')),
                new self($subtrahend->getEnd()->modify('+1 day'), $minuend->getEnd()),
            );
        }
    }

    /**
     * Joins DateRanges if they overlap on some point.
     *
     * @param DateRange[] $ranges
     *
     * @return DateRange[]
     */
    public static function joinRanges(Array $ranges)
    {
        for ($i = 0; $i < count($ranges); ++$i) {
            $cmp = $ranges[$i];
            if ($cmp) { //Iterate over all of them unless we set them as null below because we joined them
                $j = $i;
                foreach (array_slice($ranges, $i + 1) as $range) { //The array gets smaller on each run
                    ++$j;
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
     * Intersects two array of ranges, the result is a new array of ranges.
     *
     * @param DateRange[] $left_ranges
     * @param DateRange[] $right_ranges
     *
     * @return DateRange[]
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
     * Auxiliary function to cleanup null values, restore index and sort results.
     *
     * @param DateRange[] $arr
     *
     * @return DateRange[]
     */
    public static function cleanupSort(Array $arr)
    {
        $arr = array_filter($arr);
        sort($arr);

        return $arr;
    }

    /**
     * Returns an array of ranges from an array of dates (strings for the moment).
     *
     * @param array $arr
     *
     * @return DateRange[]
     */
    public static function extractRanges(Array $arr)
    {
        $ranges = array();
        if (!empty($arr)) {
            $end = $prev = null;
            $start = $arr[0];

            foreach ($arr as $date) {
                if (isset($prev) and date('Y-m-d', strtotime($date . ' - 1 day')) != $prev) {
                    $end = $prev;
                }

                if (isset($start) and isset($end)) {
                    $ranges[] = new self($start, $end);
                    $start = $date;
                    $end = null;
                    $prev = null;
                } else {
                    $prev = $date;
                }
            }

            if (isset($prev)) {
                $ranges[] = new self($start, $prev);
            } else {
                if (isset($start)) {
                    $ranges[] = new self($start, $start);
                }
            }
        }

        return $ranges;
    }
}
