<?php

namespace Exceedone\Exment\Services\DataImportExport\Formats\SpOut;

use Symfony\Component\Finder\SplFileInfo;
use Illuminate\Http\Request;
use Box\Spout\Reader\Common\Creator\ReaderEntityFactory;
use Box\Spout\Writer\Common\Creator\WriterEntityFactory;
 
class Xlsx extends SpOut
{
    protected $accept_extension = 'xlsx';

    public function getFormat() : string
    {
        return 'xlsx';
    }

    public function getFileName()
    {
        return $this->filebasename.date('YmdHis'). ".xlsx";
    }


    /**
     * get data table list. contains self table, and relations (if contains)
     */
    public function getDataTable($request, array $options = [])
    {
        $options = $this->getDataOptions($options);
        return $this->_getData($request, function ($spreadsheet) use ($options) {
            // if over row size, return number
            if (boolval($options['checkCount'])) {
                if (($count = $this->getRowCount($spreadsheet)) > (config('exment.import_max_row_count', 1000) + 2)) {
                    return $count;
                }
            }

            // get all data
            $datalist = [];
            foreach ($spreadsheet->getSheetNames() as $sheetName) {
                $sheet = $spreadsheet->getSheetByName($sheetName);
                $datalist[$sheetName] = $this->getDataFromSheet($sheet, 0, false, true);
            }

            return $datalist;
        });
    }

    /**
     * get data table list. contains self table, and relations (if contains)
     */
    public function getDataCount($request)
    {
        return $this->_getData($request, function ($spreadsheet) {
            return $this->getRowCount($spreadsheet);
        });
    }

    protected function _getData($request, $callback)
    {
        // get file
        if ($request instanceof Request) {
            $file = $request->file('custom_table_file');
            $path = $file->getRealPath();
        } elseif ($request instanceof SplFileInfo) {
            $path = $request->getPathName();
        } else {
            $path = $request;
        }
        
        $reader = $this->createReader();
        $spreadsheet = $reader->open($path);
        try {
            return $callback($spreadsheet);
        } finally {
        }
    }

    /**
     * Get all sheet's row count
     *
     * @param \PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet
     * @return int
     */
    protected function getRowCount($spreadsheet) : int
    {
        $count = 0;

        // get data count
        foreach ($spreadsheet->getSheetNames() as $sheetName) {
            $sheet = $spreadsheet->getSheetByName($sheetName);
            $count += intval($sheet->getHighestRow());
        }

        return $count;
    }



    /**
     * whether this out is as zip.
     * This table is parent and contains relation 1:n or n:n.
     */
    protected function isOutputAsZip()
    {
        return false;
    }
    
    protected function createWriter($spreadsheet)
    {
        return WriterEntityFactory::createXLSXWriter();
    }
    
    protected function createReader()
    {
        return ReaderEntityFactory::createXLSXReader();
    }
}
