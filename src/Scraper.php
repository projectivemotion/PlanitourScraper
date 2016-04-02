<?php
/**
 * Project: PlanitourScrapper
 *
 * @author Amado Martinez <amado@projectivemotion.com>
 */

namespace projectivemotion\PlanitourScraper;

use projectivemotion\PhpScraperTools\CacheScraper;

class Scraper extends CacheScraper
{
    protected $domain   =   'www.planitour.travel';

    protected $HotelFilter  =   '';
    protected $username;
    protected $password;

    public function setUsername($username)
    {
        $this->username = $username;
    }

    public function setPassword($password)
    {
        $this->password = $password;
    }

    function __construct($username, $password)
    {
        $this->setUsername($username);
        $this->setPassword($password);
    }

    public function doLogin()
    {
        $result =   $this->getCurl('/services/login.php', array('login' => $this->username, 'pass' => $this->password));
        if(json_decode($result)->esito != 'OK')
            throw new \Exception("UNABLE TO LOGIn!");

        return TRUE;
    }

    public function getCityCode($city_string, $country_string)
    {
        $res	=	$this->cache_get('/services/ajax_destination.php', 'query=' . $city_string);
        $response_json = json_decode($res);

        $match = strtoupper($city_string . ", " . $country_string);

        foreach($response_json as $city_info){
            if($city_info->label == $match){
                return $city_info->value;
            }
        }

        return 'ERROR';
    }

    public function initSearch($Country, $City, $check_in, $check_out, &$CityCode)
    {
        $ci_time    =   strtotime($check_in);
        $co_time    =   strtotime($check_out);
        
        $data = array();
        $data["htlname"] = $this->getHotelFilter();
        $data["dest"] = strtoupper($City.", ".$Country);
        $data["date1"] = date("d-M-Y", $ci_time);
        $data["date2"] = date("d-M-Y", $co_time);
        $data["fromdate"] = date('Y-m-d', $ci_time);
        $data["todate"] = date('Y-m-d', $co_time);
        
        $data["numcam"] = "1";
        $data["ad_list[]"] = "2";
        $data["ch_list[]"] = "0";
        $data["ch_ages_list[]"] = "0";

        $data["clientnat"] = "GR";


        if($CityCode == '')
        {
            $CityCode = $this->getCityCode($City, $Country);
        }

        $data['destcd'] = $CityCode;

        $result	=	$this->cache_get('/hotel-list.php', $data, FALSE);

        return $this->parsePage($result, $Country, $City);
    }

    public function getResults($page_num = 0, $Country, $City)
    {
        // @todo fix caching requests.
        $getvars    =   $page_num ? '?page=' . $page_num : '';
        
        $response   =   $this->cache_get('/hotel-list.php' . $getvars, NULL, FALSE);
        
        return $this->parsePage($response, $Country, $City);
    }

    public function parsePage($htmltext, $Country, $City)
    {
        preg_match('#<span id="nbresults".*?>([^<]*?)</span>#', $htmltext, $matches);
        $num_results = $matches[1];

        if (preg_match('#<a href="hotel-list.php\?page=(\d*)" class="paging">Last</a>#', $htmltext, $matches)) {
            $total_pages = $matches[1];
        } else {
            $total_pages = 0;
        }
        
        $doc    =   \phpQuery::newDocument($htmltext);
        
        $elements  =   $doc['div.hotel-item'];
        $hotelid_regexp = '[0-9]+\|([0-9.]+)\|([0-9.]+)\|[^\|]+\|hotel-details.php\?p=';
        
        $hotels =   array();
        foreach($elements as $el)
        {
            $id = trim(pq($el)->find("input[name=hotelid]")->attr("value"));

            if($id == '') continue;

            $hotel = array();

            $hotel['id'] = trim($id);
            $hotel['city'] = $City;
            $hotel['country'] = $Country;
            $hotel['title'] = trim(pq($el)->find("div.hotel-item-name")->text());
            $hotel['address'] = trim(pq($el)->find("div.hotel-item-addr")->text());
            $hotel['net-price'] = (float)str_replace(" €", "", trim(pq($el)->find("div.hotel-item-leadp .red")->text()));
//            $hotel['net-price'] = round($hotel['net-price'], 2);
            $hotel['description'] = trim(pq($el)->find("div.hotel-item-descr")->text());

            if(preg_match("#([0-9]+), $Country -#i", $el->textContent, $postcode))
                $hotel['postcode'] = $postcode[1];

            //  note here, some hotels do not provide their position information, or post code! See EL GOUNA, EGYPT, Captains Inn.
            if(preg_match("#$hotelid_regexp$id#", $htmltext, $position_info))
            {
                $hotel['latitude'] = $position_info[1];
                $hotel['longitude'] = $position_info[2];
            }

            $star = pq($el)->find("img");
            $count = 0;
            foreach ($star as $s) {
                if (pq($s)->attr("src") == "images/new/star-active.png") {
                    $count = $count + 1;
                }
            }
            $hotel['stars'] = $count;
            $imgsrc = pq($el)->find("img.hotel-item-photo")->attr("src");
            $hotel['photos'] = array($imgsrc);
            $hotel['deepLink'] = "";

            $rooms = array();
            $room_elements = pq($el)->find("div.hdi_line_inline");

            foreach ($room_elements as $rm) {

                $room = array();
                $room['name']           = trim(pq($rm)->find('.hdi_rooms')->text());
                $room['description']    = trim(pq($rm)->find('.hdi_detail')->text());
                $room['net-price']      = str_replace(" €", "", trim(pq($rm)->find("div.bold")->text()));
                $room['avg-night-rate'] = str_replace(" €", "", trim(pq($rm)->find("div.hdi_price_inline:first")->text()));
                $rooms[] = $room;
            }
            $hotel['rooms'] = $rooms;
            
            $hotels[]   =   $hotel;
        }

        return (object)compact('total_pages', 'num_results', 'hotels');
    }

    public function setHotelFilter($HotelFilter)
    {
        $this->HotelFilter = $HotelFilter;
    }

    public function getHotelFilter()
    {
        return $this->HotelFilter;
    }

    public function curl_setopt($ch)
    {
        curl_setopt($ch, CURLOPT_TIMEOUT, 3600);
        parent::curl_setopt($ch);
    }
}