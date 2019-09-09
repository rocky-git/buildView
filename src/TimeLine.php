<?php
/**
 * @Author: rocky
 * @Copyright: 广州拓冠科技 <http://my8m.com>
 * Date: 2019/8/31
 * Time: 17:22
 */


namespace buildView;



use think\App;

class TimeLine extends Field
{
    public function __construct($data,$titleFiled,$contentField)
    {
        $this->template = 'timeline';
        $this->setOption('title',$titleFiled);
        $this->setOption('content',$contentField);
        $this->setOption('data',$data);
    }
}