<?php
/**
 * Created by PhpStorm.
 * User: rocky
 * Date: 2019-07-21
 * Time: 08:31
 */

namespace buildView;

use think\facade\View;

/**
 * Class Field
 * @package app\common\tools\formview
 * @method $this required() 非空
 * @method $this default() 默认值
 * @method $this layui() 布局
 * @method $this options($array) 选项数据
 * @method $this multiple($bool) 开启多选/多图
 * @method $this min() 最小值
 * @method $this max() 最大值
 * @method $this swithcStates() switch参数设置
 * @method $this uploadType() 文件上传类型
 * @method $this help() 提示文本
 * @method $this format() 时间格式
 */
class Field
{
    protected $options = [];
    public $template = '';
    protected $md = 0;
    protected $appendHtml = '';
    protected $layuiVerify = ['required', 'phone', 'email', 'url', 'number', 'date', 'identity'];

    public function __construct($template, $label, $name, $value)
    {
        $this->template = $template;

        $this->setOption('label', $label);
        $this->setOption('name', $name);
        $this->setOption('value', $value);
        $this->setOption('layui', 'block');

    }

    public function __call($name, $arguments)
    {
        if (!method_exists($this, $name)) {
            $val = array_shift($arguments);
            $this->setOption($name, $val);
        }
        return $this;
    }

    public function __get($name)
    {
        return $this->options[$name];
    }

    protected function setOption($key, $val)
    {
        $this->options[$key] = $val;
    }

    public function append($html)
    {
        if ($html instanceof Button) {
            $this->options['appendHtml'] = $html->render();
        } else {
            $this->options['appendHtml'] = $html;
        }
        return $this;
    }

    public function md($num)
    {
        $this->md = $num;
        return $this;
    }

    /**
     * @return string 返回视图
     */
    public function render()
    {
        if (empty($this->options['value'])) {
            if (isset($this->options['default'])) {
                $this->options['value'] = $this->options['default'];
            }
        }
        foreach ($this->options as $key => $option) {
            if (in_array($key, ['min', 'max', 'readonly'])) {
                $this->options[$key] = "{$key}='{$option}' ";
            }
        }
        $content = file_get_contents(__DIR__ . '/view/' . $this->template . '.html');
        if ($this->md > 0) {
            $content = '<div class="layui-col-md' . $this->md . '">' . $content . '</div>';
        }
        $layuiVerifyArr = [];
        foreach ($this->layuiVerify as $value) {
            if (array_key_exists($value, $this->options)) {
                array_push($layuiVerifyArr, $value);
                $this->options[$value] = $value;
            }
            $this->options['layVerify'] = implode('|', $layuiVerifyArr);
        }
        return View::display($content, $this->options);
    }
}