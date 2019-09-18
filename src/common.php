<?php
/**
 * Created by PhpStorm.
 * User: rocky
 * Date: 2019-08-03
 * Time: 23:42
 */

function recurse_copy($src, $dst)
{  // 原目录，复制到的目录
    //打开文件夹
    $dir = opendir($src);
    $file = readdir($dir);
    //创建文件
    @mkdir($dst);
    //读取文件夹内容
    while (false !== ($file = readdir($dir))) {
        //跳过. 和..
        if (($file != '.') && ($file != '..')) {
            //查看文件是否是目录
            if (is_dir($src . '/' . $file)) {
                //如果是，递归调用本函数，继续读取
                recurse_copy($src . '/' . $file, $dst . '/' . $file);
            } else {
                if (!is_dir($dst)) {
                    $dirArr = explode('/', $dst);
                    $strDir = '';
                    foreach ($dirArr as $toDir){
                        $strDir .= $toDir .'/';
                        if(!is_dir($strDir)){
                            mkdir($strDir,0777);
                        }
                    }
                }
                //否则复制文件到目标文件夹
                $res = copy($src . '/' . $file, $dst . '/' . $file);
            }
        }
    }
    //关闭文件
    closedir($dir);
}

error_reporting(E_ERROR | E_PARSE);
//自定义命令
\think\Console::addDefaultCommands([
    'buildView\command\BuildView',
]);

//发布静态资源
$path = dirname($_SERVER['SCRIPT_FILENAME']);
$path .= DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'build-view';
if (!is_dir($path)) {
    $assets = __DIR__ . DIRECTORY_SEPARATOR . 'assets';
    recurse_copy($assets, $path);
}

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

//语言
$lang = 'zh-cn';
\think\facade\Lang::load(__DIR__ . '/lang/' . $lang . '.php');