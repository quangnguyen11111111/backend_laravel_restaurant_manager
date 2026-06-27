<?php
$from = \Carbon\Carbon::parse('2026-06-26T17:00:00.000Z');
echo $from->toDateTimeString() . "\n";
$from->setTimezone(config('app.timezone'));
echo $from->toDateTimeString() . "\n";
