<?php

namespace buildView\command;

use think\console\Command;
use think\console\command\Make;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;
use think\facade\App;
use think\facade\Config;

class BuildView extends Make
{
    protected function configure()
    {
        // 指令配置
        $this->setName('make:admin')->setDescription('Create a new BuildView controller class');
        $this->addArgument('name', 1, "The name of the class");
        $this->addOption('model', 1, Option::VALUE_REQUIRED,"The name of the class");
        // 设置参数

    }

    protected function getStub()
    {

    }
    protected function getStubs($name)
    {
        $stubPath = __DIR__ . DIRECTORY_SEPARATOR . 'buildview' . DIRECTORY_SEPARATOR;

        return $stubPath . $name.'.stub';
    }
    protected function getClassNames($module,$name)
    {
        return parent::getClassName($this->getNamespace('app', $module) . '\\' . $name) . (Config::get('controller_suffix') ? ucfirst(Config::get('url_controller_layer')) : '');
    }

    protected function getNamespace($appNamespace, $module)
    {
        return parent::getNamespace($appNamespace, $module) ;
    }
    protected function buildClasss($name,$type,$model='',$model_namespace='',$grid='',$detail='',$form='')
    {
        $stub = file_get_contents($this->getStubs($type));
        $namespace = trim(implode('\\', array_slice(explode('\\', $name), 0, -1)), '\\');
        $class = str_replace($namespace . '\\', '', $name);
        return str_replace(['{%className%}', '{%actionSuffix%}', '{%namespace%}', '{%app_namespace%}','{%model%}','{%model_namespace%}','{%grid%}','{%detail%}','{%form%}'], [
            $class,
            Config::get('action_suffix'),
            $namespace,
            App::getNamespace(),
            $model,
            $model_namespace,
            $grid,
            $detail,
            $form
        ], $stub);
    }
    protected function getTableInfo($model){
        $db = db()->name($model);
        $tableInfo= $db->query('SHOW FULL COLUMNS FROM '.$db->getTable());
        $fields = $db->getTableFields();
        $grid = '';
        $detail = '';
        $form = '';
        foreach ($tableInfo as $val){
            $label = $val['Comment']?$val['Comment']:$val['Field'];
            $grid .= "\t\t".'$grid->column(\''.$val['Field'].'\',\''.$label.'\');'.PHP_EOL;
            $detail .= "\t\t".'$detail->column(\''.$val['Field'].'\',\''.$label.'\');'.PHP_EOL;
            if(strstr($val['Type'],'char')){
                $form .= "\t\t".'$form->text(\''.$val['Field'].'\',\''.$label.'\');'.PHP_EOL;
            }elseif (strstr($val['Type'],'timestamp')){
                $form .= "\t\t".'$form->datetime(\''.$val['Field'].'\',\''.$label.'\');'.PHP_EOL;
            }elseif (strstr($val['Type'],'datetime')){
                $form .= "\t\t".'$form->datetime(\''.$val['Field'].'\',\''.$label.'\');'.PHP_EOL;
            }elseif (strstr($val['Type'],'date')){
                $form .= "\t\t".'$form->date(\''.$val['Field'].'\',\''.$label.'\');'.PHP_EOL;
            }elseif (strstr($val['Type'],'time')){
                $form .= "\t\t".'$form->time(\''.$val['Field'].'\',\''.$label.'\');'.PHP_EOL;
            }elseif (strstr($val['Type'],'tinyint')){
                $form .= "\t\t".'$form->switch(\''.$val['Field'].'\',\''.$label.'\');'.PHP_EOL;
            }elseif (strstr($val['Type'],'int') || strstr($val['Type'],'decimal')){
                $form .= "\t\t".'$form->number(\''.$val['Field'].'\',\''.$label.'\');'.PHP_EOL;
            }elseif (strstr($val['Type'],'text')){
                $form .= "\t\t".'$form->ckeditor(\''.$val['Field'].'\',\''.$label.'\');'.PHP_EOL;
            }else{
                $form .= "\t\t".'$form->text(\''.$val['Field'].'\',\''.$label.'\');'.PHP_EOL;
            }
        }
        if(in_array('create_at',$fields)){
            $grid .= "\t\t".'$grid->filter(function ($filter){
            $filter->dateBetween(\'create_at\',\'添加时间\');
        });'.PHP_EOL;
        }
        return [
            $grid,
            $detail,
            $form,
        ];
    }
    protected function execute(Input $input, Output $output)
    {

        if($input->hasOption('model')){

            $model = $input->getOption('model');
            $names = explode('/',$model);
            $names = array_filter($names);
            if(isset($names[1])){
                $model = $names[1];
                $classname_model = $this->getClassNames($names[0],'model\\'.$names[1]);
            }else{
                $classname_model = $this->getClassNames('common','model\\'.$model);
            }
           
            $this->getTableInfo($model);
            $pathname = $this->getPathName($classname_model);
            if (is_file($pathname)) {
                $output->writeln('<error>' . $classname_model . ' already exists!</error>');
            }

            if (!is_dir(dirname($pathname))) {
                mkdir(dirname($pathname), 0755, true);
            }
            list($grid,$detail,$form) = $this->getTableInfo($model);
            file_put_contents($pathname, $this->buildClasss($classname_model,'model'));
            $classname_models = $this->getClassNames('common','model\\BaseModel');
            $pathname = $this->getPathName($classname_models);
            if (!is_file($pathname)) {
                file_put_contents($pathname, $this->buildClasss($classname_models,'baseModel'));
            }
        }
        $name = trim($input->getArgument('name'));
        $names = explode('/',$name);
        $names = array_filter($names);
        if(isset($names[1])){
            $classname = $this->getClassNames($names[0],'controller\\'.$names[1]);
        }else{
            $classname = $this->getClassNames('admin','controller\\'.$name);
        }
        $pathname = $this->getPathName($classname);
        if (is_file($pathname)) {
            $output->writeln('<error>' . $classname . ' already exists!</error>');
            return false;
        }

        if (!is_dir(dirname($pathname))) {
            mkdir(dirname($pathname), 0755, true);
        }
        file_put_contents($pathname, $this->buildClasss($classname,'controller',$model,$classname_model,$grid,$detail,$form));
        $output->writeln('<info>' . $this->type . ' created successfully.</info>');
    }
}
