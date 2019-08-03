<?php
/**
 * @Author: rocky
 * @Copyright: 广州拓冠科技 <http://my8m.com>
 * Date: 2019/7/18
 * Time: 10:53
 */


namespace buildView;


use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use think\Collection;

class Excel
{
    /**
     * 导出excel表格
     * @Author: rocky
     * 2019/7/18 15:02
     * @param $title 标题
     * @param array $columnTitle 表头标题-格式['test'=>'测试']
     * @param array $data 二维数组
     * @param $callback 回调方法-转换的数据
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     */
    public static function exprot($title, $columnTitle, $data, $callback)
    {
        $letter = array('A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z');
        $PHPExcel = new Spreadsheet();
        $worksheet = $PHPExcel->getActiveSheet();
        $worksheet->setTitle($title);
        $i = 0;
        foreach ($columnTitle as $field => $val) {
            $i++;
            $worksheet->setCellValueByColumnAndRow($i, 1, $val);
        }
        $i = 1;
        foreach ($data as $key => &$val) {
            if ($callback instanceof \Closure) {
                $val = call_user_func($callback, $val);
            }
            foreach ($columnTitle as $fkey => $fval) {
                $worksheet->setCellValueByColumnAndRow($i, $key + 2, $val[$fkey]);
                $i++;
            }
            $i = 1;
        }
        $styleArray = [
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT,
            ],
        ];
        $i = 0;

        $collectionData = Collection::make($data);
        foreach ($columnTitle as $field => $vals) {
            $values = $collectionData->column($field);
            $str = $vals;
            foreach ($values as $v) {
                if (mb_strlen($v) > mb_strlen($str)) {
                    $str = $v;
                }
            }
            $width = ceil(mb_strlen($str) * 3);
            $worksheet->getColumnDimension($letter[$i])->setWidth($width);
            $i++;
        }
        $row = count($data) + 1;
        $worksheet->getStyle("A1:{$letter[count($columnTitle)-1]}{$row}")->applyFromArray($styleArray);
        $filename = "{$title}导出" . date('_YmdHi') . '分.xls';
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        $writer = IOFactory::createWriter($PHPExcel, 'Xls');
        $writer->save('php://output');
        exit;
    }

}