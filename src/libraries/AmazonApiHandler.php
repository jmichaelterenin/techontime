<?php

namespace Michael\AmazonSellerApi\libraries;

use SellingPartnerApi\Endpoint;
use SellingPartnerApi\Configuration;

class AmazonApiHandler 
{
    protected $config, $api, $array;
    
    public function __construct($config = [])
    {
        $this->config = new Configuration([
            "lwaClientId" => $_ENV['lwaClientId'],
            "lwaClientSecret" => $_ENV['lwaClientSecret'],
            "lwaRefreshToken" => $_ENV['lwaRefreshToken'],
            "awsAccessKeyId" => $_ENV['awsAccessKeyId'],
            "awsSecretAccessKey" => $_ENV['awsSecretAccessKey'],
            "endpoint" => Endpoint::NA
        ]);
        
        $this->array = [];
    }
    
    public function getResults()
    {
        return $this->array;
    }
}