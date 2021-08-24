<?php

namespace Michael\AmazonSellerApi;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Symfony\Component\DomCrawler\Crawler;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Dotenv\Dotenv;
use Exception;

use Michael\AmazonSellerApi\libraries\ProductSearch;
use Michael\AmazonSellerApi\libraries\AmazonCatalogApiHandler;
use Michael\AmazonSellerApi\libraries\AmazonPricingApiHandler;
use Michael\AmazonSellerApi\libraries\AmazonReportsApiHandler;
use Michael\AmazonSellerApi\libraries\PhpSpreadsheetSingleColumnFilter;
use Michael\AmazonSellerApi\libraries\PhpSpreadsheetSingleRowFilter;

class SynccentricSubstitute
{
    private $inFile,
            $dotEnv,
            $log,
            $debug,
            $output, 
            $apiCatalog, 
            $apiPricing,
            $apiReports,
            $client, 
            $crawler, 
            $productSearch,
            $inputFileName, 
            $categoryReader;    
      
    public function __construct($argv)
    {
        foreach ($argv as $idx => $arg) {

            switch($idx) {
                case 1:
                    if ($this->fileExtension($arg) == 'csv')
                        $this->inFile = $arg;
                    else throw new Exception('Expecting a CSV file as the import, shutting down!');
                break;
            }
                    
        }
        
        $this->dotEnv = Dotenv::createImmutable(__DIR__.'/../');
        $this->dotEnv->safeLoad();        

        $this->debug = $_ENV['APP_DEBUG'];
        
        $this->log = new Logger('my_logger');
        // Now add some handlers               
        $this->log->pushHandler(new StreamHandler(__DIR__.'/../info.log', Logger::INFO));
        if ($this->debug)
            $this->log->pushHandler(new StreamHandler(__DIR__.'/../debug.log', Logger::DEBUG));
        
        
        $this->inputFileName = __DIR__.'/../electronics_browse_tree_guide.xls';        
        /** Create a new Xls Reader  **/                
        $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xls();
        
        $worksheetNames = $reader->listWorksheetNames($this->inputFileName);
        // $this->log->info('names:', $worksheetNames);
        
        /** Load $inputFileName to a Spreadsheet Object  **/
        $reader->setLoadSheetsOnly('ELECTRONICS');
        $reader->setReadDataOnly(true);
        
        /**  Create an Instance of our Read Filter  **/
        $idColumnFilter = new PhpSpreadsheetSingleColumnFilter('A'); 
        /**  Tell the Reader that we want to use the Read Filter  **/
        $reader->setReadFilter($idColumnFilter); 
        
        $this->categoryReader = $reader->load($this->inputFileName);        
        
        $this->apiCatalog = new AmazonCatalogApiHandler();
        $this->apiPricing = new AmazonPricingApiHandler();
              
        $this->client = \Symfony\Component\Panther\Client::createFirefoxClient();
        $this->productSearch = new ProductSearch($this->client);
    }
    
    public function run($action = 'process_csv') 
    {        
        $this->log->info('Running '.$action.' on '.$this->inFile);
        $this->$action();
        $this->log->info('Finished '.$action.' on '.$this->inFile);
    }
    
    private function report_document()
    {
        $this->apiReport = new AmazonReportsApiHandler();
        $report_document_id = 'amzn1.spdoc.1.3.aec529aa-1521-48fc-8c68-069a87aaade2.T9A1JJ11LITKV.316';
        $this->apiReport->prepareReportDocumentCall('GET_XML_BROWSE_TREE_DATA', $report_document_id);
        $result = $this->apiReport->callDocumentReport();
        $this->log->info('Result:', $result);
        
        $data = $this->apiReport->getDocument();
        $outputFileName = __DIR__.'/../browse_tree.xml';        
        file_put_contents($outputFileName, $data);        
    }
    
    private function report_get()
    {
        $this->apiReport = new AmazonReportsApiHandler();
        $report_id = '568880018841';
                      
        $result = $this->apiReport->callGetReport($report_id);
        $this->log->info('Result:', $result);
    }
    
    private function report_create()
    {
        $this->apiReport = new AmazonReportsApiHandler();        
        $this->apiReport->prepareReportCreateCall('GET_XML_BROWSE_TREE_DATA', ['ATVPDKIKX0DER']);
        $result = $this->apiReport->callCreateReport();
        $this->log->info('Result:', $result);
    }
    
    private function process_category($productCategoryId)
    {
        $results = ['all_categories' => '', 'subcategory' => ''];
        $found = false;
        foreach ($this->categoryReader->getActiveSheet()->getColumnIterator() as $column) {
            $cellIterator = $column->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(true);
            foreach ($cellIterator as $key => $cell) {
                if ($cell->getValue() == $productCategoryId) {
                    $found = true;
                    $rowId = $cell->getRow();
                    break 2;
                }
            }
        }
            
        if ($found) {
            
            /**  Create an Instance of our Read Filter  **/
            $rowFilter = new PhpSpreadsheetSingleRowFilter($rowId);
            $reader2 = new \PhpOffice\PhpSpreadsheet\Reader\Xls();
            
            /** Load $inputFileName to a Spreadsheet Object  **/
            $reader2->setLoadSheetsOnly('ELECTRONICS');
            $reader2->setReadDataOnly(true);
            
            $reader2->setReadFilter($rowFilter);
            /**  Load only the single row that matches our filter to PHPExcel  **/
            $rowReader = $reader2->load($this->inputFileName);
            $i = 0;
            foreach ($rowReader->getActiveSheet()->getColumnIterator() as $column) {
                $cellIterator = $column->getCellIterator();
                $cellIterator->setIterateOnlyExistingCells(true);        
                foreach ($cellIterator as $key => $cell) {                    
                    if ($i == 1) {                        
                        $results['all_categories'] = $cell->getValue();
                        $categoryArr = explode('/', $results['all_categories']);
                        $results['subcategory'] = $categoryArr[count($categoryArr)-1];
                    }                   
                }
                $i++;
            }
            
        }            
        // $this->log->info('Results:', $results);
        
        return $results;
    }
    
    private function breakdown()
    {
        $inputFileType = 'Csv';
        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader($inputFileType);
        /**  Load $inputFileName to a Spreadsheet Object  **/
        $spreadsheet = $reader->load($this->inFile);
        
        $sheet = $spreadsheet->getSheet(0);
        $rows = $sheet->toArray();
        $headers = array_shift($rows);
        
        $this->log->info('Headers: ', $headers);        
        
        $removedEmptyPartNos = array_filter($rows, function($row) { return strlen(trim($row[0]));  });
        
        $multiplePartNos = array_filter($removedEmptyPartNos, function($row) { return (count(explode(',', $row[0])) > 1);  });
        // $this->log->info('# of multi-partno rows: '. count($multiplePartNos));
        // $this->log->info('multiples: ', $multiplePartNos);        
        $singlePartNos = array_filter($removedEmptyPartNos, function($row) { return (count(explode(',', $row[0])) == 1);  });
        // $this->log->info('# of singles: '. count($singlePartNos));
        
        $newLines = array();
        foreach($multiplePartNos as $row) {
            $partNoArr = explode(',', array_shift($row));                        
            foreach ($partNoArr as $partNo) {
                $partArr = [ trim($partNo) ];
                $lineArr = array_merge(array_values($partArr), array_values($row));                                
                $newLines[] = $lineArr;
            }            
        }
        
        $newCsv = $singlePartNos + $newLines;
        
        // Now Remove Duplicates
        
        // First sort by Part no / Condition
        usort($newCsv, function($a, $b) {
                if ($a[0] == $b[0])                    
                    return strcmp($a[2], $b[2]);                
                else if ($a[0] > $b[0]) 
                    return 1;                
                else 
                    return -1;                
            });
        
        $removedDuplicates = array();
        $previousRow = null;
        foreach($newCsv as $row) {
            if (!$previousRow) {                
                $removedDuplicates[] = $row;
                $previousRow = $row;
            } elseif ($previousRow[0] != $row[0]) {
                $removedDuplicates[] = $previousRow;
                $previousRow = $row;
            } elseif ($previousRow[0] == $row[0]) {
                if (floatval(substr($row[3], 4)) < floatval(substr($previousRow[3], 4)) && strlen(trim($row[4])))
                    $previousRow = $row;
            }
        }
        
        $headerCsv = array_merge([$headers], $removedDuplicates);
        
        // Creates New Spreadsheet
        $newSpreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $newSheet = $newSpreadsheet->getActiveSheet();
        $newSheet->fromArray($headerCsv, NULL, 'A1');
        
        
        // Write a .csv file
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Csv($newSpreadsheet);        
        // Save .xlsx file to the files directory
        $writer->save(__DIR__.'/../output.csv'); 
    }
    
    private function process_csv()
    {
        $columns = [
            'asin',
            'description',
            'manufacturer',
            'mpn',
            'title',
            'large_image',
            'brand',
            'sku',
            'all_categories',
            'subcategory',
            'buybox_new_landed_price',
            'buybox_used_landed_price',
            'sales_rank',
            'buybox_seller',
            'mfn_profit', // This is calculated by including an 8% Amazon fee 
            'error_msg'    
        ];
                
        $marketplace_ids = 'ATVPDKIKX0DER'; // string[] | A comma-delimited list of Amazon marketplace identifiers. Data sets in the response contain data only for the specified marketplaces.
        $included_data = ['images', 'summaries', 'productTypes', 'salesRanks']; // string[] | A comma-delimited list of data sets to include in the response. Default: summaries.
        $locale = 'en_US'; // string | Locale for retrieving localized summaries. Defaults to the primary locale of the marketplace.
        $item_type = 'Asin';
        $this->apiCatalog->prepareCatalogItemCall($marketplace_ids, $included_data, $locale);        
        $this->apiPricing->preparePricingCall($marketplace_ids, $item_type);
        
        $file = fopen($this->inFile, "r");
        $this->output = fopen(__DIR__.'/../output.csv', "w");
        
        // Get the header line
        $csv_line = fgets($file);
        $csv_arr =  explode(",", $csv_line);
        list($part_no, $description, $condition, $cost, $stock) = $csv_arr;
        $part_no = 'initial_identifier';
        
        array_unshift($columns , $part_no);
        $columns[] = 'imported_'.strtolower( preg_replace("/\r|\n/", "", $description) );
        $columns[] = 'imported_'.strtolower( preg_replace("/\r|\n/", "", $condition) );
        $columns[] = 'imported_'.strtolower( preg_replace("/\r|\n/", "", $cost) );
        $columns[] = 'imported_'.strtolower( preg_replace("/\r|\n/", "", $stock) );
        
        // $this->log->info('Headers:', $columns);
        fputcsv($this->output, $columns);             
                
        $counter = 0;
        // Now get the rest
        while(($csv_arr = fgetcsv($file)))
        {
            $productData = array_fill_keys($columns, '');                                               
            // part # is the first
            list($part_no, $description, $condition, $cost, $stock) = $csv_arr;                        
            
            $productData['initial_identifier'] = $part_no;
            $productData['imported_description'] = $description;
            $productData['imported_condition'] = $condition;
            $productData['imported_price'] = floatval( str_replace( ',', '', substr($cost, 4)) ); // 'US $' removed, elminate commas
            $productData['imported_stock'] = $stock;                        
            
            try {
            
                if (!$this->productSearch->performSearch($part_no, $description, ($this->debug ? $this->log : null)))
                    throw new Exception('Amazon and Google yielded no results!');
                
                $productData['asin'] = $this->productSearch->getAsin(); 
              
                // Make the API calls
               
                $this->apiCatalog->callCatalogItem($productData['asin']);
                
                $productData['large_image'] = $this->apiCatalog->getMainImage();
                $productData['brand'] = $this->apiCatalog->getBrand();
                $productData['manufacturer'] = $this->apiCatalog->getManufacturer();
                $productData['title'] = $this->apiCatalog->getItemName();
                $productData['mpn'] = $this->apiCatalog->getModelNumber();
                $productData['sku'] = $this->apiCatalog->getModelNumber();
                
                $result = $this->apiPricing->callPricing($productData['asin'])->getResults();
                if ($this->debug) $this->log->debug('Result:'. json_encode($result));
                $productData['sales_rank'] = $this->apiPricing->getSalesRank();
                
                $productData['buybox_new_landed_price'] = $this->apiPricing->getBuyBoxLandedPrice("1");
                $productData['buybox_used_landed_price'] = $this->apiPricing->getBuyBoxLandedPrice("2");
                
                $price_to_use = (($productData['buybox_new_landed_price'] != 0) ? $productData['buybox_new_landed_price'] : $productData['buybox_used_landed_price']);                
                $productData['mfn_profit'] = ((is_numeric($productData['imported_price']) && $price_to_use != 0) ? 
                                                round($price_to_use - ($productData['imported_price'] + (0.08 * $productData['buybox_new_landed_price'])), 2) 
                                                    : 0);
               
                // Next, get the category info using the ProductCategoryId
                $productCategoryId = $this->apiPricing->getProductCategoryId();
                if ($productCategoryId != 0)
                    $productData = array_merge($productData, $this->process_category($productCategoryId));
           
                // Now visit the page for the buybox seller and anything else
                $product_url = $this->productSearch->getProductUrl();
                // $this->log->info($product_url);
                // $this->log->info($productData['asin']);
                $this->client->request('GET', $product_url.'?language=en_US'); // Fuckers
                $this->crawler = $this->client->waitFor('#prodDetails');
                if ($productCategoryId == 0 && $this->crawler->filter('#wayfinding-breadcrumbs_feature_div')->count()) {
                    $breadcrumbNode = $this->crawler->filter('#wayfinding-breadcrumbs_feature_div .a-list-item');
                    $category_arr = [];                    
                    $breadcrumbNode->each(function (Crawler $c) use(&$category_arr) {
                        if (strlen($c->text()) > 3) $category_arr[] = $c->text();                        
                    });                                      
                    $productData['all_categories'] = implode('/', $category_arr);
                    $productData['subcategory'] = $category_arr[count($category_arr)-1];
                }
                
                if ($this->crawler->filter('#productDescription')->count()) {
                    $product_description =  $this->crawler->filter('#productDescription')->text();
                    $productData['description'] = $product_description;
                }
                
                // $this->client->takeScreenshot('product.png'); // Yeah, screenshot!
                if ($this->crawler->filter('#buybox')->count() && $this->crawler->filter('#unqualifiedBuyBox_feature_div')->count() == 0) {
                    // $this->log->info('INSIDE buybox_seller logic');
                    $buybox_text = ''; $show_more = false;
                    if ($this->crawler->filter('#tabular-buybox-show-more:not(.aok-hidden)')->count()) {                        
                        $link_text = $this->crawler->filter('#tabular-buybox-show-more')->text(); // Should be 'Details'
                        // $this->log->info($this->crawler->filter('#tabular-buybox-show-more')->text());
                        $link = $this->crawler->filter('#tabular-buybox-show-more')->first()->selectLink($link_text)->link();
                        if ($link) {
                            $this->crawler = $this->client->click($link);                        
                            $this->client->waitFor('#tabular-buybox-side-sheet-content');                        
                            $buybox_text = $this->crawler->filter('#tabular-buybox-side-sheet-content')->text();
                            $show_more = true;
                        }
                    } else { 
                        $buybox_text = $this->crawler->filter('#buybox')->text();                        
                    }
                    if (strlen($buybox_text)) {
                        // $this->log->info($buybox_text);
                        $buybox_seller = trim($this->get_string_between($buybox_text, 'Sold by', ($show_more ? '' : 'Return policy:') ));
                        // $this->log->info($buybox_seller);
                        $productData['buybox_seller'] = $buybox_seller;
                    }
                }
                                                
            } catch (Exception $e) {
                // $this->client->takeScreenshot('product_'.$counter.'.png'); // Yeah, screenshot!
                $productData['error_msg'] = $e->getMessage();
            }
                        
            if ($this->debug) $this->log->info('Output: ', $productData);
            fputcsv($this->output, array_map(array($this, 'escapeForCsv'), $productData) );
            
            $counter++;            
        }
        
        fclose($file);
       
    }
  
    private function escapeForCsv($value)
    {
        if (strstr($value, ',') !== false)
        return '"' . str_replace(['"', '\"' ], ['""', '""'], $value) . '"';
        else return $value;
    }
    
    private function get_string_between($string, $start, $end){
        
        $startsAt = strpos($string, $start) + strlen($start);
        $endsAt = (strlen($end) ? strpos($string, $end, $startsAt) : strlen($string));
        $result = substr($string, $startsAt, $endsAt - $startsAt);
        return $result;        
    }
    
    
    private function fileExtension($s) {
        $n = strrpos($s,".");
        return ($n===false) ? "" : substr($s,$n+1);
    }
    
    public function __destruct()
    {
        
    }
}