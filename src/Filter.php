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
    public function __construct($model)
    {
        $this->filter = new \app\common\tools\Filter($model);
    }

    /**
     * like筛选
     * @Author: rocky
     * 2019/7/25 16:45
     * @param $lable 标签
     * @param $field 字段
     * @return $this
     */
    public function like($lable, $field)
    {

        $field = $this->paseFilter($field,'like');
        $this->template($lable, $field);
        return $this;
    }
    protected function template($lable, $field, $template = 'text')
    {
        $field = new Field($template, $lable, $field, Request::param($field));
        $field->layui('inline');
        array_push($this->formItem, $field);
        return $field;
    }
    public function db(){
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
    public function dateBetween($lable, $field){
        $field = $this->paseFilter($field,'dateBetween');
        $this->template($lable, $field,'dateRange');
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
    public function in($lable, $field){

        $field = $this->paseFilter($field,'in');
        $this->template($lable, $field);
        return $this;
    }
    protected function paseFilter($field,$method){
        if(is_string($field)){
            $fields = explode('.',$field);
            $field = end($fields);
            if(count($fields) > 1){
                $field = '_'.$field;
                $this->filter->relationWhere($fields[0],function ($q) use ($field,$method){
                    $q->$method($field);
                });
            }else{
                $this->filter->$method($field);
            }
        }
        if(is_array($field)){
            foreach ($field as $val){
                $this->filter->$method($val);
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
    public function eq($lable, $field)
    {
        $field = $this->paseFilter($field,'eq');
        $this->template($lable, $field);
        return $this;
    }

    public function radio($options){
        $item = end($this->formItem);
        $item->template = __FUNCTION__;
        $item->options($options);
        return $this;
    }
    public function select($options){
        $item = end($this->formItem);
        $item->template = __FUNCTION__;
        $item->options($options);
        return $this;
    }
    public function checkbox($options){
        $item = end($this->formItem);
        $item->template = __FUNCTION__;
        $item->options($options);
        return $this;
    }
    public function multiple($bool=true){
        $item = end($this->formItem);
        $item->multiple(true);
        return $this;
    }
    public function date(){
        $item = end($this->formItem);
        $item->template = __FUNCTION__;
        return $this;
    }
    public function time(){
        $item = end($this->formItem);
        $item->template = __FUNCTION__;
        return $this;
    }
    public function datetime(){
        $item = end($this->formItem);
        $item->template = __FUNCTION__;
        return $this;
    }
    public function dateRange(){
        $item = end($this->formItem);
        $item->template = __FUNCTION__;
        return $this;
    }
    public function datetimeRange(){
        $item = end($this->formItem);
        $item->template = __FUNCTION__;
        return $this;
    }
    public function timeRange(){
        $item = end($this->formItem);
        $item->template = __FUNCTION__;
        return $this;
    }
    public function render()
    {
        $html = '';
        foreach ($this->formItem as $form) {
            $html .= ' <div class="layui-inline"">' . $form->render() . '</div>';
        }
        $content = file_get_contents(__DIR__ . '/view/filter.html');
        return View::display($content, ['filter' => $html]);
    }
}