<?php

namespace Michael\AmazonSellerApi\libraries;

/**  Define a Read Filter class implementing PHPExcel_Reader_IReadFilter  */
class PhpSpreadsheetSingleRowFilter implements \PhpOffice\PhpSpreadsheet\Reader\IReadFilter
{
    private $requestedRow;
    
    public function __construct($row) {
        $this->requestedRow = $row;
    }
    
    public function readCell($column, $row, $worksheetName = '') {
        if ($row == $this->requestedRow) {
            return true;
        }
        return false;
    }
} 
