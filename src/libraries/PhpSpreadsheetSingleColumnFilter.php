<?php

namespace Michael\AmazonSellerApi\libraries;

/**  Define a Read Filter class implementing PHPExcel_Reader_IReadFilter  */
class PhpSpreadsheetSingleColumnFilter implements \PhpOffice\PhpSpreadsheet\Reader\IReadFilter
{
    private $requestedColumn;
    
    public function __construct($column) {
        $this->requestedColumn = $column;
    }
    
    public function readCell($column, $row, $worksheetName = '') {
        if ($column == $this->requestedColumn) {
            return true;
        }
        return false;
    }
}
