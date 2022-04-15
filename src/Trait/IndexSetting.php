<?php

namespace Tbryan24\LaravelScoutElastic\Trait;

trait IndexSetting
{
    protected $engine;
    public $body = [];

    public static function bootIndexSetting()
    {
    }

    public function createIndex($body)
    {
        $this->body = $body;
        return $this->engine()->createIndexNew($this);
    }
}
