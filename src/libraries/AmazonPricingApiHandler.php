<?php

namespace Michael\AmazonSellerApi\libraries;

use SellingPartnerApi\Api;

class AmazonPricingApiHandler extends AmazonApiHandler
{    
    private $marketplace_ids, $item_type;
        
    public function __construct($config = [])
    {
        parent::__construct($config);
        
        $this->api = new \SellingPartnerApi\Api\ProductPricingApi($this->config);        
    }
    
    public function preparePricingCall($marketplace_ids, $item_type)
    {
        $this->marketplace_ids = $marketplace_ids;
        $this->item_type = $item_type;
    }
    
    public function callPricing($identifier)
    {
        $asins = ($this->item_type == 'Asin' ? array($identifier) : []);
        $skus = ($this->item_type == 'Sku' ? array($identifier) : []);
        $result = $this->api->getCompetitivePricing($this->marketplace_ids, $this->item_type, $asins, $skus);
        $array = json_decode(json_encode($result), true);
        $this->array = $array['payload'][0];
        return $this;
    }
     
    public function getSalesRank()
    {
        return ((isset($this->array['Product']['SalesRankings']) && !empty($this->array['Product']['SalesRankings'])) ? $this->array['Product']['SalesRankings'][0]['Rank'] : '0');
    }
    
    public function getProductCategoryId()
    {
        return ((isset($this->array['Product']['SalesRankings']) && !empty($this->array['Product']['SalesRankings'])) ? $this->array['Product']['SalesRankings'][0]['ProductCategoryId'] : '0');        
    }
    
    public function getBuyBoxLandedPrice($type)
    {
        $bb_landed_price = 0;
        if (isset($this->array['Product']['CompetitivePricing'])) {
            $competitivePrices = $this->array['Product']['CompetitivePricing']['CompetitivePrices'];
            if (!empty($competitivePrices)) {
                $compPrice = array_values(array_filter($competitivePrices, function($cp) use($type) {
                    return ($cp['CompetitivePriceId'] == $type);
                }));
                if (count($compPrice)) 
                    $bb_landed_price = $compPrice[0]['Price']['LandedPrice']['Amount'];                
            }
        }
        return $bb_landed_price;
    }
    
}