<?php
/**
 * Created by PhpStorm.
 * User: rocky
 * Date: 2019-07-28
 * Time: 11:45
 */

namespace buildView;

class Button extends Field
{
    protected $html = '';
    protected $class = '';
    public function __construct($text,$color='',$size='',$icon = 'layui-icon-edit',$radius = false)
    {
        $this->template = 'button';
        if(!empty($icon)){
            $this->icon("<i class=\"layui-icon {$icon}\"></i>");
        }
        $this->label($text);
        if(!empty($color)){
            $this->color('layui-btn-'.$color);
        }
        if(!empty($size)){
            $this->size('layui-btn-'.$size);
        }
        if($radius){
            $this->radius('layui-btn-radius');
        }
    }

    /**
     * 打开窗口 modal当前 open新窗口
     * @Author: rocky
     * 2019/9/11 10:02
     * @param $url 跳转链接
     * @param string $type 跳转类型
     */
    public function href($url,$type='open'){
        if(empty($url)){
            $url = request()->url();
        }
        $this->tager("data-{$type}='{$url}'");

    }

    /**
     * 更新单条数据
     * @Author: rocky
     * 2019/9/11 10:06
     * @param $id 更新主键条件
     * @param array $updateData 更新数据
     * @param string $url
     */
    public function save($id,$updateData,$url=''){
        if(empty($url)){
            $url = request()->url();
        }
        $str = $this->ruleValue($updateData);
        $this->value("data-value='id#{$id};{$str}'");
        $this->tager("data-action='{$url}'");
    }
    private function ruleValue($updateData){
        $str = '';
        foreach ($updateData as $key=>$val){
            $str.="{$key}#{$val};";

        }
        $str = substr($str, 0, -1);
        return $str;
    }

    /**
     * 批量更新数据
     * @Author: rocky
     * 2019/9/11 10:06
     * @param $id 更新主键条件
     * @param array $updateData 更新数据
     * @param string $url
     */
    public function saveAll($updateData,$url=''){
        if(empty($url)){
            $url = request()->url();
        }
        $str = $this->ruleValue($updateData);
        $this->id("data-batch='true'");
        $this->value("data-value='{$str}'");
        $this->tager("data-url='{$url}'");
    }
}