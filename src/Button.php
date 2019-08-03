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
    public function href($url,$type='open'){
        if(empty($url)){
            $url = request()->url();
        }
        $this->tager("data-{$type}='{$url}'");

    }
    public function save($id,$field,$val,$url=''){
        if(empty($url)){
            $url = request()->url();
        }
        $this->id("data-update='{$id}'");
        $this->field("data-field='{$field}'");
        $this->value("data-value='{$val}'");
        $this->tager("data-action='{$url}'");
    }
    public function saveAll($field,$val,$url=''){
        if(empty($url)){
            $url = request()->url();
        }
        $this->id("data-update='' data-batch=true");
        $this->field("data-field='{$field}'");
        $this->value("data-value='{$val}'");
        $this->tager("data-action='{$url}'");
    }
}