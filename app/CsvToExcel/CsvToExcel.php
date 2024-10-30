<?php

namespace App\CsvToExcel;

use Illuminate\Support\Str;

class CsvToExcel
{
    public function exportCsv($params)
    {
        $columns = $params['columns'];
        $query = &$params['query'];
        $exportFilename = $params['export_filename'];
        $exportDirectory = isset($params['export_directory']) ? $params['export_directory'] : '../storage/app/public/export/' . Str::uuid();

        $old = umask(0);
        mkdir($exportDirectory, 0777, true);
        umask($old);

        $file = fopen($exportDirectory . '/' . $exportFilename . '.csv', 'w+');
        fputcsv($file, $columns);
        $query->cursor()
            ->each(function ($data) use ($file) {
                $data = $data->toArray();
                fputcsv($file, $data);
            });
        fclose($file);
        $cmd = <<<CMD
            sed -i 's/\\\"/""/g' $exportDirectory/${exportFilename}.csv
        CMD;
        exec($cmd);
        chmod($exportDirectory. '/'. $exportFilename. '.csv', 0666);
        return [
            'export_directory' => $exportDirectory,
            'export_filename' => $exportFilename . '.csv'
        ];
    }


    public function exportExcel($params)
    {
        $exportFileInfo = $this->exportCsv($params);
        $exportDirectory = $exportFileInfo['export_directory'];
        $exportFilename = $exportFileInfo['export_filename'];
        $cmd = "cd $exportDirectory && export HOME=/tmp && soffice --headless --convert-to xlsx:'Calc MS Excel 2007 XML' ${exportFilename}";
        exec($cmd);
        $exportFilename = str_replace('.csv', '.xlsx', $exportFilename);
        return [
            'export_directory' => $exportDirectory,
            'export_filename' => $exportFilename
        ];
    }

}
