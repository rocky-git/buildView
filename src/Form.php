<?php
/**
 * Created by PhpStorm.
 * User: rocky
 * Date: 2019-07-18
 * Time: 20:40
 */

namespace buildView;

use think\Collection;
use think\Db;
use think\exception\HttpResponseException;
use think\facade\Request;
use think\facade\Validate;
use think\Model;
use think\model\relation\BelongsTo;
use think\model\relation\BelongsToMany;
use think\model\relation\HasMany;
use think\model\relation\HasOne;

/**
 * Class Form
 * @package app\common\tools\formview
 * @method Field text($field, $lable) 文本输入框;
 * @method Field hidden($field, $lable) 隐藏框框;
 * @method Field number($field, $lable) 数字输入框;
 * @method Field password($field, $lable) 密码输入框;
 * @method Field select($field, $lable) select选择框;
 * @method Field selectGroup($field, $lable) select分组选择框;
 * @method Field radio($field, $lable) radio单选框;
 * @method Field checkbox($field, $lable) checkbox复选框;
 * @method Field switch ($field, $lable) switch开关;
 * @method Field textarea($field, $lable) textarea文本框;
 * @method Field ckeditor($field, $lable) ckeditor编辑器;
 * @method Field image($field, $lable) 上传图片框;
 * @method Field file($field, $lable) 上传文件;
 * @method Field slider($field, $lable) 滑块框;
 * @method Field date($field, $lable) 日期框;
 * @method Field datetime($field, $lable) 日期时间框;
 * @method Field dateRange($field, $lable) 日期范围框;
 * @method Field timeRange($field, $lable) 时间范围框;
 * @method Field time($field, $lable) 时间框;
 * @method Field color($field, $lable) 颜色框;
 * @method Field distpicker($field, $lable) 省市区;
 */
class Form extends Field
{
    protected $formItem = [];
    protected $model = null;
    protected $data = [];
    protected $tabArr = [];
    protected $relationArr = [];
    protected $tabTitles = [];
    protected $tabContents = [];
    protected $tabField = null;
    protected $tabNum = 0;
    protected $tabCount = 0;
    protected $tableFields = [];
    //保存前回调
    protected $beforeSave = null;
    //保存后回调
    protected $afterSave = null;
    //创建验证规则
    protected $createRules = [
        'rule' => [],
        'msg' => [],
    ];
    //更新验证规则
    protected $updateRules = [
        'rule' => [],
        'msg' => [],
    ];
    protected $configTable = 'SystemConfig';

    /**
     * Form constructor.
     * @param 模型
     */
    public function __construct($model = '')
    {
        if (!empty($model)) {
            if ($model instanceof Model) {
                $this->model = $model;
                $this->tableFields = $this->model->getTableFields();
            } else {
                abort(999, '不是有效的模型');
            }
            $id = Request::get('id', '');
            $this->setOption('title', lang('build_view_add_btn'));
            if (!empty($id)) {
                $this->data = $this->model->exists(true)->find($id);
                if (empty($this->data)) {
                    throw new HttpResponseException(json(['code' => 0, 'msg' => '数据不存在！', 'data' => []]));
                }
                $this->setOption('hiddenId', '<input type="hidden" value="' . $id . '" name="' . $this->model->getPk() . '">');
                $this->setOption('title', lang('build_view_grid_edit'));
            }
        }
        $this->setOption('aciontUrl', request()->url());
        $this->template = 'form';

    }

    /**
     * 设置表单验证规则
     * @Author: rocky
     * 2019/8/9 10:45
     * @param $rule 验证规则
     * @param $msg 验证提示
     * @param int $type 0新增更新，1新增，2更新
     */
    public function setRules($rule, $msg, $type = 0)
    {
        switch ($type) {
            case 0:
                $this->createRules['rule'] = array_merge($this->createRules['rule'], $rule);
                $this->createRules['msg'] = array_merge($this->createRules['msg'], $msg);
                $this->updateRules['rule'] = array_merge($this->updateRules['rule'], $rule);
                $this->updateRules['msg'] = array_merge($this->updateRules['msg'], $msg);
                break;
            case 1:
                $this->createRules['rule'] = array_merge($this->createRules['rule'], $rule);
                $this->createRules['msg'] = array_merge($this->createRules['msg'], $msg);
                break;
            case 2:
                $this->updateRules['rule'] = array_merge($this->updateRules['rule'], $rule);
                $this->updateRules['msg'] = array_merge($this->updateRules['msg'], $msg);
                break;
        }
    }

    /**
     * 设置md布局
     * @param $md 默认12
     */
    public function md($md = 12)
    {
        $this->setOption('md', $md);
    }

    /**
     * 设置方框风格的表单集
     * @param $title 设置标题
     */
    public function setThemePane()
    {
        $this->setOption('theme', true);
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
     * 设置配置表名
     * @param $configTable 表名
     */
    public function setConfigTable($configTable)
    {
        $this->configTable = $configTable;
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

    //表单提交验证规则
    private function checkRule($post)
    {
        if($this->model instanceof Model){
            if (array_key_exists($this->model->getPk(), $post)) {
                //更新
                $validate = Validate::make($this->updateRules['rule'], $this->updateRules['msg']);
            } else {
                //新增
                $validate = Validate::make($this->createRules['rule'], $this->createRules['msg']);
            }
        }else{
            $validate = Validate::make($this->createRules['rule'], $this->createRules['msg']);
        }
        $result = $validate->check($post);
        if (!$result) {
            throw new HttpResponseException(json(['code' => 0, 'msg' => $validate->getError(), 'data' => []]));
        }
    }

    /**
     * 获取模型当前数据
     * @Author: rocky
     * 2019/8/22 14:56
     * @return array|mixed
     */
    public function getModelData()
    {
        return $this->data;
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
            $this->checkRule($post);
            Db::startTrans();
            try {
                if (!is_null($this->beforeSave)) {
                    $beforePost = call_user_func($this->beforeSave, Request::post(), $this->data);
                    if (is_array($beforePost)) {
                        $post = array_merge($post, $beforePost);
                    }
                }
                if ($this->model instanceof Model) {
                    $res = $this->model->save($post);
                    foreach ($this->relationArr as $relation) {
                        if ($this->model->$relation() instanceof BelongsTo || $this->model->$relation() instanceof HasOne) {

                            $relationData = $post[$relation];
                            if (empty($this->data) || empty($this->data->$relation)) {
                                $this->model->$relation()->save($relationData);
                            } else{
                                $this->data->$relation->save($relationData);
                            }
                        } elseif ($this->model->$relation() instanceof HasMany) {
                            if($post[$relation] === false){
                                continue;
                            }
                            if (empty($this->data)) {
                                $pk = $this->model->getPk();
                                $this->data = $this->model->find($this->model->$pk);
                            }
                            $realtionUpdateIds = $post[$relation]['id'];
                            $deleteIds = $this->data->$relation->column('id');
                            if (is_array($realtionUpdateIds)) {
                                $deleteIds = array_diff($deleteIds, $realtionUpdateIds);
                            }
                            $relationData = [];
                            $fields = array_keys($post[$relation]);
                            foreach ($fields as $field) {
                                foreach ($post[$relation][$field] as $key => &$val) {
                                    if(is_array($val)){
                                        $index = 0;
                                        foreach ($val as $i=>$v){
                                            $relationData[$index][$field] = $v;
                                            $index++;
                                        }
                                    } else{
                                        $relationData[$key][$field] = $val;
                                    }
                                }
                            }
                            if (count($deleteIds) > 0) {
                                $this->model->$relation()->whereIn('id', $deleteIds)->delete();
                            }

                              $this->model->$relation()->saveAll($relationData);

                        } elseif ($this->model->$relation() instanceof BelongsToMany) {

                            $relationData = $post[$relation];
                            if (is_string($relationData)) {
                                $relationData = explode(',', $relationData);
                                $relationData = array_filter($relationData);
                            }
                            if (empty($this->data)) {
                                $pk = $this->model->getPk();
                                $this->data = $this->model->find($this->model->$pk);
                            }

                            $this->data->$relation()->detach();
                            if (count($relationData) > 0) {

                                $res = $this->data->$relation()->saveAll($relationData);
                            }
                        }

                    }
                    if (!is_null($this->afterSave)) {

                        call_user_func_array($this->afterSave, [Request::post(), $this->model]);
                    }

                } else {
                    //不传入模型的时候默认配置表
                    foreach ($post as $name => $value) {
                        if (Db::name($this->configTable)->where('name', $name)->count() > 0) {
                            $res = Db::name($this->configTable)->where('name', $name)->setField('value', $value);
                        } else {
                            $res = Db::name($this->configTable)->insert([
                                'name' => $name,
                                'value' => $value
                            ]);
                        }
                    }
                }

                Db::commit();

                if ($res || $this->model == null) {
                    throw new HttpResponseException(json(['code' => 1, 'msg' => lang('build_view_action_success'), 'data' => []]));
                } else {
                    throw new HttpResponseException(json(['code' => 0, 'msg' => lang('build_view_action_error'), 'data' => []]));
                }
            } catch (Exception $e) {
                Db::rollback();
                throw new HttpResponseException(json(['code' => 0, 'msg' => lang('build_view_action_error'), 'data' => []]));
            }
        }
    }

    /**
     * 设置提交按钮文字
     * @return string
     */
    public function setSubmitText($text)
    {
        $this->setOption('submitText', $text);
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
        if (isset($this->options['theme'])) {
            $html = str_replace('layui-form-label', 'layui-form-label color-green', $html);
        }
        $this->setOption('content', $html);

        return $this->render();
    }

    //添加formitem
    protected function formItem($template, $field, $lable)
    {
        if ($this->model instanceof Model) {
            if (is_array($field)) {
                foreach ($field as $value) {
                    list($tmp_val, $tmp_rawVal) = $this->getData($value);
                    $val[] = $tmp_val;
                    $rawVal[] = $tmp_rawVal;
                }

            } else {
                if ($template == 'checkbox' || $template == 'select' || $template == 'selectGroup' || $template == 'selectIframe' || $template == 'selectServiceIframe' || $template == 'selectCourseIframe') {
                    if (method_exists($this->model, $field)) {
                        if ($this->model->$field() instanceof BelongsToMany) {
                            array_push($this->relationArr, $field);
                            $pk = $this->model->$field()->getPk();
                            $relationData = $this->data->$field;
                            if (is_null($relationData)) {
                                $val = [];
                            } else {
                                $val = $relationData->column($pk);
                            }
                        }
                    } else {
                        list($val, $rawVal) = $this->getData($field);
                    }
                } else {
                    list($val, $rawVal) = $this->getData($field);
                }
            }
        } else {
            if (is_array($field)) {
                foreach ($field as $value) {
                    $temp_val = Db::name($this->configTable)->where('name', $value)->value('value');
                    $val[] = $temp_val;
                    $rawVal[] = $temp_val;
                }
            } else {
                $val = Db::name($this->configTable)->where('name', $field)->value('value');
                $rawVal = $val;
            }
        }
        if(is_array($field)){
            $fieldArr = [];
            foreach ($field as $f){
                $names = explode('.', $f);
                if (count($names) > 1) {
                    $relationMethod = $names[0];
                    if (method_exists($this->model, $relationMethod)) {
                        if ($this->model->$relationMethod() instanceof BelongsTo || $this->model->$relationMethod() instanceof HasOne) {
                            if (!in_array($names[0], $this->relationArr)) {
                                array_push($this->relationArr, $names[0]);
                            }
                        }
                    }
                    $fieldArr[] = "{$relationMethod}[{$names[1]}]";
                }else{
                    $fieldArr[] = $names[0];
                }
            }
            $field = $fieldArr;
        }else{
            $names = explode('.', $field);
            if (count($names) > 1) {
                $relationMethod = $names[0];
                if (method_exists($this->model, $relationMethod)) {
                    if ($this->model->$relationMethod() instanceof BelongsTo || $this->model->$relationMethod() instanceof HasOne) {
                        if (!in_array($names[0], $this->relationArr)) {
                            array_push($this->relationArr, $names[0]);
                        }
                    }
                }
                $field = "{$relationMethod}[{$names[1]}]";
            }
        }

        $item = new Field($template, $lable, $field, $val, $rawVal);
        if ($template == 'image') {
            $item->getData(true);
        }
        $item->setBuildForm($this);
        array_push($this->formItem, $item);
        return $item;
    }

    public function __call($name, $arguments)
    {
        return $this->formItem($name, $arguments[0], $arguments[1]);
    }

    /**
     * 一对多
     * @param $label 标签
     * @param $relationMethod 关联方法
     * @param \Closure $closure
     */
    public function hasMany($label, $relationMethod, \Closure $closure)
    {
        if (method_exists($this->model, $relationMethod)) {
            if ($this->model->$relationMethod() instanceof HasMany) {
                array_push($this->relationArr, $relationMethod);
                array_push($this->formItem, ['type' => 'hasMany', 'label' => $label, 'relationMethod' => $relationMethod, 'closure' => $closure]);
            } else {
                abort(100, '关联方法不是一对多');
            }
        } else {
            abort(100, '无效关联方法');
        }
    }

    //tab布局
    public function tab($title, \Closure $closure)
    {
        array_push($this->formItem, ['type' => 'tab', 'title' => $title, 'closure' => $closure]);
        return $this;
    }

    //获取字段数据
    protected function getData($field)
    {
        $fields = explode('.', $field);
        $val = $this->data;
        $rawVal = $this->data;
        $tableFields = $this->tableFields;
        foreach ($fields as $f) {
            if (isset($val[$f])) {
                if (is_object($val)) {
                    $tableFields = $val->getTableFields();
                    if (in_array($f, $tableFields)) {
                        $rawVal = $val->getData($f);
                    } else {
                        $rawVal = '';
                    }
                } else {
                    $rawVal = $val[$f];
                }
                $val = $val[$f];
            } else {
                $val = '';
                $rawVal = '';
            }
        }
        return [$val, $rawVal];
    }

    //解析formhtml
    private function parseFormItem($html, $hasManyHtml)
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
                                $f->value('');
                                $f->rawValue('');
                                $f->field($f->name);
                            }
                            $f->name("{$form['relationMethod']}[{$f->field}][]");
                            $hasManyjsHtml = $hasManyjsHtml . $f->render();

                        }

                        $hasManyjsHtml = '<div class="layui-row">' . $hasManyjsHtml . '<div class="layui-form-item"><div class="layui-input-block"><button type="button" class="layui-btn layui-btn-danger" data-hasMany="hasManyDel">' . lang('build_view_action_remove_btn') . '</button></div></div></div>';

                        foreach ($this->data[$form['relationMethod']] as $val) {

                            $hasItemHtml = '';
                            $idField = new Field('hidden', 'id', "{$form['relationMethod']}[id][]", $val['id']);

                            foreach ($this->formItem as $k => $f) {
                                if (is_null($f->field)) {
                                    $f->field($f->name);
                                }
                                if (is_object($val)) {
                                    if(is_array($f->field)){
                                        $rawVal = [];
                                        foreach ($f->field as $tmp_field){
                                            $rawVal[] = $val->getData($tmp_field);
                                        }
                                    }else{
                                        $rawVal = $val->getData($f->field);
                                    }
                                } else {
                                    if(is_array($f->field)){
                                        $rawVal = [];
                                    }else{
                                        $rawVal = '';
                                    }

                                }
                                if(is_array($f->field)){
                                    $tmp_nams = [];
                                    foreach ($f->field as $tmp_field){
                                        $f->value($val[$tmp_field]);
                                        $tmp_nams[]= "{$form['relationMethod']}[{$tmp_field}][]";
                                    }
                                    $f->name($tmp_nams);
                                }else{
                                    $f->value($val[$f->field]);
                                    $f->name("{$form['relationMethod']}[{$f->field}][]");
                                }
                                $f->rawValue($rawVal);
                                $hasItemHtml = $hasItemHtml . $f->render();
                            }
                            $hasItemHtml = '<div class="layui-row">' . $idField->render() . $hasItemHtml . '<div class="layui-form-item"><div class="layui-input-block"><button type="button" class="layui-btn layui-btn-danger" data-hasMany="hasManyDel">' . lang('build_view_action_remove_btn') . '</button></div></div></div>';
                            $hasManyHtml .= $hasItemHtml;

                        }
                        $hasManyField = new Field('hasMany', $form['label'], $form['relationMethod'], $hasManyHtml);
                        $hasManyHtml = '';
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
                    if ($this->tabCount == 1 || $this->tabCount == $this->tabNum) {

                        $html = $this->tabField->render();

                    }
                    $this->formItem = $formItemArr;
                }
            }
        }
        return $html;
    }

    //保存后回调
    public function saved(\Closure $closure)
    {
        $this->afterSave = $closure;
    }

    //保存前回调
    public function saving(\Closure $closure)
    {
        $this->beforeSave = $closure;
    }
}
