<?php
/**
 * Created by PhpStorm.
 * User: rocky
 * Date: 2019-08-04
 * Time: 10:30
 */

namespace buildView;


use think\facade\View;

class Builder
{
    public $html = '';

    public $script = '';

    public $style = '';

    public $css = [
    
        '/vendor/rockys/build-view/src/plugs/theme/css/console.css',
//        '/vendor/rockys/build-view/src/plugs/formSelect/formSelects-v4.css',
    ];
    public $js = [
      
        'admin' => '/vendor/rockys/build-view/src/plugs/admin.js',
//        'formSelect'=> '/vendor/rockys/build-view/src/plugs/formSelect/formSelects-v4.min.js',
    ];


    public function render($html)
    {
        $this->html .= $html;
        foreach ($this->css as $css) {
            $this->html .= '<link href="' . $css . '" rel="stylesheet">' . PHP_EOL;
        }
        if (!(empty($this->style))) {
            $this->html .= '<style>' . $this->style . '</style>' . PHP_EOL;
        }

        foreach ($this->js as $key => $js) {
            $this->html .= '<script src="'.$js.'"></script>';
        }
        if (!(empty($this->script))) {
            $this->html .= '<script>' . $this->script . '</script>' . PHP_EOL;
        }
        
        return View::display($this->html);
    }
}