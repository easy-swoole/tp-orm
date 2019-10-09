# Easyswoole-ORM

## 单元测试
```php
 ./vendor/bin/co-phpunit tests
```

推荐使用mysql著名的employees样例库进行测试和学习mysql: https://github.com/datacharmer/test_db

## 基础定义

定义一个模型

```php

<?php

namespace EasySwoole\ORM\Tests;

use EasySwoole\ORM\AbstractModel;
use EasySwoole\ORM\Utility\Schema\Table;

/**
 * 用于测试的用户模型
 * Class UserModel
 * @package EasySwoole\ORM\Tests
 */
class UserModel extends AbstractModel
{
    /**
     * 表的定义
     * 此处需要返回一个 EasySwoole\ORM\Utility\Schema\Table
     * @return Table
     */
    protected function schemaInfo(): Table
    {
        $table = new Table('siam');
        $table->colInt('id')->setIsPrimaryKey(true);
        $table->colChar('name', 255);
        $table->colInt('age');
        return $table;
    }

}

```

## 开始使用

```php

<?php

use EasySwoole\Mysqli\QueryBuilder;
use EasySwoole\ORM\DbManager;
use EasySwoole\ORM\Tests\UserModel;
use Swoole\Coroutine;
use EasySwoole\ORM\Db\Connection;
use EasySwoole\ORM\Db\Config;

require_once 'vendor/autoload.php';

// Add default connection
$config = new Config();
$config->setDatabase('easyswoole_orm');
$config->setUser('root');
$config->setPassword('');
$config->setHost('127.0.0.1');

DbManager::getInstance()->addConnection(new Connection($config));

// Use connection in coroutine
Coroutine::create(function () {
    // orm use code 
    // swoole mysql must be use in coroutine env.
    // if in onRequest ...method
    // it will use coroutine automatic
});

```

## 查
```php
<?php

// 获取单条(返回一个模型)
$res = UserModel::create()->get(10001);
$res = UserModel::create()->get(['emp_no' => 10001]);
var_dump($res); // 如果查询不到则为null
// 不同获取字段方式
var_dump($res->emp_no);
var_dump($res['emp_no']);

// 批量获取 返回一个数组  每一个元素都是一个模型对象
$res = UserModel::create()->all([1, 3, 10003]);
$res = UserModel::create()->all('1, 3, 10003');
$res = UserModel::create()->all(['name' => 'siam']);
$res = UserModel::create()->all(function (QueryBuilder $builder) {
    $builder->where('name', 'siam');
});
var_dump($res);

```

## 改

```php
<?php

// 可以直接静态更新
$res = UserModel::create()->update([
    'name' => 'new'
], ['id' => 1]);

// 根据模型对象进行更新（无需写更新条件）
$model = UserModel::create()->get(1);

// 不同设置新字段值的方式
$res = $model->update([
    'name' => 123,
]);
$model->name = 323;
$model['name'] = 333;

// 调用保存  返回bool 成功失败
$res = $model->update();
var_dump($res);
```
## 增

```php
<?php
$model = new UserModel([
    'name' => 'siam',
    'age'  => 21,
]);
// 不同设置值的方式
// $model->setAttr('id', 7);
// $model->id = 10003;

$res = $model->save();
var_dump($res); // 返回自增id 或者主键的值  失败则返回null
```

## 删

```php
<?php
// 删除(返回影响的记录数)
// 不同方式
$res = UserModel::create()->destroy(1);
$res = UserModel::create()->destroy('2,4,5');
$res = UserModel::create()->destroy([3, 7]);
$res = UserModel::create()->destroy(['age' => 21]);
$res = UserModel::create()->destroy(function (QueryBuilder $builder) {
    $builder->where('id', 1);
});

var_dump($res);
```
