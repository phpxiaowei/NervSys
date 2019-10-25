<?php

/**
 * NS Core Tunnel script
 *
 * Copyright 2016-2019 Jerry Shaw <jerry-shaw@live.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace core;

use core\lib\stc\factory;
use core\lib\std\pool;

/**
 * Class nct
 *
 * @package core
 */
final class nct
{
    /**
     * Register CMD router parser
     *
     * @param array $router
     */
    public static function register_router(array $router): void
    {
        factory::build(pool::class)->router_stack[] = $router;
        unset($router);
    }

    /**
     * Set error content
     *
     * @param array $error
     */
    public static function set_error(array $error): void
    {
        /** @var \core\lib\std\pool $unit_pool */
        $unit_pool = factory::build(pool::class);

        //Replace error content
        $unit_pool->error = array_replace_recursive($unit_pool->error, $error);

        unset($error, $unit_pool);
    }

    /**
     * Set data
     *
     * @param string $key
     * @param        $value
     */
    public static function add_data(string $key, $value): void
    {
        factory::build(pool::class)->data[$key] = $value;
        unset($key, $value);
    }

    /**
     * Get data
     *
     * @param string $key
     *
     * @return mixed|null
     */
    public static function get_data(string $key)
    {
        return factory::build(pool::class)->data[$key] ?? null;
        unset($key);
    }

    /**
     * Get client IP
     *
     * @return string
     */
    public static function get_ip(): string
    {
        return factory::build(pool::class)->ip;
    }

    /**
     * CLI running mode
     *
     * @return bool
     */
    public static function is_CLI(): bool
    {
        return factory::build(pool::class)->is_CLI;
    }

    /**
     * Request vis TLS
     *
     * @return bool
     */
    public static function is_TLS(): bool
    {
        return factory::build(pool::class)->is_TLS;
    }
}