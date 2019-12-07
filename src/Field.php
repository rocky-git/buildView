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
 * @method $this readonly() 只读
 * @method $this disabled() 禁用
 * @method $this attr($array) 设置属性
 * @method $this appendHtml($html) 追加html
 * @method $this getData($bool) 获取原始数据
 * @method $this required() 非空
 * @method $this default() 默认值
 * @method $this layui() 布局
 * @method $this options($array) 选项数据
 * @method $this load($field, $url) select联动
 * @method $this multiple($bool) 开启多选/多图
 * @method $this min() 最小值
 * @method $this max() 最大值
 * @method $this swithcStates() switch参数设置
 * @method $this help() 提示文本
 * @method $this format() 时间格式
 * @method Field uptype($type) 上传存储类型;
 * @method Field baseUrl($bool) 上传带域名前缀;
 * @method Field uploadType($extension) 允许上传文件的后缀;
 */
class Field
{
    protected $options = [];
    public $template = '';
    protected $md = 0;
    protected $appendHtml = '';
    protected $layuiVerify = ['required', 'phone', 'email', 'url', 'number', 'date', 'identity'];
    protected $buildForm = null;

    public function __construct($template, $label, $name, $value, $rawValue = '')
    {
        $this->template = $template;
        $this->setOption('label', $label);
        $this->setOption('name', $name);
        $this->setOption('value', $value);
        $this->setOption('rawValue', $rawValue);
        $this->setOption('layui', 'block');
    }

    public function setBuildForm($form)
    {
        $this->buildForm = $form;
    }

    /**
     * 设置验证规则
     * @Author: rocky
     * 2019/8/9 10:59
     * @param $rule 验证规则
     * @param $msg 验证提示
     * @param $type 0新增更新，1新增，2更新
     */
    private function setRule($rule, $msg, $type = 0)
    {
        $rule = [
            $this->name => $rule
        ];
        $ruleMsg = [];
        foreach ($msg as $key => $m) {
            $ruleMsg[$this->name . '.' . $key] = $m;
        }
        if (!is_null($this->buildForm)) {
            $this->buildForm->setRules($rule, $ruleMsg, $type);
        }
    }

    /**
     * 表单新增更新验证规则
     * @Author: rocky
     * 2019/8/9 10:50
     * @param $rule 验证规则
     * @param $msg 验证提示
     */
    public function rule($rule, $msg = [])
    {
        $this->setRule($rule, $msg);
        return $this;
    }

    /**
     * 表单新增验证规则
     * @Author: rocky
     * 2019/8/9 10:50
     * @param $rule 验证规则
     * @param $msg 验证提示
     */
    public function createRule($rule, $msg = [])
    {
        $this->setRule($rule, $msg, 1);
        return $this;
    }

    /**
     * 表单更新验证规则
     * @Author: rocky
     * 2019/8/9 10:50
     * @param $rule 验证规则
     * @param $msg 验证提示
     */
    public function updateRule($rule, $msg = [])
    {
        $this->setRule($rule, $msg, 2);
        return $this;
    }

    public function __call($name, $arguments)
    {
        if (!method_exists($this, $name)) {
            if (count($arguments) == 1) {
                $val = array_shift($arguments);
            } else {
                $val = $arguments;
            }
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

    //追加html
    public function append($html)
    {
        if ($html instanceof Button) {
            $this->options['appendHtml'] = $html->render();
        } else {
            $this->options['appendHtml'] = $html;
        }
        return $this;
    }

    /**
     * md布局
     * @param $num
     * @return $this
     */
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

        if (is_array($this->options['value'])) {
            $this->options['value'] = array_filter($this->options['value']);
        } elseif (isset($this->options['getData']) && $this->options['getData'] == true) {
            $this->options['value'] = $this->options['rawValue'];
        }
        if (empty($this->options['value']) && !is_numeric($this->options['value'])) {
            if (isset($this->options['default'])) {
                $this->options['value'] = $this->options['default'];
            }
        }
        foreach ($this->options as $key => $option) {
            if($this->template == 'number'){
                if (in_array($key, ['min', 'max', 'readonly', 'disabled'])) {
                    $this->options[$key] = "{$key}='{$option}' ";
                }
            }else{
                if (in_array($key, ['readonly', 'disabled'])) {
                    $this->options[$key] = "{$key}='{$option}' ";
                }
            }
        }
        $path = __DIR__ . '/view/' . $this->template . '.html';
        if (file_exists($path)) {
            $content = file_get_contents($path);
        } else {
            $path = app()->getModulePath() . 'view/build_view/' . $this->template . '.html';

            $content = file_get_contents($path);
        }
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
        $this->setOption('build_view_rand', mt_rand(1000000, 9999999));
        return View::display($content, $this->options, ['strip_space' => false]);
    }

}