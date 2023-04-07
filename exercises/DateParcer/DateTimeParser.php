<?php

declare(strict_types=1);

class DateTimeParser
{
    public const DAYS = ['d', 'j'];
    public const MONTHS = ['m', 'n', 'M', 'F'];
    public const YEARS = ['y', 'Y'];
    public const DELIMITERS = ['', '.', '/', ' ', '-'];
    public const FORMAT_SIZES = [
        'year_only' => 'Y',
        'month_and_year' => 'Ym',
        'full_date' => 'Ymd'
    ];

    /**
     * @throws Exception
     */
    static public function dtParse($date): int
    {
        $obj = new static();

        /* optional validation. unnecessary for php>8 */
        if (!is_string($date) && !is_int($date)) {
            throw new Exception('Date must be integer or string');
        }
        if (is_int($date) && $date <= 0) {
            throw new Exception('Date must be greater than zero');
        }

        return (int)$obj->parse((string)$date);
    }

    /**
     * @throws Exception
     */
    public function parse(string $date): string
    {
        /* improvement: build non replace params from self::DELIMITERS */
        $date = preg_replace('/[^A-z0-9 \.\/-]/', '', $date);

        $formatType = $this->detectFormatType($date);
        $availableFormats = $this->generateDateFormats($formatType);

        $result = null;

        foreach ($availableFormats as $format) {
            /* use strict format */
            $format = "!{$format}";
            $parsedDate = DateTimeImmutable::createFromFormat($format, $date);
            if (!$this->successfullyParsed($parsedDate)) {
                continue;
            }

            $result = $parsedDate->format($formatType);
            break;
        }

        if (!$result) {
            throw new Exception('Date can\'t be parsed');
        }

        return str_pad($result, 8, '0');
    }

    public function detectFormatType(string $date): string
    {
        $matches = [];
        if (preg_match('/^(\d{2,4})$/', $date, $matches) > 0) {
            return self::FORMAT_SIZES['year_only'];
        }
        if (preg_match('/^(\d{1,2}).?(\d{2,4})$/', $date, $matches) > 0) {
            return self::FORMAT_SIZES['month_and_year'];
        }
        return self::FORMAT_SIZES['full_date'];
    }

    /**
     * @throws Exception
     */
    protected function generateDateFormats(string $formatType): array
    {
        $formats = [];
        switch ($formatType) {
            case self::FORMAT_SIZES['year_only']:
                foreach (self::YEARS as $year) {
                    $formats[] = "{$year}";
                }
                break;
            case self::FORMAT_SIZES['month_and_year']:
                foreach (self::YEARS as $year) {
                    foreach (self::MONTHS as $month) {
                        foreach (self::DELIMITERS as $delimiter) {
                            $formats[] = "{$year}{$delimiter}{$month}";
                            $formats[] = "{$month}{$delimiter}{$year}";
                        }
                    }
                }
                break;
            case self::FORMAT_SIZES['full_date']:
                foreach (self::YEARS as $year) {
                    foreach (self::MONTHS as $month) {
                        foreach (self::DAYS as $day) {
                            foreach (self::DELIMITERS as $firstDelimiter) {
                                foreach (self::DELIMITERS as $secondDelimiter) {
                                    $formats[] = "{$month}{$firstDelimiter}{$day}{$secondDelimiter}{$year}";
                                    $formats[] = "{$year}{$firstDelimiter}{$month}{$secondDelimiter}{$day}";
                                    $formats[] = "{$day}{$firstDelimiter}{$month}{$secondDelimiter}{$year}";
                                }
                            }
                        }
                    }
                }
                break;
            default:
                throw new Exception("Unexpected format type: {$formatType}");
        }

        return $formats;
    }

    /**
     * @param DateTimeImmutable|bool $date
     */
    public function successfullyParsed($date): bool
    {
        if (!$date) {
            return false;
        }

        $errors = DateTime::getLastErrors();
        if ($errors['warning_count'] > 0) {
            return false;
        }
        if ($errors['error_count'] > 0) {
            return false;
        }

        return true;
    }
}

