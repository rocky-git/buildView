<?php
/**
 * Created by PhpStorm.
 * User: rocky
 * Date: 2019-07-18
 * Time: 20:40
 */

namespace buildView;
use think\Collection;
use think\exception\HttpResponseException;
use think\facade\Request;
use think\Model;
use think\model\relation\BelongsTo;
use think\model\relation\HasMany;
use think\model\relation\HasOne;

/**
 * Class Form
 * @package app\common\tools\formview
 * @method Field text($lable, $field) 文本输入框;
 * @method Field hidden($lable, $field) 隐藏框框;
 * @method Field number($lable, $field) 数字输入框;
 * @method Field password($lable, $field) 密码输入框;
 * @method Field select($lable, $field) select选择框;
 * @method Field radio($lable, $field) radio单选框;
 * @method Field checkbox($lable, $field) checkbox复选框;
 * @method Field switch ($lable, $field) switch开关;
 * @method Field textarea($lable, $field) textarea文本框;
 * @method Field ckeditor($lable, $field) ckeditor编辑器;
 * @method Field image($lable, $field) 上传图片框;
 * @method Field file($lable, $field) 上传文件;
 * @method Field slider($lable, $field) 滑块框;
 * @method Field date($lable, $field) 日期框;
 * @method Field datetime($lable, $field) 日期时间框;
 * @method Field dateRange($lable, $field) 日期范围框;
 * @method Field timeRange($lable, $field) 时间范围框;
 * @method Field time($lable, $field) 时间框;
 */
class Form extends Field
{
    protected $formItem = [];
    protected $model;
    protected $data = [];
    protected $tabArr = [];
    protected $relationArr = [];
    protected $tabTitles = [];
    protected $tabContents = [];
    protected $tabField = null;
    protected $tabNum = 0;
    protected $tabCount = 0;
    protected $beforeSave = null;
    /**
     * Form constructor.
     * @param 模型
     */
    public function __construct($model = '')
    {
        if (!empty($model)) {
            if ($model instanceof Model) {
                $this->model = $model;
            } else {
                abort(999, '不是有效的模型');
            }
            $id = Request::get('id', false);
            $this->setOption('title', '添加');
            if ($id !== false) {
                $this->data = $this->model->exists(true)->find($id);
                if(empty($this->data)){
                    throw new HttpResponseException(json(['code' => 0, 'msg' => '数据不存在！', 'data' => []]));
                }
                $this->setOption('hiddenId', '<input type="hidden" value="' . $id . '" name="' . $this->model->getPk() . '">');
                $this->setOption('title', '编辑');
            }
        }
        $this->setOption('aciontUrl', request()->url());
        $this->template = 'form';
        $this->importJs();
    }
    private function importJs(){
        $js = file_get_contents(__DIR__ . '/view/admin.js');
        $this->options['import_js'] = $js;
    }

    /**
     * 设置标题
     * @param $title 设置标题
     */
    public function setTitle($title)
    {
        $this->setOption('title', $title);
    }

    /**
     * 设置JS
     * @param $title 设置JS
     */
    public function script($js)
    {
        $this->setOption('script', $js);
    }

    /**
     * 设置请求URL
     * @param $url 请求地址
     */
    public function setAction($url)
    {
        $this->setOption('aciontUrl', $url);
    }

    /**
     * 数据保存
     * @Author: rocky
     * 2019/7/31 9:08
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    private function dataSave()
    {
        if (Request::isPost()) {
            $post = Request::post();
            if(!is_null($this->beforeSave)){
                $beforePost = call_user_func($this->beforeSave,Request::post());
                $post = array_merge($post,$beforePost);
            }
            if ($this->model instanceof Model) {
                $res = $this->model->save($post);
                foreach ($this->relationArr as $relation) {
                    if ($this->model->$relation() instanceof BelongsTo || $this->model->$relation() instanceof HasOne) {
                        $relationData = $post[$relation];
                        if(empty($this->data)){
                            $this->model->$relation()->save($relationData);
                        }else{
                            $this->data->$relation->save($relationData);
                        }
                    }elseif($this->model->$relation() instanceof HasMany){
                        $res = $this->model->save($post);
                        $realtionUpdateIds = $post[$relation]['id'];
                        $ids = Collection::make($this->data->$relation)->column('id');
                        $deleteIds = array_diff($ids, $realtionUpdateIds);
                        $relationData = [];
                        $fields = array_keys($post[$relation]);
                        foreach ($fields as $field) {
                            foreach ($post[$relation][$field] as $key => $val) {
                                $relationData[$key][$field] = $val;
                            }
                        }
                        if (count($deleteIds) > 0) {
                            $this->model->$relation()->whereIn('id', $deleteIds)->delete();
                        }
                        $this->model->$relation()->saveAll($relationData);
                    }
                }
            } else {
                foreach ($post as $key => $val) {
                    $res = sysconf($key, $val);
                }
            }
            if ($res) {
                throw new HttpResponseException(json(['code' => 1, 'msg' => '数据保存成功', 'data' => []]));
            } else {
                throw new HttpResponseException(json(['code' => 0, 'msg' => '数据保存失败, 请稍候再试!', 'data' => []]));
            }

        }
    }

    /**
     * 输出视图
     * @return string
     */
    public function view()
    {
        $hasManyHtml = '';
        $html = '';
        $types = Collection::make($this->formItem)->column('type');
        $tabs = array_count_values($types);
        $this->tabCount = $tabs['tab'];
        $html = $this->parseFormItem($html, $hasManyHtml);


        $this->dataSave();
        $this->setOption('content', $html);
        return $this->render();
    }

    protected function formItem($template, $lable, $field)
    {
        if ($this->model instanceof Model) {
            if (is_array($field)) {
                foreach ($field as $value) {
                    $val[] = $this->getData($value);
                }
            } else {
                $val = $this->getData($field);
            }
        } else {
            $val = sysconf($field);
        }
        $names = explode('.',$field);
        if(count($names) > 1){
            $relationMethod = $names[0];
            if (method_exists($this->model, $relationMethod)) {
                if ($this->model->$relationMethod() instanceof BelongsTo || $this->model->$relationMethod() instanceof HasOne) {
                    if(!in_array($names[0], $this->relationArr)){
                        array_push($this->relationArr,$names[0]);
                    }
                }
            }
            $field = "{$relationMethod}[{$names[1]}]";
        }
        $item = new Field($template, $lable, $field, $val);
        array_push($this->formItem, $item);
        return $item;
    }

    public function __call($name, $arguments)
    {

        return $this->formItem($name, $arguments[0], $arguments[1]);
    }

    public function hasMany($label, $relationMethod, \Closure $closure)
    {
        if (method_exists($this->model, $relationMethod)) {
            if($this->model->$relationMethod() instanceof HasMany){
                array_push($this->relationArr, $relationMethod);
                array_push($this->formItem, ['type' => 'hasMany', 'label' => $label, 'relationMethod' => $relationMethod, 'closure' => $closure]);
            }else{
                abort(100,'关联方法不是一对多');
            }
        }else{
            abort(100,'无效关联方法');
        }
    }

    public function tab($title, \Closure $closure)
    {
        array_push($this->formItem, ['type' => 'tab', 'title' => $title, 'closure' => $closure]);
        return $this;
    }

    protected function getData($field)
    {
        $fields = explode('.', $field);
        $val = $this->data;
        foreach ($fields as $f) {
            if(isset($val[$f])){
                $val = $val[$f];
            }else{
                $val = '';
            }
        }
        return $val;
    }

    /**
     * @param $html
     * @param $hasManyHtml
     * @return string
     */
    public function parseFormItem($html, $hasManyHtml)
    {
        foreach ($this->formItem as $key => $form) {
            if ($form instanceof Field) {
                $html .= $form->render();
            } else {
                if ($form['type'] == 'hasMany') {
                    $formItemArr = array_slice($this->formItem, $key);
                    $this->formItem = [];
                    call_user_func($form['closure'], $this);
                    if (method_exists($this->model, $form['relationMethod'])) {
                        $hasManyjsHtml = '';
                        foreach ($this->formItem as $k => $f) {
                            if (is_null($f->field)) {
                                $f->field($f->name);
                            }
                            $f->name("{$form['relationMethod']}[{$f->field}][]");
                            $hasManyjsHtml = $hasManyjsHtml . $f->render();
                        }
                        $hasManyjsHtml = '<div class="layui-row">' . $hasManyjsHtml . '<div class="layui-form-item"><div class="layui-input-block"><button type="button" class="layui-btn layui-btn-danger" data-hasMany="hasManyDel">移除</button></div></div></div>';
                        foreach ($this->data[$form['relationMethod']] as $val) {
                            $hasItemHtml = '';
                            $idField = new Field('hidden', 'id', "{$form['relationMethod']}[id][]", $val['id']);
                            foreach ($this->formItem as $k => $f) {
                                if (is_null($f->field)) {
                                    $f->field($f->name);
                                }
                                $f->value($val[$f->field]);
                                $f->name("{$form['relationMethod']}[{$f->field}][]");
                                $hasItemHtml = $hasItemHtml . $f->render();
                            }
                            $hasItemHtml = '<div class="layui-row">' . $idField->render() . $hasItemHtml . '<div class="layui-form-item"><div class="layui-input-block"><button type="button" class="layui-btn layui-btn-danger" data-hasMany="hasManyDel">移除</button></div></div></div>';
                            $hasManyHtml .= $hasItemHtml;
                        }
                        $hasManyField = new Field('hasMany', $form['label'], $form['relationMethod'], $hasManyHtml);
                        $hasManyField->hasManyjsHtml(urlencode($hasManyjsHtml));
                        $html .= $hasManyField->render();
                        $this->formItem = $formItemArr;
                    }
                } elseif ($form['type'] == 'tab') {
                    $formItemArr = array_slice($this->formItem, $key);
                    $this->tabNum += 1;
                    $this->formItem = [];
                    call_user_func($form['closure'], $this);
                    $html = $this->parseFormItem($html, $hasManyHtml);
                    array_push($this->tabContents, $html);
                    $html = '';
                    $this->formItem = [];
                    array_push($this->tabTitles, $form['title']);

                    if (is_null($this->tabField)) {
                        $this->tabField = new Field('tab', '', '', '');
                        $this->tabField->setOption('tabTitles', $this->tabTitles);
                        $this->tabField->setOption('tabContents', $this->tabContents);
                    } else {
                        $this->tabField->setOption('tabTitles', $this->tabTitles);
                        $this->tabField->setOption('tabContents', $this->tabContents);
                    }
                    if ($this->tabCount  == 1 || $this->tabCount  == $this->tabNum) {

                        $html = $this->tabField->render();

                    }
                    $this->formItem = $formItemArr;
                }
            }
        }
        return $html;
    }
    //保存前回调
    public function saving(\Closure $closure){
        $this->beforeSave = $closure;
    }
}