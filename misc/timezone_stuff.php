<?php

$event_timestamp = 1675167722;
$event_datetime = new DateTime('@' . $event_timestamp);
$datekey = $event_datetime->format('Ymd');
echo 'event_timestamp  UTC : ' . $event_timestamp . PHP_EOL;
echo 'event_datetime   UTC : ' . $event_datetime->format('Y-m-d H:i:s') . PHP_EOL;
echo 'datekey          UTC : ' . $datekey . PHP_EOL;
#exit;

$local_tz_label = 'EST5EDT';
$local_tz_label = 'UTC';

$local_start_date = '2023-02-19';
$local_start_dt = new DateTime($local_start_date,  new DateTimeZone($local_tz_label));
echo 'local start date min: ' . $local_tz_label . ': ' . $local_start_date . PHP_EOL;
echo 'local start dt min timestamp ' . $local_tz_label . ': ' . $local_start_dt->getTimestamp() . PHP_EOL;

$local_end_date = '2023-02-20';
$local_end_dt = new DateTime($local_end_date,  new DateTimeZone($local_tz_label));
echo 'local start date max: ' . $local_tz_label . ': ' . $local_end_date . PHP_EOL;
echo 'local start dt max timestamp ' . $local_tz_label . ': ' . $local_end_dt->getTimestamp() . PHP_EOL;

if ( $event_timestamp >= $local_start_dt->getTimestamp() && $event_timestamp < $local_end_dt->getTimestamp() ) {
    echo 'within target range.' . PHP_EOL;
}


echo 'EVENT           LOWER           UPPER' . PHP_EOL;
echo $event_timestamp . '       ' . $local_start_dt->getTimestamp() . '      ' . $local_end_dt->getTimestamp() . PHP_EOL;






