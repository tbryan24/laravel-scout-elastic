<?php

namespace Tbryan24\LaravelScoutElastic\Engines;


use Elasticsearch\Client as Elastic;
use Illuminate\Database\Eloquent\Collection;
use Laravel\Scout\Builder;
use Laravel\Scout\Engines\Engine;

use function PHPUnit\Framework\isNull;

class ElasticsearchEngine extends Engine
{
    /**
     * Elastic client.
     *
     * @var Elastic
     */
    protected $elastic;

    /**
     * Create a new engine instance.
     *
     * @param \Elasticsearch\Client $elastic
     * @return void
     */
    public function __construct(Elastic $elastic)
    {
        $this->elastic = $elastic;
    }

    /**
     * Notes:通过惰性集合将给定结果映射到给定模型的实例
     * Author: wangchengfei
     * DataTime: 2022/4/13 10:22
     * @param Builder $builder
     * @param mixed $results
     * @param \Illuminate\Database\Eloquent\Model $model
     * @return Collection|\Illuminate\Support\Collection|\Illuminate\Support\LazyCollection
     */
    public function lazyMap(Builder $builder, $results, $model)
    {
        return Collection::make($results['hits']['hits'])->map(
            function ($hit) use ($model) {
                return $model->newFromBuilder($hit['_source']);
            }
        );
    }

    public function createIndex($name, array $options = [])
    {
        $params = [
            'index' => $name,
        ];

        if (isset($options['shards'])) {
            $params['body']['settings']['number_of_shards'] = $options['shards'];
        }

        if (isset($options['replicas'])) {
            $params['body']['settings']['number_of_replicas'] = $options['replicas'];
        }

        $this->elastic->indices()->create($params);
    }

    public function deleteIndex($name)
    {
        $this->elastic->indices()->delete(['index' => $name]);
    }

    /**
     * Update the given model in the index.
     *
     * @param Collection $models
     * @return void
     */
    public function update($models)
    {
        if ($models->isEmpty()) {
            return;
        }

        $params['body'] = [];

        $models->each(
            function ($model) use (&$params) {
                $params['body'][] = [
                    'update' => [
                        '_id' => $model->getScoutKey(),
                        '_index' => $model->searchableAs(),
                        //'_type' => get_class($model),//这里不设置type，在新版本的es中将被舍弃
                    ]
                ];
                $params['body'][] = [
                    'doc' => $model->toSearchableArray(),
                    'doc_as_upsert' => true
                ];
            }
        );

        $this->elastic->bulk($params);
    }

    /**
     * Remove the given model from the index.
     *
     * @param Collection $models
     * @return void
     */
    public function delete($models)
    {
        $params['body'] = [];

        $models->each(
            function ($model) use (&$params) {
                $params['body'][] = [
                    'delete' => [
                        '_id' => $model->getKey(),
                        '_index' => $model->searchableAs(),
                        '_type' => get_class($model),
                    ]
                ];
            }
        );

        $this->elastic->bulk($params);
    }

    /**
     * Perform the given search on the engine.
     *
     * @param Builder $builder
     * @return mixed
     */
    public function search(Builder $builder)
    {
        return $this->performSearch(
            $builder,
            array_filter(
                [
                    'numericFilters' => $this->filters($builder),
                    'size' => $builder->limit,
                ]
            )
        );
    }

    /**
     * Perform the given search on the engine.
     *
     * @param Builder $builder
     * @param int $perPage
     * @param int $page
     * @return mixed
     */
    public function paginate(Builder $builder, $perPage, $page)
    {
        $result = $this->performSearch(
            $builder,
            [
                'numericFilters' => $this->filters($builder),
                'from' => (($page * $perPage) - $perPage),
                'size' => $perPage,
            ]
        );

        $result['nbPages'] = $result['hits']['total'] / $perPage;

        return $result;
    }

    /**
     * Perform the given search on the engine.
     *
     * @param Builder $builder
     * @param array $options
     * @return mixed
     */
    protected function performSearch(Builder $builder, array $options = [])
    {
        $callback=$builder->callback;
        if (is_array($builder->query)) {
            $body = $builder->query;
            $params = [
                'index' => $builder->model->searchableAs(),
                'body' => $body
            ];
        } else {
            $params = [
                'index' => $builder->model->searchableAs(),
                //'type' => get_class($builder->model),
                'body' => [
                    'query' => [
                        'bool' => [
                            'must' => [['query_string' => ['query' => "{$builder->query}"]]]
                        ]
                    ]
                ]
            ];
        }

        if ($sort = $this->sort($builder)) {
            $params['body']['sort'] = $sort;
        }

        if (isset($options['from'])) {
            $params['body']['from'] = $options['from'];
        }

        if (isset($options['size'])) {
            $params['body']['size'] = $options['size'];
        }
        if ($suggest = $this->suggest($builder)) {
            $params['body']['suggest'] = $suggest;
            unset($params['body']['query']);
            return $this->elastic->search($params);
        }

        if (isset($options['numericFilters']) && count($options['numericFilters'])) {
            $params['body']['query']['bool']['must'] = array_merge(
                $params['body']['query']['bool']['must'],
                $options['numericFilters']
            );
        }

        if ($builder->callback) {
            return call_user_func(
                $builder->callback,
                $this->elastic,
                $builder->query,
                $params
            );
        }

        /**
         * 这里使用了 highlight 的配置
         */
        if ($builder->model->searchSettings
            && isset($builder->model->searchSettings['attributesToHighlight'])
        ) {
            $attributes = $builder->model->searchSettings['attributesToHighlight'];
            foreach ($attributes as $attribute) {
                $params['body']['highlight']['pre_tags'] = ["<em style='color:red'>"];
                $params['body']['highlight']['post_tags'] = ["</em>"];
                $params['body']['highlight']['fields'][$attribute] = new \stdClass();
            }
        }
        return $this->elastic->search($params);
    }

    /**
     * Get the filter array for the query.
     *
     * @param Builder $builder
     * @return array
     */
    protected function filters(Builder $builder)
    {
        return collect($builder->wheres)->map(
            function ($value, $key) {
                if (is_array($value)) {
                    return ['terms' => [$key => $value]];
                }

                return ['match_phrase' => [$key => $value]];
            }
        )->values()->all();
    }

    /**
     * Pluck and return the primary keys of the given results.
     *
     * @param mixed $results
     * @return \Illuminate\Support\Collection
     */
    public function mapIds($results)
    {
        return collect($results['hits']['hits'])->pluck('_id')->values();
    }

    /**
     * Map the given results to instances of the given model.
     *
     * @param \Laravel\Scout\Builder $builder
     * @param mixed $results
     * @param \Illuminate\Database\Eloquent\Model $model
     * @return Collection
     */
    public function map(Builder $builder, $results, $model)
    {
        if ($results['hits']['total'] === 0) {
            return $model->newCollection();
        }

        $keys = collect($results['hits']['hits'])->pluck('_id')->values()->all();

        $modelIdPositions = array_flip($keys);

        return $model->getScoutModelsByIds(
            $builder,
            $keys
        )->filter(
            function ($model) use ($keys) {
                return in_array($model->getScoutKey(), $keys);
            }
        )->sortBy(
            function ($model) use ($modelIdPositions) {
                return $modelIdPositions[$model->getScoutKey()];
            }
        )->values();
    }

    /**
     * Get the total count from a raw result returned by the engine.
     *
     * @param mixed $results
     * @return int
     */
    public function getTotalCount($results)
    {
        return $results['hits']['total'];
    }

    /**
     * Flush all of the model's records from the engine.
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     * @return void
     */
    public function flush($model)
    {
        $model->newQuery()
            ->orderBy($model->getKeyName())
            ->unsearchable();
    }

    /**
     * Generates the sort if theres any.
     *
     * @param Builder $builder
     * @return array|null
     */
    protected function sort($builder)
    {
        if (count($builder->orders) == 0) {
            return null;
        }

        return collect($builder->orders)->map(
            function ($order) {
                return [$order['column'] => $order['direction']];
            }
        )->toArray();
    }

    /**
     * Notes: 实现分析器解析逻辑（暂时只针对分词解析）
     * Desc: 该方法对字符串进行分词拆解，然后排除单个字母或汉子，返回单词或者中文词条
     * Author: wangchengfei
     * DataTime: 2022/4/7 11:31
     * @param Builder $builder
     * @return array
     */
    public function analyze(Builder $builder)
    {
        try {
            $analyze = $builder->analyze;
            $params = [
                'index' => $builder->model->searchableAs(),
                'body' => $analyze
            ];
            $res = $this->elastic->indices()->analyze($params);
            $tokens = Collection::make($res['tokens'])->map(
                function ($items) {
                    $offset = $items['end_offset'] - $items['start_offset'];
                    //过滤掉单个汉字或者字母
                    if ($offset > 1) {
                        return $items['token'];
                    }
                }
            )->reject(
                function ($item) {
                    return is_null($item);
                }
            )->unique()->values();
        } catch (\Exception $exception) {
            throw new \Exception($exception->getMessage());
        }

        return $tokens;
    }

    /**
     * Notes: Generates the suggest if theres any.
     * Author: wangchengfei
     * DataTime: 2022/4/13 16:04
     * @param $builder
     * @return null
     */
    protected function suggest($builder){
        if (count($builder->suggest) == 0) {
            return null;
        }

        return $builder->suggest;
    }
}
