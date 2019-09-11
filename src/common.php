<?php
/**
 * Created by PhpStorm.
 * User: rocky
 * Date: 2019-08-03
 * Time: 23:42
 */
error_reporting(E_ERROR | E_PARSE);
\think\Console::addDefaultCommands([
    'buildView\command\BuildView',
]);
$lang = 'zh-cn';
\think\facade\Lang::load(__DIR__ .'/lang/'.$lang.'.php');