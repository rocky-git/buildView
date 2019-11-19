<?php
/**
 * @Author: rocky
 * @Copyright: 广州拓冠科技 <http://my8m.com>
 * Date: 2019/7/25
 * Time: 15:10
 */


namespace buildView;


class Column
{
    //每行数据
    protected $data = [];
    //当前列字段数据
    public $value = '';
    //html
    protected $html = '';
    //列宽度
    protected $width = '';
    public $columnHtml = '';
    public $excelData = '';
    public $field;
    public $title;
    protected $closure = null;
    protected $excelClosure = null;
    protected $using = [];
    protected $fromFeild = null;
    protected $defaultValue = '--';
    public $totalRow = false;
    public $colsRow = 0;
    private $color = null;
    protected $layui_bg = [
        'layui-bg-cyan',
        'layui-bg-orange',
        'layui-bg-blue',
        'layui-bg-green',
        'layui-bg-black',
    ];
    public $cols = [
        'align' => 'center'
    ];
    protected $htmlAttr = [];

    public function __construct($field, $title)
    {
        $this->field = $field;
        $this->title = $title;
        $this->cols['field'] = $this->field;
        $this->cols['title'] = $title;

    }

    /**
     * 设置默认值
     * @Author: rocky
     * 2019/11/9 11:07
     * @param $val 默认值
     */
    public function defaultValue($val)
    {
        $this->defaultValue = $val;
    }

    //设置数据
    public function setData($data)
    {
        $this->data = $data;
        $fields = explode('.', $this->field);
        foreach ($fields as $f) {
            if (isset($data[$f])) {
                $data = $data[$f];
            } else {
                $data = '';
            }

        }

        $this->value = $data;

        if (!empty($this->using)) {
            $bgColor = $this->layui_bg[$this->value];
            $this->value = $this->using[$this->value];
            $this->columnHtml = $this->value;
        }
        if (empty($this->htmlAttr)) {
            $this->columnHtml = $this->value;
        } else {
            foreach ($this->htmlAttr as $val) {
                if (!empty($this->using)) {
                    if (strstr($val, 'badge')) {
                        $val = preg_replace("/class='(.*)'/", "class='layui-badge {$bgColor}'", $val);
                    }
                }
                if (is_array($this->value)) {
                    foreach ($this->value as $value) {
                        if (!empty($value) || is_numeric($value)) {
                            $this->columnHtml .= str_replace('_VALUE_', $value, $val);
                        }
                    }

                    if (!empty($this->value) || is_numeric($this->value)) {
                        $this->columnHtml = '<span class="layui-col-space10">' . $this->columnHtml . '</span>';
                    }
                } else {

                    if (!empty($this->value) || is_numeric($this->value)) {
                        $val = str_replace('_RAND_', rand(100000, 999999), $val);
                        $this->columnHtml = str_replace('_VALUE_', $this->value, $val);
                    }
                }

            }

        }
        if (!is_null($this->fromFeild)) {
            $this->fromFeild->value($this->value);
            $this->fromFeild->domId($this->field . $this->data->id);
            $this->fromFeild->id($this->data->id);
            $this->columnHtml = $this->fromFeild->render();
        }
        if (!is_null($this->closure)) {
            $this->columnHtml = call_user_func_array($this->closure, [$this->value, $this->data, $this->columnHtml]);
        }
        if (!is_null($this->excelClosure)) {
            $this->excelData = call_user_func_array($this->excelClosure, [$this->value, $this->data, $this->columnHtml]);
        }
        if (empty($this->columnHtml)) {

            if (!is_numeric($this->columnHtml)) {
                $this->columnHtml = $this->defaultValue;
            }
        }
        if (!is_null($this->color)) {
            $this->columnHtml = '<span class="color-' . $this->color . '">' . $this->columnHtml . '</span>';
        }
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

    //自定义显示格式
    public function setExcelData(\Closure $closure)
    {
        $this->excelClosure = $closure;
        return $this;
    }

    /**
     * 关闭excel 导出
     * @Author: rocky
     * 2019/10/9 16:54
     */
    public function excelClose()
    {
        $this->excelClose = true;
        return $this;
    }

    /**
     * 开启合计
     * @Author: rocky
     * 2019/7/25 16:50
     * @param $value 开启合计
     */
    public function totalRow()
    {
        $this->totalRow = true;
        return $this;
    }

    /**
     * 开启编辑
     * @Author: rocky
     * 2019/7/25 16:50
     * @param $value 对齐方式
     */
    public function hide()
    {
        $this->cols['hide'] = true;
        return $this;
    }

    /**
     * 开启编辑
     * @Author: rocky
     * 2019/7/25 16:50
     * @param $value 对齐方式
     */
    public function editor()
    {
        $this->cols['edit'] = 'text';
        return $this;
    }

    /**
     * 开启排序
     * @Author: rocky
     * 2019/7/25 16:50
     */
    public function sort($sort = true)
    {
        if (is_string($sort)) {
            $this->cols['sortSql'] = $sort;
        }
        $this->cols['sort'] = true;
        return $this;
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

    public function color($color)
    {
        $this->color = $color;
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

    /**
     * 设置最小宽度
     * @Author: rocky
     * 2019/7/25 16:50
     * @param $value 宽度
     */
    public function minWidth($val)
    {
        $this->cols['minWidth'] = $val;
        return $this;
    }

    //switchs开关更新
    public function switchs($state = [])
    {
        $this->fromFeild = new Field('switchUpdate', '', $this->field, $this->value);
        $this->fromFeild->swithcStates($state);
    }


    public function select($options, $multiple = false)
    {
        $this->fromFeild = new Field('selectUpdate', '', $this->field, $this->value);
        if ($multiple) {
            $this->fromFeild->multiple($multiple);
        }

        $this->fromFeild->options($options);
    }

    public function render()
    {
        $this->html = $this->columnHtml;
        $this->columnHtml = '';
        return $this->html;
    }

    /**
     * 视频显示
     * @Author: rocky
     * 2019/11/19 10:56
     * @param int $width 宽度
     * @param int $height 高度
     * @param $autoplay 自动播放
     * @param bool $control 控制
     */
    public function video($width=500,$height=300,$autoplay=false,$control=true){
        if($control){
            $control = 'controls';
        }else{
            $control = '';
        }
        if($autoplay){
            $autoplay = 'autoplay';
        }else{
            $autoplay = '';
        }
        $this->htmlAttr[] = "<video src='_VALUE_' width='{$width}' height='{$height}' {$control} {$autoplay}></video><script> $('audio,video').mediaelementplayer(/* Options */);</script>";
    }
    /**
     * 音频显示
     * @Author: rocky
     * 2019/11/19 10:56
     * @param int $width 宽度
     * @param int $height 高度
     * @param $autoplay 自动播放
     * @param bool $control 控制
     */
    public function audio($width=300,$height=40,$autoplay=false,$control=true){
        if($control){
            $control = 'controls';
        }else{
            $control = '';
        }
        if($autoplay){
            $autoplay = 'autoplay';
        }else{
            $autoplay = '';
        }
        $this->htmlAttr[] = "<audio src='_VALUE_' width='{$width}' height='{$height}' {$control} {$autoplay}></audio><script> $('audio,video').mediaelementplayer(/* Options */);</script>";
    }
    //图片显示
    public function image($radius = 0, $width = 80, $height = 80)
    {

        $this->htmlAttr[] = "<img src='_VALUE_' data-tips-image='' style='width: {$width}px;height: {$height}px;border-radius: {$radius}%'>";
    }

    public function rate($length)
    {
        $this->htmlAttr[] = <<<EOF
<div data-rate='_RAND_'></div><script>
  layui.rate.render({
      elem: $('[data-rate=_RAND_]')  //绑定元素
      ,value:'_VALUE_'
      ,length:{$length}
      ,readonly:true
    });
  </script>
EOF;
    }

    //徽章显示
    public function badge($options = 'blue')
    {
        if (is_array($options)) {
            $color = $options[$this->value];
        } else {
            $color = $options;
        }
        $this->htmlAttr[] = "<span class='layui-badge layui-bg-{$color}'>_VALUE_</span>";
    }

    //自定义显示格式
    public function display(\Closure $closure)
    {
        $this->closure = $closure;
        return $this;
    }

    public function using(array $options)
    {
        $this->using = $options;
        return $this;
    }
}