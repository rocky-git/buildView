<?php
/**
 * @Author: rocky
 * @Copyright: 广州拓冠科技 <http://my8m.com>
 * Date: 2019/7/26
 * Time: 16:43
 */


namespace buildView;


use think\exception\HttpResponseException;
use think\facade\Request;
use think\Model;
use think\model\relation\HasMany;

class Detail extends Field
{
    //模型数据
    protected $data = [];
    //数据库表字段
    protected $tableFields = [];
    //模型
    protected $model;
    //db对象
    protected $db;
    //关联字段链接
    protected $realtionMethodArr = [];
    //列
    protected $columns = [];
    //布局
    protected $layoutArr = [];
    //重新设置query条件
    protected $queryFind = false;
    //查询主键条件
    protected $id = 0;
    /**
     * Form constructor.
     * @param 模型
     */
    public function __construct($model)
    {
        if ($model instanceof Model) {
            $this->model = $model;
            $this->db = $this->model->db();
            $this->tableFields = $this->model->getTableFields();
        } else {
            abort(999, '不是有效的模型');
        }
        $id = Request::get('id', false);
        if ($id) {
            $this->id = $id;
            $this->data = $this->model->find($id);;
            if(empty($this->data)){
                throw new HttpResponseException(json(['code' => 0, 'msg' => '数据不存在！', 'data' => []]));
            }
        }
        $this->template = 'detail';
        $this->setOption('title', lang('build_view_grid_detail'));

    }

    public function view()
    {
        if (Request::isPost()) {
            $this->model->setQuery(null);
            $updateData = Request::except('id','post');
            $res = $this->model->where($this->model->getPk(), Request::post('id'))->update($updateData);
            if ($res) {
                throw new HttpResponseException(json(['code' => 1, 'msg' => lang('build_view_action_success'), 'data' => []]));
            } else {
                throw new HttpResponseException(json(['code' => 0, 'msg' => lang('build_view_action_error'), 'data' => []]));
            }
        } else {
            $html = '';
            $html .= $this->parseColumnHtml();
            foreach ($this->layoutArr as $layout) {
                call_user_func($layout['closure'], $this);
                if ($layout['type'] == 'layout') {
                    $html .= '<div class="layui-col-md' . $layout['md'] . '"><blockquote  class="layui-elem-quote think-box-shadow" style="font-size: 14px;margin-bottom: 0px;font-weight: bold">' . $layout['title'] . '</blockquote>' . $this->parseColumnHtml() . '</div>';
                }elseif($layout['type'] == 'hasMany' || $layout['type'] == 'array'){
                    $html .= '<div class="layui-col-md' . $layout['md'] . '"><blockquote  class="layui-elem-quote think-box-shadow" style="font-size: 14px;margin-bottom: 0px;font-weight: bold">' . $layout['title'] . '</blockquote>' . $this->parsehasManyHtml($layout['relationMethod']) . '</div>';
                }
            }
            $this->setOption('detail', $html);
            return $this->render();
        }
    }
    public function layout($title, $md, \Closure $closure)
    {
        array_push($this->layoutArr, ['type' => 'layout', 'title' => $title, 'md' => $md, 'closure' => $closure]);
        return $this;
    }
    public function hasManyArray($relationMethod,$title, $md,\Closure $closure)
    {
        array_push($this->layoutArr, ['type' => 'array', 'title' => $title, 'md' => $md,'relationMethod'=>$relationMethod,  'closure' => $closure]);
        return $this;
    }
    public function hasMany($relationMethod,$title, $md,\Closure $closure)
    {
        if (method_exists($this->model, $relationMethod)) {
            if($this->model->$relationMethod() instanceof HasMany){
                array_push($this->layoutArr, ['type' => 'hasMany', 'title' => $title, 'md' => $md,'relationMethod'=>$relationMethod,  'closure' => $closure]);
            }else{
                abort(100,'关联方法不是一对多');
            }
        }else{
            abort(100,'无效关联方法');
        }
        return $this;
    }

    /**
     * 解析一对多html元素
     * @Author: rocky
     * 2019/8/1 15:00
     * @param $relationMethod 一对多关联方法
     * @return string\
     */
    private function parsehasManyHtml($relationMethod)
    {
        $html = '';
        foreach ($this->columns as $column) {
            $html .= '<th >' . $column->title . '</th>';
        }
        $html = '<thead><tr>'.$html.'</tr></thead>';
        $tr = '';
        foreach ($this->data->$relationMethod as $val){
            $td = '';
            foreach ($this->columns as $column) {
                $column->setData($val);
                $td .= '<td >'  . $column->render() . '</td>';
            }
            $tr .= '<tr>'.$td.'</tr>';
        }
        $html .= $tr;
        $html = '<table class="layui-table" lay-skin="line" style="margin-top:0px ">' . $html . '</table>';
        $this->columns = [];
        return $html;
    }
    /**
     * 解析html元素
     * @Author: rocky
     * 2019/8/1 13:52
     * @return string
     */
    private function parseColumnHtml()
    {
        $html = '';
        foreach ($this->columns as $column) {
            $column->setData($this->data);
            if(empty($column->title)){
                $html .= '<tr><td >' .  $column->render() . '</td></tr>';
            }else{
                $html .= '<tr><td >' . $column->title . '：' . $column->render() . '</td></tr>';
            }
        }
        $html = '<table class="layui-table" lay-size="lg" style="margin-top:0px ">' . $html . '</table>';
        $this->columns = [];
        return $html;
    }

    /**
     * 获取模型当前数据
     * @Author: rocky
     * 2019/8/22 14:56
     * @return array|mixed
     */
    public function getModelData(){
        return $this->data;
    }
    //获取当前模型
    public function model()
    {
        $this->queryFind = true;
        return $this->db->getModel();
    }

    /**
     * 设置标题
     * @param $title 设置标题
     */
    public function setTitle($title)
    {
        $this->setOption('title', $title);
    }

    public function __call($name, $arguments)
    {
        array_push($this->realtionMethodArr, $name);
        if (count($arguments) > 0) {
            $label = array_shift($arguments);
            $name = implode('.', $this->realtionMethodArr);
            $this->realtionMethodArr = [];
            return $this->column($name, $label);
        }
        return $this;
    }



    /**
     * 设置列
     * @Author: rocky
     * 2019/7/25 16:20
     * @param $field 字段
     * @param $label 标签
     * @return Column
     */
    public function column($field, $label)
    {
        if($this->queryFind){
            $this->queryFind = false;
            $this->data = $this->db->find($this->id);
        }
        $column = new Column($field, $label);
        array_push($this->columns, $column);
        return $column;
    }
}