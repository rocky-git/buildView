<?php
/**
 * Created by PhpStorm.
 * User: rocky
 * Date: 2020-03-25
 * Time: 21:43
 */

namespace buildView;


use think\Service;

class RegisterService extends Service
{
    public function register()
    {
        //定义上传方法路由
        \think\facade\Route::post('buildview/upload',function(){
            $plug = new \buildView\Plugs();
            return $plug->plupload();
        });
        //定义获取七牛云Token
        \think\facade\Route::any('buildview/qiniuToken',function(){
            $plug = new \buildView\Plugs();
            return $plug->qiniuToken();
        });
        //定义百度地图路由
        \think\facade\Route::any('buildview/map',function(){
            $plug = new \buildView\Plugs();
            return $plug->map();
        });
        //语言
        $lang = 'zh-cn';
        \think\facade\Lang::load(__DIR__ . '/lang/' . $lang . '.php');
    }
    public function boot(Route $route)
    {
        $this->commands([
            'buildView\command\BuildView',
        ]);
    }
}