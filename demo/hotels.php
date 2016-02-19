<?php
/**
 * Project: PlanitourScraper
 *
 * @author Amado Martinez <amado@projectivemotion.com>
 */

require __DIR__ . '/../vendor/autoload.php';

if($argc < 3)
    die("$argv[0] [user] [pass]");

$planitour  =   new \projectivemotion\PlanitourScraper($argv[1], $argv[2]);
$planitour->use_cache   =   true;

// must login before calling initSearch
$planitour->doLogin();

// find Emporio Hotel
$planitour->setHotelFilter('Emporio');

// get first page of results.
$results_info   =   $planitour->initSearch('Mexico', 'Cancun', '2016-05-01', '2016-05-3', $cityCode);

print_r($results_info);
