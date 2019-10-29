# buildView
##ThinkPhP 适用于ThinkAdmin后台框架，layui构建视图增删改查。
使用下面的命令来快速创建一个对应模型和控制器
>php think make:admin UserController --model=User
---
表格基本使用
```
use buildView\Grid;
use app\common\model\User;

$grid = new Grid(new User);

//设置标题
$grid->setTitle('列表');

//Thinkphp数据库的链式操作
$grid->model()->where('id',2);

//添加一列，nickname数据库字段
$grid->nickname('昵称');

// 效果和上面一样
$grid->column('nickname', '昵称');

// 添加一列徽章
$grid->column('nickname', '昵称')->badge();
//绿色
$grid->column('nickname', '昵称')->badge('green');

//using使用场景
$grid->column('is_pay', '付款状态')->using([
    1=>'待付款',
    2=>'已付款',
]);
// 图片显示 wdith列宽度，image第一个参数圆角，第二参数图片宽度，第三次参数高度
$grid->nickname('昵称')->width(200)->image(50,200,80);

//列自定义显示
$grid->headimg('微信昵称')->display(function ($val, $data,$html) {
            //$val 当前值
            //$data 当前行数据
            //$html 当前值html
            return $html . '<br>' . $data['nickname'];
        })->image(50);
        
//头像昵称列
三个参数，以下列出的是默认参数
$grid->userInfo('headimg','nickname','会员信息');

// 可编辑列
$grid->nickname('昵称')->editor();

// 可开关switch按钮
$grid->nickname('昵称')->switchs();

// 开启合计行
$grid->total('价格')->totalRow();
 
// 开启排序
$grid->total('价格')->sort();

// 设置列宽度
$grid->total('价格')->width(200);

// 自定义开关switch按钮
$grid->nickname('昵称')->switchs([
    'on'=>['text'=>'开','value'=>'1'],
    'off'=>['text'=>'关','value'=>'-1']
]);
//一对一，多对一实例
user为对应模型关联定义
$grid->user()->nickname('昵称');


//隐藏操作列
$grid->hideAction();

//隐藏添加按钮
$grid->hideAddButton();

//隐藏表格导出按钮
$grid->hideExportButton();

//隐藏批量删除按钮
$grid->hideDeletesButton();

//隐藏多选框
$grid->hideColumnSelect();

//禁用分页
$grid->hidePage();

//添加工具栏可以是纯html或Button组件
$html ="<span>这里展示</span>"
$grid->addTools($html);
//Button组件
$button = new Button('同意换货');
$button->href(url('passChange') . "?id={$data['id']}", 'modal');
$grid->addTools($button);

//操作列单个隐藏
$grid->actions(function (Actions $actions,$data){
            //$data 当前行数据
            //隐藏删除按钮        
           $actions->hideDel();
           //隐藏详情按钮
           $actions->hideDetail();
           //隐藏编辑按钮
           $actions->hideEdit();
           //操作列尾部追加，用法和工具栏一样，可以是纯html或Button组件
           $action->append($button);
           //操作列前追加，用法和工具栏一样，可以是纯html或Button组件
           $action->prepend($button);
 });
 //筛选
 $grid->filter(function ($filter){
             //等于筛选  
             $filter->eq('nickname','昵称');
             
             //一对一，多对一实例
             $filter->eq('user.nickname','昵称');
              
             //模糊筛选
             $filter->like('nickname','昵称');
             //时间区间筛选
             $filter->dateBetween('create_at','时间');
  });
  // 最后渲染视图
  return $grid->view(); 
```
---
form表单
```
$form = new Form(new User);
//设置标题
$form->setTitle('编辑');
//数字框 必选 最小值 最大值
$form->number('num','数量')->required()->min(1)->max(4);

//文本框
$form->text('title','标题');
//一对一，多对一
$form->text('user.nickname','昵称');

//设置默认值
$form->text('title','标题')->default(10);
//设置value值
$form->text('title','标题')->value(10);

//富文本框
$form->ckeditor('content','标题');

//图片上传,裁剪200*200图片
$form->image('img','图片')->size([200,200]);

//多图上传
$form->image('img','图片')->multiple(true);

//文件上传 只允许jpg
$form->file('img','图片')->uploadType('jpg');

//单选下拉框
$form->select('options','图片')->options([
    1=>'第一个',
    2=>'第二个',
]);

//多选下拉框
$form->select('options','图片')->options([
    1=>'第一个',
    2=>'第二个',
])->multiple(true);

//radio
$form->radio('options','图片')->options([
    1=>'第一个',
    2=>'第二个',
]);
//switch
$form->switch('status','开关');
$form->switch('status','开关设置参数')->swithcStates([
            'on'=>['text'=>'开启','value'=>1],
            'off'=>['text'=>'关闭','value'=>0],
        ]);
//滑块
$form->slider('sre','滑块');

//时间
$form->time('today','时间');
//日期
$form->date('today','日期');
//日期时间
$form->datetime('today','日期');
//日期范围
$form->dateRange('today','日期');
//日期范围两个字段,设置format参数具体参照layui日期组件
$form->dateRange(['start_date','end_date'],'日期')->format('yyyy年MM月dd日');
//保存前
$form->saving(function ($post) {
    //$post当前提交数据
    //执行代码
});
//保存后回调
$form->saved(function ($post, $model) {
    //$post当前提交数据,$model保存后的模型
    //执行代码
});
// 最后渲染视图
return $form->view(); 
```
---
Detail详情
```
$detail = new Detail(new ShopOrder);
//设置标题
$detail->setTitle('订单详情');
//添加一行，pay_price数据库字段
$detail->pay_price('支付金额￥');
//一对一，多对一
$detail->user()->nickname('昵称');
//图片显示
$detail->spec_img('商品规格图')->image();
//一对多，goods_record为对应模型关联方法
$detail->hasMany('goods_record', '商品信息', 12, function ($detail) {
            $detail->spec_img('商品规格图')->image();
            $detail->spec_name('商品规格');
            $detail->good_title('商品标题');
            $detail->price('商品售价');
            $detail->num('数量');
});
// 最后渲染视图
return $detail->view();
```

