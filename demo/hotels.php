<?php
/**
 * Project: PlanitourScraper
 *
 * @author Amado Martinez <amado@projectivemotion.com>
 */

require __DIR__ . '/../vendor/autoload.php';

if($argc < 3)
{
    $travis_ci  =   getenv('planitour_login');
    if($travis_ci)
    {
        list($username, $password)  =   explode(':', $travis_ci);
    }else
        die("$argv[0] [user] [pass]");
}else{
    $username   =   $argv[1];
    $password   =   $argv[2];
}

$planitour  =   new \projectivemotion\PlanitourScraper\Scraper($username, $password);
$planitour->cacheOn();
$planitour->verboseOff();

// must login before calling initSearch
$planitour->doLogin();

// find Berlin Hotel
$planitour->setHotelFilter('Berlin Mark Hotel');

// get first page of results.
$results_info   =   $planitour->initSearch('Germany', 'Berlin', '2016-05-15', '2016-05-18', $cityCode);

print_r($results_info);
