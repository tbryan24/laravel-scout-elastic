# laravel-scout-elastic

基于https://github.com/ErickTamayo/laravel-scout-elastic 改造

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
