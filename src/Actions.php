<?php
/**
 * @Author: rocky
 * @Copyright: 广州拓冠科技 <http://my8m.com>
 * Date: 2019/7/25
 * Time: 15:10
 */


namespace buildView;


class Actions
{
    //每行数据
    protected $data = [];
    //html
    protected $html = '';
    //列宽度
    protected $width = '';
    public $columnHtml = '';
    public $field;
    public $title;
    protected $closure = null;
    protected $detailButton = '';
    protected $editButton = '';
    protected $delButton = '';
    protected $hideDetailButton = false;
    protected $hideEditButton = false;
    protected $hideDelButton = false;
    protected $prependArr =[];
    protected $appendArr = [];
    public $cols = [
        'align' => 'center'
    ];
    public function __construct($field, $title)
    {
        $this->detailButton = '<a class="layui-btn layui-btn-primary layui-btn-sm" lay-event="detail" ><i class="layui-icon layui-icon-about"></i>'.lang('build_view_grid_detail').'</a>';
        $this->delButton = '<a class="layui-btn layui-btn-danger layui-btn-sm" lay-event="del"><i class="layui-icon layui-icon-delete"></i>'.lang('build_view_grid_del').'</a>';
        $this->editButton = '<a class="layui-btn layui-btn-sm" lay-event="edit"><i class="layui-icon layui-icon-edit"></i>'.lang('build_view_grid_edit').'</a>';
        $this->field = $field;
        $this->title = $title;
        $this->cols['field'] = $this->field;
        $this->cols['title'] = $title;
    }

    public function hideDetail()
    {
        $this->hideDetailButton = true;
    }

    public function hideEdit()
    {
        $this->hideEditButton = true;
    }

    public function hideDel()
    {
        $this->hideDelButton = true;
    }

    //设置数据
    public function setData($data)
    {
        $this->data = $data;
        $this->hideDetailButton = false;
        $this->hideEditButton = false;
        $this->hideDelButton = false;

    }


    /**
     * 设置对齐方式
     * @Author: rocky
     * 2019/7/25 16:50
     * @param $value 对齐方式
     */
    public function align($value)
    {
        $this->cols['align'] = $value;
        return $this;
    }

    /**
     * 设置样式
     * @Author: rocky
     * 2019/7/25 16:50
     * @param $value 对齐方式
     */
    public function style($style)
    {
        $this->cols['style'] = $style;
        return $this;
    }

    /**
     * 设置宽度
     * @Author: rocky
     * 2019/7/25 16:50
     * @param $value 宽度
     */
    public function width($val)
    {
        $this->cols['width'] = $val;
        return $this;
    }

    public function setClosure(\Closure $closure)
    {
        $this->closure = $closure;
    }


    public function render()
    {
        $width = 0;
        if (!is_null($this->closure)) {
            call_user_func_array($this->closure, [$this, $this->data]);
        }

        if (!$this->hideDetailButton) {
            $width+=100;
            $this->columnHtml .= $this->detailButton;
        }
        if (!$this->hideEditButton) {
            $width+=100;
            $this->columnHtml .= $this->editButton;
        }
        if (!$this->hideDelButton) {
            $width+=100;
            $this->columnHtml .= $this->delButton;
        }
        foreach ($this->prependArr as $val){
            $width+=100;
            $this->columnHtml  = $val . $this->columnHtml;
        }
        foreach ($this->appendArr as $val){
            $width+=100;
            $this->columnHtml .= $val;
        }
        $this->cols['width'] = $width;
        $this->html = $this->columnHtml;
        $this->columnHtml = '';
        $this->prependArr =[];
        $this->appendArr = [];
        return $this->html;
    }
    public function prepend($val){
        if($val instanceof Button){
            $this->prependArr[] = $val->render();


        }else{
            $this->prependArr[] = $val;
        }
    }
    public function append($val){
        if($val instanceof Button){
            $this->appendArr[] = $val->render();
        }else{
            $this->appendArr[] = $val;
        }
    }
}