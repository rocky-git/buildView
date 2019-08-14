<?php
/**
 * Created by PhpStorm.
 * User: rocky
 * Date: 2019-08-11
 * Time: 11:24
 */

namespace buildView;


use think\Db;
use think\db\Query;
use think\exception\HttpResponseException;
use think\facade\Request;

/**
 * Class Echarts
 * @package buildView
 * @method $this count($text) 统计数量
 * @method $this max($text, $filed) 统计最大值
 * @method $this avg($text, $field) 统计平均值
 * @method $this sum($text, $field) 统计总和
 * @method $this min($text, $field) 统计最小值
 */
class Echarts extends Field
{
    //标题
    protected $title = '';
    protected $text = '';
    //日期字段
    protected $dateField = '';
    //db对象
    protected $db;
    //克隆Db对象
    protected $clone_db = null;
    //过滤器
    protected $filter = null;
    //图表类型
    protected $type;
    //统计数据结果
    protected $data = [
        'totalArr' => [],
    ];
    protected $headerAnalyze = [];
    protected $charts = [];
    protected $chartTable = '';

    public function __construct()
    {

        $this->template = 'echarts';
    }

    /**
     * 创建图表
     * @param $model 模型或表名或db对象
     * @param $title 标题
     * @param string $type 图表类型
     * @param string $dateField 日期字段
     */
    public function create($model, $title, $filter, $type = 'line', $dateField = 'create_at')
    {
        $this->clone_db = null;
        if ($model instanceof Model) {
            $db = $this->model->db();
        } elseif ($model instanceof Query) {
            $db = $model;
        } else {
            $db = Db::name($model);
        }
        $this->db = $db;
        if (empty($this->chartTable)) {
            $this->chartTable = Request::get('chartTable', false);
        }
        if ($this->chartTable == $db->getTable()) {
            $this->chartTable = Request::get('chartTable');
            $this->charts[$db->getTable()] = [
                'db' => $db,
                'title' => $title,
                'type' => $type,
                'dateField' => $dateField
            ];
            if (array_key_exists($this->chartTable, $this->charts)) {
                $this->db = $this->charts[$this->chartTable]['db'];
                $this->title = $this->charts[$this->chartTable]['title'];
                $this->dateField = $this->charts[$this->chartTable]['dateField'];
                $this->type = $this->charts[$this->chartTable]['type'];
                if ($filter instanceof \Closure) {
                    $this->filter = new Filter($this->db);
                    $this->filter->setRequest('post');
                    call_user_func($filter, $this->filter);
                    $this->db = $this->filter->db();
                }
            }
        } else {
            if (empty($this->chartTable)) {
                $this->chartTable = $db->getTable();
                if ($filter instanceof \Closure) {
                    $this->filter = new Filter($this->db);
                    $this->filter->setRequest('post');
                    call_user_func($filter, $this->filter);
                    $this->db = $this->filter->db();
                }
            }
            $this->db = $db;
            $this->title = $title;
            $this->dateField = $dateField;
            $this->type = $type;

        }

        return $this;
    }

    public function setHeader($text, $type, $field, $db_callback = null)
    {
        $this->model();
        if ($db_callback instanceof \Closure) {
            call_user_func($db_callback, $this->model());
        }
        $where = $this->db->getOptions('where');

        $table = $this->db->getTable();
        $this->headerAnalyze[] = [
            'table' => $this->db->getTable(),
            'text' => $text,
            'todayCount' => Db::name($table)->setOption('where', $where)->whereTime($this->dateField, 'd')->$type($field),
            'count' => Db::name($table)->setOption('where', $where)->$type($field)
        ];
        $this->setOption('headerAnalyze', $this->headerAnalyze);
        return $this;
    }


    public function __call($name, $arguments)
    {

        $this->model();
        if (Request::isPost() && $this->chartTable == $this->db->getTable()) {

            if (end($arguments) instanceof \Closure) {
                $callback = array_pop($arguments);
                call_user_func($callback, $this->db);
            }
            if (count($arguments) > 1) {
                $this->text = array_shift($arguments);
                array_unshift($arguments, $name);
                call_user_func_array([$this, 'analyze'], $arguments);

            } else {
                $this->text = $arguments[0];
                $this->analyze($name, '*');
            }
        }
        return $this;
    }

    /**
     * 返回db
     * @return \think\db\Query|null
     */
    public function model()
    {
        if (is_null($this->clone_db)) {
            $this->clone_db = clone $this->db;
        } else {
            $this->db = clone $this->clone_db;
        }
        return $this->db;
    }

    /**
     * 返回视图
     * @return string
     */
    public function view()
    {

        if (Request::isPost()) {
            throw new HttpResponseException(json([
                'code' => 200,
                'data' => $this->data,
            ]));
        } else {
            if (!is_null($this->filter)) {
                $this->setOption('filter', $this->filter->render());
            }
            $md = 12 / count($this->headerAnalyze);
            $this->setOption('md', $md);
            return $this->render();
        }

    }

    /**
     * 分析图表计算
     * @param $type 统计类型
     * @param $field 统计字段
     */
    private function analyze($type, $field)
    {
        $post = Request::post();
        $data = [];
        $data['title'] = $this->title;
        $countArr = [];
        $where = $this->db->getOptions('where');
        $table = $this->db->getTable();
        switch ($post['datetype']) {
            case 'today':
                $dates = $this->get_week();
                $data['dateArr'] = $dates;
                foreach ($dates as $key => $date) {
                    $count = Db::name($table)->setOption('where', $where)->whereBetween($this->dateField, ["{$date} 00:00:00", "{$date} 23:59:59"])->$type($field);
                    array_push($countArr, $count);
                }
                $toDay = date('Y-m-d');
                $yesterday = date("Y-m-d",strtotime("-1 day"));
                $weekCount = Db::name($table)->setOption('where', $where)->whereBetween($this->dateField, ["{$toDay} 00:00:00", "{$toDay} 23:59:59"])->$type($field);
                $lastWeekCount = Db::name($table)->setOption('where', $where)->whereBetween($this->dateField, ["{$yesterday} 00:00:00", "{$yesterday} 23:59:59"])->$type($field);
                $weekCountPercent = $this->computePercent($weekCount, $lastWeekCount);
                $this->data['totalArr'] = array_merge($this->data['totalArr'], [
                    [
                        'text' => '今天' . $this->text,
                        'count' => $weekCount,
                    ],
                    [
                        'text' => '昨天' . $this->text,
                        'count' => $lastWeekCount,
                    ],
                    [
                        'text' => '同比昨天' . $this->text,
                        'count' => $weekCountPercent,
                    ]
                ]);

                break;
            case 'week':
                $dates = $this->get_week();
                $data['dateArr'] = $dates;
                foreach ($dates as $key => $date) {
                    $count = Db::name($table)->setOption('where', $where)->whereBetween($this->dateField, ["{$date} 00:00:00", "{$date} 23:59:59"])->$type($field);
                    array_push($countArr, $count);
                }
                $weekCount = Db::name($table)->setOption('where', $where)->whereTime($this->dateField, 'week')->$type($field);
                $lastWeekCount = Db::name($table)->setOption('where', $where)->whereTime($this->dateField, 'last week')->$type($field);
                $weekCountPercent = $this->computePercent($weekCount, $lastWeekCount);
                $this->data['totalArr'] = array_merge($this->data['totalArr'], [
                    [
                        'text' => '本周' . $this->text,
                        'count' => $weekCount,
                    ],
                    [
                        'text' => '上周' . $this->text,
                        'count' => $lastWeekCount,
                    ],
                    [
                        'text' => '同比上周' . $this->text,
                        'count' => $weekCountPercent,
                    ]
                ]);
                break;
            case 'month':
                $dates = $this->getMonth();
                $data['dateArr'] = $dates;
                foreach ($data['dateArr'] as $key => $date) {
                    $data['dateArr'][$key] = ($key + 1) . '日';
                }
                foreach ($dates as $key => $date) {
                    $count = Db::name($table)->setOption('where', $where)->whereBetween($this->dateField, ["{$date} 00:00:00", "{$date} 23:59:59"])->$type($field);
                    array_push($countArr, $count);
                }
                $weekCount = Db::name($table)->setOption('where', $where)->whereTime($this->dateField, 'month')->$type($field);
                $lastWeekCount = Db::name($table)->setOption('where', $where)->whereTime($this->dateField, 'last month')->$type($field);
                $weekCountPercent = $this->computePercent($weekCount, $lastWeekCount);
                $this->data['totalArr'] = array_merge($this->data['totalArr'], [
                    [
                        'text' => '本月' . $this->text,
                        'count' => $weekCount,
                    ],
                    [
                        'text' => '上月' . $this->text,
                        'count' => $lastWeekCount,
                    ],
                    [
                        'text' => '同比上月' . $this->text,
                        'count' => $weekCountPercent,
                    ]
                ]);
                break;
            case 'quarter':
                $month = date('m');
                if ($month == 1 || $month == 2 || $month == 3) {
                    $whereLastDate = [date('Y-10-01', strtotime("-1 year")), date('Y-12-31', strtotime("-1 year"))];
                    $whereDate = [date('Y-01-01'), date('Y-03-31')];
                } elseif ($month == 4 || $month == 5 || $month == 6) {
                    $whereLastDate = [date('Y-01-01'), date('Y-03-31')];
                    $whereDate = [date('Y-04-01'), date('Y-06-30')];
                } elseif ($month == 7 || $month == 8 || $month == 9) {
                    $whereLastDate = [date('Y-04-01'), date('Y-06-30')];
                    $whereDate = [date('Y-07-01'), date('Y-09-30')];
                } else {
                    $whereLastDate = [date('Y-07-01'), date('Y-09-30')];
                    $whereDate = [date('Y-10-01'), date('Y-12-31')];
                }
                $weekCount = Db::name($table)->setOption('where', $where)->whereTime($this->dateField, $whereDate)->$type($field);
                $lastWeekCount = Db::name($table)->setOption('where', $where)->whereTime($this->dateField, $whereLastDate)->$type($field);
                $weekCountPercent = $this->computePercent($weekCount, $lastWeekCount);
                $this->data['totalArr'] = array_merge($this->data['totalArr'], [
                    [
                        'text' => '本季' . $this->text,
                        'count' => $weekCount,
                    ],
                    [
                        'text' => '上季' . $this->text,
                        'count' => $lastWeekCount,
                    ],
                    [
                        'text' => '同比上季' . $this->text,
                        'count' => $weekCountPercent,
                    ]
                ]);
                $count = Db::name($table)->setOption('where', $where)->whereTime($this->dateField, [date('Y-01-01'), date('Y-03-31')])->$type($field);
                $data['dateArr'][] = '第1季';
                array_push($countArr, $count);
                $count = Db::name($table)->setOption('where', $where)->whereTime($this->dateField, [date('Y-04-01'), date('Y-06-30')])->$type($field);

                $data['dateArr'][] = '第2季';
                array_push($countArr, $count);

                $count = Db::name($table)->setOption('where', $where)->whereTime($this->dateField, [date('Y-07-01'), date('Y-09-30')])->$type($field);
                $data['dateArr'][] = '第3季';
                array_push($countArr, $count);

                $count = Db::name($table)->setOption('where', $where)->whereTime($this->dateField, [date('Y-10-01'), date('Y-12-31')])->$type($field);
                $data['dateArr'][] = '第4季';
                array_push($countArr, $count);
                break;
            case 'year':
                $weekCount = Db::name($table)->setOption('where', $where)->whereTime($this->dateField, 'year')->$type($field);

                $lastWeekCount = Db::name($table)->setOption('where', $where)->whereTime($this->dateField, 'last year')->$type($field);

                $weekCountPercent = $this->computePercent($weekCount, $lastWeekCount);
                $this->data['totalArr'] = array_merge($this->data['totalArr'], [
                    [
                        'text' => '今年' . $this->text,
                        'count' => $weekCount,
                    ],
                    [
                        'text' => '往年' . $this->text,
                        'count' => $lastWeekCount,
                    ],
                    [
                        'text' => '同比往年' . $this->text,
                        'count' => $weekCountPercent,
                    ]
                ]);
                for ($i = 1; $i <= 12; $i++) {
                    $data['dateArr'][] = $i . '月';
                    $todayNum = date("t", strtotime(date('Y-m')));
                    $count = Db::name($table)->setOption('where', $where)->whereTime($this->dateField, [date("Y-{$i}-01"), date("Y-{$i}-{$todayNum}")])->$type($field);
                    array_push($countArr, $count);
                }
                break;
            case 'range':
                $data['totalArr'] = [];
                $dates = explode(' - ', $post['daterange']);
                $startDate = $dates[0];
                $endDate = $dates[1];
                $data['dateArr'] = $this->prDates($startDate, $endDate);
                foreach ($data['dateArr'] as $key => $date) {
                    $count = Db::name($table)->setOption('where', $where)->whereBetween($this->dateField, ["{$date} 00:00:00", "{$date} 23:59:59"])->$type($field);
                    array_push($countArr, $count);
                }
                break;
        }

        $this->data['series'][] = [
            'name' => $this->text,
            'type' => $this->type,
            'data' => $countArr,
        ];

        $this->data = array_merge($this->data, $data);
    }

    /**
     * 算出百分比
     * @param $count 现在的统计
     * @param $lastCount 旧的统计
     */
    private function computePercent($count, $lastCount)
    {
        if ($lastCount == 0) {
            $countPercent = '0%';
        } else {
            $countPercent = (($count - $lastCount) / $lastCount * 100);
            $countPercent = round($countPercent, 1) . '%';
        }


        return $countPercent;

    }

    /**
     * 获取本月所有日期
     * @Author: rocky
     * 2019/6/26
     **/
    private function getMonth()
    {
        $j = date("t"); //获取当前月份天数
        $start_time = strtotime(date('Y-m-01'));  //获取本月第一天时间戳
        $array = array();
        for ($i = 0; $i < $j; $i++) {
            $array[] = date('Y-m-d', $start_time + $i * 86400); //每隔一天赋值给数组
        }
        return $array;
    }

    /**
     * 输入两个日期，把这两个日期之间的所有日期取出来
     * @Author: rocky
     * 2019/6/26
     **/
    private function prDates($start, $end)
    {
        $dt_start = strtotime($start);
        $dt_end = strtotime($end);
        $date = [];
        while ($dt_start <= $dt_end) {
            array_push($date, date('Y-m-d', $dt_start));
            $dt_start = strtotime('+1 day', $dt_start);
        }
        return $date;
    }

    /**
     * 获取本周所有日期
     */
    private function get_week($time = '', $format = 'Y-m-d')
    {
        $time = $time != '' ? $time : time();
        //获取当前周几
        $week = date('w', $time);
        $date = [];
        for ($i = 0; $i <= 6; $i++) {
            $date[$i] = date($format, strtotime('+' . $i - $week + 1 . ' days', $time));
        }
        return $date;
    }

    /**
     * 数字转万
     * @Author: rocky
     * 2019/6/26
     **/
    private function numberToW($num)
    {
        $num >= 10000 ? $num / 10000 . '万' : $num;
        return $num;
    }
}