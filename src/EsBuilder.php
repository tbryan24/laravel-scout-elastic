<?php

namespace Tbryan24\LaravelScoutElastic;


use Illuminate\Support\Collection;
use Laravel\Scout\Builder;
use Tbryan24\LaravelScoutElastic\Trait\IndexSetting;

class EsBuilder extends Builder
{
    use IndexSetting;

    public $analyze;//分词解析

    public $suggest = [];//搜索建议

    public $highLight = [];//高亮

    public function __construct($model, $query, $callback = null, $softDelete = false, $analyze = null)
    {
        parent::__construct($model, $query, $callback, $softDelete);
    }

    /**
     * Notes: add nalyzer
     * Author: wangchengfei
     * DataTime: 2022/4/12 17:29
     * @return mixed
     */
    public function getTokens()
    {
        return $this->engine()->getTokens($this);
    }

    /**
     * Notes: 分词解析器
     * Author: wangchengfei
     * DataTime: 2022/4/13 16:25
     * @param string $text
     * @param string $analyzer ik_max_word or ik_smart
     * @return $this
     * @throws \Exception
     */
    public function analyze($text = '', $analyzer = 'ik_smart')
    {
        try {
            $this->analyze = [
                "text" => $text,
                "analyzer" => $analyzer
            ];
        } catch (\Exception $exception) {
            throw new \Exception($exception->getMessage());
        }

        return $this;
    }

    /**
     * Notes: search suggest
     * Author: wangchengfei
     * DataTime: 2022/4/13 15:56
     * @param $query
     * @return $this
     * @throws \Exception
     */
    public function suggest($query)
    {
        $suggest = $this->model->suggestAs();
        if (empty($suggest)) {
            throw new \Exception("模型内未配置搜索建议");
        }
        $suggest_name = $suggest['suggest_name'] ?? "result";
        $this->suggest =
            [
                "text" => $query,
                $suggest_name => [
                    "completion" => [
                        "field" => $suggest['field'],
                        "size" => $suggest['size'],
                        "skip_duplicates" => true,//过滤掉重复结果
                    ]
                ]

            ];

        return $this;
    }

    /**
     * Notes: 高亮功能
     * Author: wangchengfei
     * DataTime: 2022/4/14 10:12
     * @param array $columns
     * @param string $pre_tags
     * @param string $post_tags
     * @throws \Laravel\Octane\Exceptions\DdException
     */
    public function highLight($columns = [], $pre_tags = "<em style='color:red'>", $post_tags = "</em>")
    {
        $fields = Collection::make($columns)->map(
            function ($items) {
                return [$items => new \stdClass()];
            }
        )->flatMap(
            function ($values) {
                return $values;
            }
        )->all();
        $highlight = [
            "fields" => $fields,
            'pre_tags' => [$pre_tags],
            'post_tags' => [$post_tags]
        ];
        $this->highLight = $highlight;
        return $this;
    }
}
