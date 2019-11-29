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
    public $colsRow = 0;
    public $field;
    public $title;
    protected $closure = null;
    protected $detailButton = '';
    protected $detailText = '';
    protected $editText = '';
    protected $delText = '';
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
        $this->field = $field;
        $this->title = $title;
        $this->cols['field'] = $this->field;
        $this->cols['title'] = $title;
    }
    /**
     * 设置删除按钮文字
     * @Author: rocky
     * 2019/11/28 15:06
     * @param $text 文字
     */
    public function setDelText($text){
        $this->delText = $text;
    }
    /**
     * 设置详情按钮文字
     * @Author: rocky
     * 2019/11/28 15:06
     * @param $text 文字
     */
    public function setDetailText($text){
        $this->detailText = $text;
    }
    /**
     * 设置编辑按钮文字
     * @Author: rocky
     * 2019/11/28 15:06
     * @param $text 文字
     */
    public function setEditText($text){

        $this->editText = $text;

    }
    //隐藏详情按钮
    public function hideDetail()
    {
        $this->hideDetailButton = true;
    }
    //隐藏编辑按钮
    public function hideEdit()
    {
        $this->hideEditButton = true;
    }
    //隐藏删除按钮
    public function hideDel()
    {
        $this->hideDelButton = true;
    }
    /**
     * 设置单元格所占行数
     * @Author: rocky
     * 2019/7/25 16:50
     * @param $value 行数
     */
    public function rowspan($value)
    {
        $this->cols['rowspan'] = $value;
        return $this;
    }
    /**
     * 设置表头行数，用于多级表头
     * @Author: rocky
     * 2019/11/9 11:08
     * @param $row 行数
     */
    public function setColsRow($row)
    {
        $this->colsRow = $row - 1;
        return $this;
    }
    /**
     * 设置单元格所占列数
     * @Author: rocky
     * 2019/7/25 16:50
     * @param $value 列数
     */
    public function colspan($value)
    {
        $this->cols['colspan'] = $value;
        return $this;
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
        $this->detailText = $this->detailText ? $this->detailText : lang('build_view_grid_detail');
        $this->delText = $this->delText ? $this->delText : lang('build_view_grid_del');
        $this->editText = $this->editText ? $this->editText : lang('build_view_grid_edit');
        $this->detailButton = '<a class="layui-btn layui-btn-primary layui-btn-sm" lay-event="detail" ><i class="layui-icon layui-icon-about"></i>'.$this->detailText.'</a>';
        $this->delButton = '<a class="layui-btn layui-btn-danger layui-btn-sm" lay-event="del"><i class="layui-icon layui-icon-delete"></i>'.$this->delText.'</a>';
        $this->editButton = '<a class="layui-btn layui-btn-sm" lay-event="edit"><i class="layui-icon layui-icon-edit"></i>'.$this->editText.'</a>';
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
        $this->cols['minWidth'] = $width;
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