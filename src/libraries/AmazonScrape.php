<?php

namespace Michael\AmazonSellerApi\libraries;

use Symfony\Component\DomCrawler\Crawler;

use Michael\AmazonSellerApi\libraries\IScrapeMethod;

class AmazonScrape implements IScrapeMethod 
{
    private $crawler;    
    
    public function __construct() 
    {
        
    }
    
    public function processResults($client, $part_no, $description, $log = null): array
    {
        $results = ['success' => false];
                       
        $descriptionArr = explode(' ', $description);
        $key = array_search($part_no, $descriptionArr);
        if ($key === false) $cycle = 1; // Only try this once
        else $cycle = $key;
        while (!$results['success'] && $cycle >= 0) {
            $search_str = ($key !== false ? implode('+', array_merge(array_slice($descriptionArr, 0, $cycle), [$part_no]) ) : $part_no);
            // $log->info('Searching for:'. $search_str);
            
            $url_format = "https://www.amazon.com/s?k=%s&i=electronics&ref=nb_sb_noss";
            // Does &i=electronics hurt or help? 
            $url = sprintf($url_format, $search_str);
            $client->request('GET', $url);
                        
            $this->crawler = $client->waitFor('.s-search-results');
            // $this->client->takeScreenshot('results.png'); // Yeah, screenshot!
            $productResults = $this->crawler->filter('.s-result-item');
            if ($productResults->count()) {
                // $log->info('# of results:'.$productResults->count());
                $productResults->each(function (Crawler $pn, $j) use(&$results, $part_no, $log) {
                    if (!$results['success']) {
                        // $log->info($pn->text());
                        $full_text = $pn->text();
                        if (strstr($full_text, $part_no) !== false && strstr($full_text, "Sponsored") === false) {
                            // $log->info('FOUND on Amazon!');
                            
                            $results['success'] = true;
                            $alinkNode = $pn->filter('.a-link-normal')->first();
                            $imageNode = $pn->filter('.a-link-normal')->first()->filter('.s-image')->first();
                            $page_href = $alinkNode->attr('href');
                            $url_arr = parse_url($page_href);
                            
                            $base_path = substr($url_arr['path'], 0, strrpos( $url_arr['path'], '/'));
                            $results['product_url'] = 'https://www.amazon.com'.$base_path;
                            $path_arr = explode('/', $base_path);
                            $results['asin'] = $path_arr[count($path_arr)-1];
                        }
                    }
                });
            }
            $cycle--;
            // usleep( mt_rand(2000000, 4000000) ); // Delay to avoid suspicion
        } // end while loop
        
        return $results;
    }
    
}