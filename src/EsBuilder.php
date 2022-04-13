<?php

namespace Tbryan24\LaravelScoutElastic;


use Laravel\Scout\Builder;

class EsBuilder extends Builder
{
    public $analyze;

    public $suggest = [];

    public function __construct($model, $query, $callback = null, $softDelete = false, $analyze = null)
    {
        $this->analyze = $analyze;
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
    public function analyze($text='',$analyzer='ik_smart')
    {
        try {
            $this->analyze = [
                "text" => $text,
                "analyzer" => "ik_smart"
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
                        "size" => $suggest['size']
                    ]
                ]

            ];

        return $this;
    }
}
