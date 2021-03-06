<?php
/**
 * Created by PhpStorm.
 * User: rocky
 * Date: 2019-07-22
 * Time: 23:41
 */

namespace buildView;


use think\facade\Request;
use think\facade\View;

class Filter
{
    public $formItem = [];
    protected $filter;
    protected $requestMethod = 'get';
    public function __construct($model)
    {
        $this->filter = new FilterSearch($model);
    }
    public function setRequest($request){
        $this->requestMethod = $request;
    }
    /**
     * like筛选
     * @Author: rocky
     * 2019/7/25 16:45
     * @param $lable 标签
     * @param $field 字段
     * @return $this
     */
    public function like($field, $lable)
    {

        $field = $this->paseFilter($field, 'like');
        $this->template( $field,$lable);
        return $this;
    }
	public function xselect($options)
    {
        $item = end($this->formItem);
        $item->template = __FUNCTION__;
        $item->options($options);
        return $this;
    }
    /**
     * between筛选
     * @Author: rocky
     * 2019/7/25 16:46
     * @param $lable 标签
     * @param $field 字段
     * @return $this
     */
    public function between($field, $lable){
        $field = $this->paseFilter($field, 'between');
        $this->template( [$field.'_start',$field.'_end'],$lable,'betweenText');
        return $this;
    }
    /**
     * findIn筛选
     * @Author: rocky
     * 2019/7/25 16:46
     * @param $lable 标签
     * @param $field 字段
     * @return $this
     */
    public function findIn($field, $lable)
    {
        $field = $this->paseFilter($field, 'findIn');
        $this->template( $field,$lable);
        return $this;
    }
    protected function template($field, $lable, $template = 'text')
    {
        if($template == 'betweenText'){
            $params = [Request::param($field[0]),Request::param($field[1])];
        }else{
            $params = Request::param($field);
        }
        $field = new Field($template, $lable, $field, $params);
        $field->layui('inline');
        array_push($this->formItem, $field);
        return $field;
    }
    public function db()
    {
        return $this->filter->query();
    }
    /**
     * 日期区间筛选
     * @Author: rocky
     * 2019/7/25 16:46
     * @param $lable 标记
     * @param $field 字段
     * @return $this
     */
    public function dateBetween($field, $lable)
    {
        $field = $this->paseFilter($field, 'dateBetween');
        $this->template($field, $lable, 'dateRange');
        return $this;
    }
    /**
     * in筛选
     * @Author: rocky
     * 2019/7/25 16:46
     * @param $lable 标签
     * @param $field 字段
     * @return $this
     */
    public function in($field, $lable)
    {

        $field = $this->paseFilter($field, 'in');
        $this->template( $field,$lable);
        return $this;
    }

    protected function paseFilter($field, $method)
    {
        if (is_string($field)) {
            $fields = explode('.', $field);
            $field = end($fields);
            if (count($fields) > 1) {
				$field = implode('-',$fields);
                $this->filter->relationWhere($fields[0], function ($q) use ($field, $method) {
                    $q->$method($field,$this->requestMethod);
                });
            } else {
                $this->filter->$method($field,$this->requestMethod);
            }
        }
        if (is_array($field)) {
            foreach ($field as $val) {
                $this->filter->$method($val,$this->requestMethod);
            }
        }

        return $field;
    }

    /**
     * 等于筛选
     * @Author: rocky
     * 2019/7/25 16:47
     * @param $lable 标记
     * @param $field 字段
     * @return $this
     */
    public function eq($field, $lable)
    {
        $field = $this->paseFilter($field, 'eq');
        $this->template( $field,$lable);
        return $this;
    }

    public function radio($options)
    {
        $item = end($this->formItem);
        $item->template = __FUNCTION__;
        $item->options($options);
        return $this;
    }

    public function select($options)
    {
        $item = end($this->formItem);
        $item->template = __FUNCTION__;
        $item->options($options);
        return $this;
    }
    public function selectGroup($options)
    {
        $item = end($this->formItem);
        $item->template = __FUNCTION__;
        $item->options($options);
        return $this;
    }
    public function checkbox($options)
    {
        $item = end($this->formItem);
        $item->template = __FUNCTION__;
        $item->options($options);
        return $this;
    }

    public function multiple($bool = true)
    {
        $item = end($this->formItem);
        $item->multiple(true);
        return $this;
    }

    public function date()
    {
        $item = end($this->formItem);
        $item->template = __FUNCTION__;
        return $this;
    }

    public function time()
    {
        $item = end($this->formItem);
        $item->template = __FUNCTION__;
        return $this;
    }

    public function datetime()
    {
        $item = end($this->formItem);
        $item->template = __FUNCTION__;
        return $this;
    }

    public function dateRange()
    {
        $item = end($this->formItem);
        $item->template = __FUNCTION__;
        return $this;
    }

    public function datetimeRange()
    {
        $item = end($this->formItem);
        $item->template = __FUNCTION__;
        return $this;
    }

    public function timeRange()
    {
        $item = end($this->formItem);
        $item->template = __FUNCTION__;
        return $this;
    }

    public function render()
    {
        $html = '';
        $gets = request()->get();
        
        foreach ($gets as $key=>$val){
            $html .= '<input type="hidden" name="'.$key.'" value="'.$val.'" />';
        }
        foreach ($this->formItem as $form) {
            $html .= ' <div class="layui-inline"">' . $form->render() . '</div>';
        }
        $content = file_get_contents(__DIR__ . '/view/filter.html');
        return View::display($content, ['filter' => $html]);
    }
}