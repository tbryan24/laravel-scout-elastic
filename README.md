# laravel-scout-elastic

基于https://github.com/ErickTamayo/laravel-scout-elastic 改造

### 1、composer安装

安装前需要提前安装官方scout扩展https://learnku.com/docs/laravel/8.x/scout/9422和elasticsearch的php扩展，具体的scout配置说明见官方文档

首先安装php elasticsearch扩展包

```php
composer require elasticsearch/elasticsearch
```

然后，安装laravel官方 Scout扩展：

```php
composer require laravel/scout
```

Scout 安装完成后，使用 vendor:publish Artisan 命令来生成 Scout 配置文件。这个命令将在你的 config 目录下生成一个 scout.php 配置文件。

php artisan vendor:publish --provider="Laravel\Scout\ScoutServiceProvider"

```php
composer require elasticsearch/elasticsearch
```

然后安装：

```php
composer require tbryan24/laravel-scout-elastic
```

### 2、注册服务提供者

扩展包里有一个服务提供者，使用包的时候需要在config/app.php的providers中注册服务提供者

```php
Tbryan24\LaravelScoutElastic\ElasticScoutProvider::class
```

## 3、模型配置可搜索

在搜索的模型中添加`Tbryan24\LaravelScoutElastic\EsSearchable`  该trait继承的是`Laravel\Scout\Searchable` 。这个 trait 会注册一个模型观察者来保持模型和所有驱动的同步：

```php
<?php

namespace App\Models;

use Jenssegers\Mongodb\Eloquent\Model;
use Tbryan24\LaravelScoutElastic\EsSearchable;

class LifeMomentsPost extends Model
{
    use EsSearchable;
}
```



### 4、配置模型搜索索引

在模型中引入Tbryan24\LaravelScoutElastic\EsSearchable,可以过重写模型上的 `searchableAs` 方法来自定义模型的索引

```php
<?php

namespace App\Models;

use Jenssegers\Mongodb\Eloquent\Model;
use Tbryan24\LaravelScoutElastic\EsSearchable;

class LifeMomentsPost extends Model
{
    use EsSearchable;

    /**
     * 重写模型上的 searchableAs 方法来自定义模型的索引
     * @return string
     */
    public function searchableAs()
    {
        return 'posts_index';
    }
}
```

### 5、配置可搜索数据

默认情况下，模型以完整的 `toArray` 格式持久化到搜索索引。如果要自定义同步到搜索索引的数据，可以覆盖模型上的 `toSearchableArray` 方法：

```php
<?php

namespace App\Models;

use Jenssegers\Mongodb\Eloquent\Model;
use Tbryan24\LaravelScoutElastic\EsSearchable;

class LifeMomentsPost extends Model
{
    use EsSearchable;

    /**
     * 重写模型上的 searchableAs 方法来自定义模型的索引
     * @return string
     */
    public function searchableAs()
    {
        return 'posts_index';
    }
    
    /**
     * 获取模型的可搜索数据。
     *
     * @return array
     */
    public function toSearchableArray()
    {
        /*$data=$this->toArray();
        $data['id']=$data["_id"];
        unset($data["_id"]);
        return $data;*/
        //return $this->toArray();//如果你用的是mongodb作为主库，这里直接返回会有问题，mongodb的_id与es的_id冲突
        // 自定义数组...
		return [
            'id' => $this->_id,
            'title' => $this->title,
            'content' => $this->content,
            'type' => $this->type,
            'status' => $this->status,
            'fabulous_count' => $this->fabulous_count,
            'views' => $this->views,
            'account_id' => $this->account_id,
            'suggest' => $this->title,
            // 需要注意 时间字段 需要转义，否则会失败
            'created_at' => $this->created_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
```



### 6、当然你还可以在模型中自定义查询结构

正常情况下search方法传入的是你的搜索关键词（字符串），为了实现更多功能你可以采用该方式传入构造好的body数组，该方式比较灵活，你可以基于es原生构造所有你希望实现的查询，比如你要嵌套多层bool来实现复杂的过滤需求，模型中新增方法getSearchBody用于构造查询的body（暂定方式）

```php
/**
     * Notes: 构造es搜索查询的body
     * Author: wangchengfei
     * DataTime: 2022/4/8 13:30
     * @param $content
     * @param int $page
     * @param int $pageSize
     * @return array
     */
public function getSearchBody($content, $page = 1, $pageSize = 10)
{
    $body = [
        'query' => [
            'bool' => [
                'must' => [
                    [
                        "bool" => [
                            "should" => [
                                [
                                    "term" => [
                                        "status" => [
                                            "value" => "0"
                                        ]
                                    ]
                                ],
                                [
                                    "term" => [
                                        "status" => [
                                            "value" => "2"
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ],
                    [
                        'match' => [
                            'title' => [
                                'query' => $content,
                            ]
                        ]
                    ],
                ],
            ]
        ],
        "highlight" => [
            "fields" => [
                'title' => new \stdClass(),
            ],
            'pre_tags' => ["<em style='color:red'>"],
            'post_tags' => ["</em>"]
        ],
        "from" => ($page - 1) * $pageSize,
        "size" => $pageSize
    ];
    return $body;
}
```

如果你采用了以上方式，你就不需要链式调用高亮，排序等方法了，全都可以在body里实现，如果你调用了高亮等那就会重写该部分内容,比如你在Repository中使用自定义body，你就可以像如下方式使用：

```php
$body = $this->lifeMomentsPostModel->getSearchBody($content, $page, $pageSize);
$esData = LifeMomentsPost::search($body)->raw();
```



## 分词解析

获取分词解析后的最终数据（对单个汉字或字母进行了过滤和对分词结果进行了排重）

```php
LifeMomentsSearchKeywords::search()->analyze($keywords)->getTokens()
```

获取分词解析的原始数据（es返回的结构）

```php
LifeMomentsSearchKeywords::search()->analyze($keywords)->raw();
```

## 搜索建议用法

```php
LifeMomentsSearchKeywords::search()->suggest($keywords)->raw();
```

## 高亮的用法

```php
LifeMomentsPost::search($keywords)->highLight(["title","content"],"<p style='color:red'>","</p>")->raw();
```
