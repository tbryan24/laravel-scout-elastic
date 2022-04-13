<?php

namespace Tbryan24\LaravelScoutElastic;


use Laravel\Scout\Searchable;

trait EsSearchable
{
    use Searchable;

    /**
     * Perform a search against the model's indexed data.
     *
     * @param  string  $query
     * @param  \Closure  $callback
     * @return \Laravel\Scout\Builder
     */
    public static function search($query = '', $callback = null)
    {
        return app(EsBuilder::class, [
            'model' => new static,
            'query' => $query,
            'callback' => $callback,
            'softDelete'=> static::usesSoftDelete() && config('scout.soft_delete', false),
            'analyze' => $query,
        ]);
    }

    public function suggestAs()
    {
        return [];
    }
}
