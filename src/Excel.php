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
use think\facade\Db;

class Excel
{
    private static function getLetter($i)
    {

        $letter = array('A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z');
        if ($i > count($letter) - 1) {
            if($i > 51){
                $num = ceil($i / 25);
            }else{
                $num = round($i / 25);
            }
            $j = $i % 26;

            $str = $letter[$num-1].$letter[$j];

            return $str;
        } else {
            return $letter[$i];
        }
    }
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
    public static function export($title, $columnTitle, $data, $callback = '')
    {
        set_time_limit(0);
        ini_set('memory_limit', '-1');
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
                $worksheet->setCellValueByColumnAndRow($i, $key + 2, self::filterEmoji($val[$fkey]));
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
            $worksheet->getColumnDimension(self::getLetter($i))->setWidth($width);
            $i++;
        }
        $row = count($data) + 1;
        $letter = self::getLetter(count($columnTitle)-1);
        $worksheet->getStyle("A1:{$letter}{$row}")->applyFromArray($styleArray);
        $filename = $title . date('_YmdHi') . '分.xls';
        ob_end_clean();
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        $writer = IOFactory::createWriter($PHPExcel, 'Xls');
        $writer->save('php://output');
        exit;
    }

    /**
     * Excel导入数据库
     * @Author: rocky
     * 2019/9/6 18:49
     * @param $filename 文件路径
     * @param $table 数据库表名
     * @param $columnFields 字段 ['title','content']
     * @param int $sheet 第几个工作表 默认第一个
     * @param int $rowIndex 第几行开始 默认第二行
     * @param int $cellIndex 第几列开始 默认第一列
     * @param null $rowCount 多少行 默认全部 如果是数组指定第哪几行 [3,5]
     * @param null $callback 回调方法-每行数据
     * @return bool
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Reader\Exception
     */
    public static function inport($filename, $table, $columnFields, $sheet = 0, $rowIndex = 2, $cellIndex = 1, $rowCount = null,$callback=null)
    {
        $Excel = IOFactory::load($filename);
        $excel_array = $Excel->getSheet($sheet)->toArray();
        $data = [];
        $excel_data = [];
        if(is_array($rowCount)){
            $excel_array = array_slice($excel_array, $rowIndex - 1, null);
            foreach ($rowCount as $key=>$value){
                array_push($excel_data,$excel_array[$value-1]);
            }
            $excel_array = $excel_data;
        }else{
            $excel_array = array_slice($excel_array, $rowIndex - 1, $rowCount);
        }
        foreach ($excel_array as $key => $value) {
            $rowData = [];
            $cell = $cellIndex - 1;
            foreach ($columnFields as $k => $field) {
                $rowData[$field] = $value[$cell];
                $cell++;
            }
			if ($callback instanceof \Closure) {
                $rowData = call_user_func($callback, $rowData);
            }
            array_push($data, $rowData);
        }
        Db::name($table)->limit(100)->insertAll($data);
        return true;
    }

    private static function filterEmoji($str)
    {
        $str = preg_replace_callback('/./u',
            function (array $match) {
                return strlen($match[0]) >= 4 ? '' : $match[0];
            },
            $str);
        return $str;
    }
}