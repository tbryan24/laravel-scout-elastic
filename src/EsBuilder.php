<?php

namespace Tbryan24\LaravelScoutElastic;


use Laravel\Scout\Builder;

class EsBuilder extends Builder
{
    public $analyze;

    public function __construct($model, $query, $callback = null, $softDelete = false,$analyze=null)
    {
        $this->analyze=$analyze;
        parent::__construct($model, $query, $callback, $softDelete);
    }

    /**
     * Notes: add nalyzer
     * Author: wangchengfei
     * DataTime: 2022/4/12 17:29
     * @return mixed
     */
    public function esAnalyze()
    {
        return $this->engine()->analyze($this);
    }
}
