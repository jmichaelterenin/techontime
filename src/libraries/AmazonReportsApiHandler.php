<?php

namespace Michael\AmazonSellerApi\libraries;

use SellingPartnerApi\Api;
use SellingPartnerApi\ReportType;
use SellingPartnerApi\Document;

class AmazonReportsApiHandler extends AmazonApiHandler
{    
    private $report_type, $marketplace_ids, $body, $report_document_id, $document;
        
    public function __construct($config = [])
    {
        parent::__construct($config);
        
        $this->api = new \SellingPartnerApi\Api\ReportsApi($this->config);        
    }
    
    public function prepareReportCreateCall($reportType, $marketplace_ids)
    {
        $this->marketplace_ids = $marketplace_ids;
        $this->report_type = constant("\SellingPartnerApi\ReportType::$reportType");
        
        $this->body = (new \SellingPartnerApi\Model\Reports\CreateReportSpecification())
        ->setReportType($this->report_type['name'])
        ->setReportOptions(['RootNodesOnly' => true])
        ->setMarketplaceIds($marketplace_ids);
    }
    
    public function callCreateReport()
    {
        $result = $this->api->createReport($this->body);
        $array = json_decode(json_encode($result), true);
        
        return $array;
    }        
    
    public function callGetReport($report_id)
    {
        $result = $this->api->getReport($report_id);
        $array = json_decode(json_encode($result), true);
        
        return $array;
    }
    
    public function prepareReportDocumentCall($reportType, $reportDocumentId)
    {
        $this->report_type = constant("\SellingPartnerApi\ReportType::$reportType");
        $this->report_document_id = $reportDocumentId;
    }
    
    public function callDocumentReport()
    {
        $result = $this->api->getReportDocument($this->report_document_id, $this->report_type);
        
        $document = new Document($result->getPayload(), $this->report_type);
        $document->download(false);
        $this->document = $document->getData();
        
        $array = json_decode(json_encode($result), true);
        
        return $array;        
    }
    
    public function getDocument()
    {
        return $this->document;
    }
}