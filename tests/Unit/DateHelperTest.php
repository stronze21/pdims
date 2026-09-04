<?php

namespace Tests\Unit;

use App\Helpers\DateHelper;
use PHPUnit\Framework\TestCase;

class DateHelperTest extends TestCase
{
    public function test_it_rejects_dates_before_sql_server_datetime_minimum()
    {
        $parsed = DateHelper::parseExpiryDate('0202-09-30');

        $this->assertSame('0202-09-30', $parsed['raw']);
        $this->assertSame('0202-09-30', $parsed['formatted']);
        $this->assertNull($parsed['sql_format']);
    }

    public function test_it_formats_a_valid_iso_expiry_date()
    {
        $parsed = DateHelper::parseExpiryDate('2027-09-30');

        $this->assertSame('Sep 30, 2027', $parsed['formatted']);
        $this->assertSame('2027-09-30', $parsed['sql_format']);
    }

    public function test_it_rejects_an_impossible_calendar_date()
    {
        $parsed = DateHelper::parseExpiryDate('2027-02-30');

        $this->assertNull($parsed['sql_format']);
    }
}
