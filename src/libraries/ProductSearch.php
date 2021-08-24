<?php

namespace Michael\AmazonSellerApi\libraries;

use Symfony\Component\DomCrawler\Crawler;

use Michael\AmazonSellerApi\libraries\AmazonScrape;
use Michael\AmazonSellerApi\libraries\GoogleScrape;

class ProductSearch
{
    private $client, $crawler, $asin, $product_url, $scrapeStrategy;
    
    public function __construct($client)
    {
        $this->client = $client;
        $this->asin = '';
        $this->product_url = '';
        $this->scrapeStrategy = null;
    }
    
    public function setStrategy(IScrapeMethod $method)
    {
        $this->scrapeStrategy = $method;
    }
    
    public function performSearch($part_no, $description, $log = false) : bool
    {
        if (!$this->scrapeStrategy || $this->scrapeStrategy instanceof GoogleScrape)
            $this->setStrategy(new AmazonScrape());
        
        $results = $this->scrapeStrategy->processResults($this->client, $part_no, $description, $log);        
        if ($results['success']) {
            $this->asin = $results['asin'];
            $this->product_url = $results['product_url'];
        } else {
            if ($log) $log->debug('GOOGLE SEARCH');
            $this->setStrategy(new GoogleScrape());
            $results = $this->scrapeStrategy->processResults($this->client, $part_no, $description, $log);
            // $log->info('Results:', $results);
            if ($results['success']) {
                $this->asin = $results['asin'];
                $this->product_url = $results['product_url'];
            }
        }
        
        return $results['success'];                            
    }
       
    public function getAsin()
    {
        return $this->asin;
    }
    
    public function getProductUrl()
    {
        return $this->product_url;
    }
}