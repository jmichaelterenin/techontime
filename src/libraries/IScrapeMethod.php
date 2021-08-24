<?php

namespace Michael\AmazonSellerApi\libraries;

interface IScrapeMethod
{
    
    public function processResults($client, $part_no, $description, $log): array;
        
}