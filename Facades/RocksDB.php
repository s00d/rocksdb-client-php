<?php

namespace s00d\RocksDB\Facades;

use Illuminate\Support\Facades\Facade;

class RocksDB extends Facade {
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor() {
        return 'rocksdb';
    }
}
