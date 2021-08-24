<?php

namespace Michael\AmazonSellerApi\libraries;

use Symfony\Component\DomCrawler\Crawler;

use Michael\AmazonSellerApi\libraries\IScrapeMethod;

class GoogleScrape implements IScrapeMethod 
{
    private $crawler;    
    
    public function __construct() 
    {
        
    }
    
    public function processResults($client, $part_no, $description, $log = null): array
    {
        $results = ['success' => false];
        
        $url_format = "https://www.google.com/search?q=%s";
        $search_str = 'Amazon.com+'.$part_no.'';
        $url = sprintf($url_format, $search_str);
        $client->request('GET', $url);        
        
        $this->crawler = $client->waitFor('#search');
        $productResults = $this->crawler->filter('#search .g');
        if ($productResults->count()) {
            if ($log) $log->debug($productResults->count().' results');
            $productResults->each(function (Crawler $pn, $j) use(&$results, $part_no, $log) {
                if (!$results['success']) {
                    $alinkNode = $pn->filter('a')->first();
                    $page_href = $alinkNode->attr('href');
                    if (strstr($page_href, $part_no) !== false && strstr($page_href, 'www.amazon.com') !== false) {
                        // $log->info('FOUND on Google!');
                        $results['success'] = true;
                        $url_arr = parse_url($page_href);
                        $base_path = $url_arr['path'];
                        // $log->info($base_path);
                        $path_arr = array_values(array_filter(explode('/', $base_path)));
                        $results['asin'] = $path_arr[count($path_arr)-1];
                        if (strstr($page_href, 'product-reviews') !== false) {                    
                            $results['product_url'] = 'https://www.amazon.com'.$path_arr[0].'/dp/'.$results['asin'].'/';
                        } else
                            $results['product_url'] = 'https://www.amazon.com'.$base_path;
                    }
                }
            });
        }
        usleep( mt_rand(2000000, 4000000) ); // Delay to avoid suspicion
        // $log->info('Results:', $results);
        return $results;
    }
    
}