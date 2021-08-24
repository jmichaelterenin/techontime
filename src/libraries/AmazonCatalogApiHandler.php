<?php

namespace Michael\AmazonSellerApi\libraries;

use SellingPartnerApi\Api;

class AmazonCatalogApiHandler extends AmazonApiHandler
{    
    private $marketplace_ids, $included_data, $locale;
        
    public function __construct($config = [])
    {
        parent::__construct($config);
        
        $this->api = new \SellingPartnerApi\Api\CatalogApi($this->config);        
    }
    
    public function prepareCatalogItemCall($marketplace_ids, $included_data, $locale = 'en_US')
    {
        $this->marketplace_ids = $marketplace_ids;
        $this->included_data = $included_data;
        $this->locale = $locale;
    }
    
    public function callCatalogItem($asin)
    {
        $result = $this->api->getCatalogItem($asin, $this->marketplace_ids, $this->included_data, $this->locale);
        $this->array = json_decode(json_encode($result), true);
        
        return $this;
    }        
    
    public function getMainImage()
    {
        return (isset($this->array['images'][0]['images'][0]['link']) ? $this->array['images'][0]['images'][0]['link'] : '');    
    }
    
    public function getBrand()
    {
        return ((isset($this->array['summaries']) && isset($this->array['summaries'][0]['brandName'])) ? $this->array['summaries'][0]['brandName'] : '');
    }
    
    public function getManufacturer()
    {
        return ((isset($this->array['summaries'])  && isset($this->array['summaries'][0]['manufacturer'])) ? $this->array['summaries'][0]['manufacturer'] : '');
    }
    
    public function getItemName()
    {
        return (isset($this->array['summaries']) ? $this->array['summaries'][0]['itemName'] : '');
    }
    
    public function getModelNumber()
    {
        return ((isset($this->array['summaries']) && isset($this->array['summaries'][0]['modelNumber'])) ? $this->array['summaries'][0]['modelNumber'] : '');
    }
    
}