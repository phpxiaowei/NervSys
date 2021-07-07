<?php

/**
 * Multi-Process Controller Extension
 *
 * Copyright 2016-2021 秋水之冰 <27206617@qq.com>
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

namespace Ext;

use Core\Execute;
use Core\Factory;
use Core\Lib\App;
use Core\Lib\Error;
use Core\Lib\IOUnit;
use Core\Lib\Router;
use Core\OSUnit;

/**
 * Class libMPC
 *
 * @package Ext
 */
class libMPC extends Factory
{
    private App     $app;
    private Error   $error;
    private Router  $router;
    private Execute $execute;
    private IOUnit  $io_unit;
    private OSUnit  $os_unit;

    public int $proc_idx = 0;
    public int $max_fork = 10;
    public int $max_exec = 1000;

    public string $php_path = '';
    public string $proc_cmd = '';

    public array $proc_list = [];
    public array $proc_exec = [];

    /**
     * libMPC constructor.
     */
    public function __construct()
    {
        $this->app     = App::new();
        $this->io_unit = IOUnit::new();
        $this->os_unit = OSUnit::new();
    }

    /**
     * Set PHP executable path
     *
     * @param string $php_path
     *
     * @return $this
     */
    public function setPhpPath(string $php_path): self
    {
        $this->php_path = &$php_path;

        unset($php_path);
        return $this;
    }

    /**
     * Exec cmd async
     *
     * @param string $c
     * @param array  $data
     *
     * @return bool
     */
    public function execAsync(string $c, array $data = []): bool
    {
        $cmd = $this->php_path . ' "' . $this->app->script_path . '"';
        $cmd .= ' -c"' . $this->io_unit->encodeData($c) . '"';

        if (!empty($data)) {
            $cmd .= ' -d"' . $this->io_unit->encodeData(json_encode($data, JSON_FORMAT)) . '"';
        }

        if (isset($data['argv'])) {
            $cmd .= ' ' . $data['argv'];
        }

        $proc = popen($this->os_unit->setCmd($cmd)->setAsBg()->setEnvPath()->fetchCmd(), 'rb');

        if (is_resource($proc)) {
            $result = true;
            pclose($proc);
        } else {
            $result = false;
        }

        unset($c, $data, $cmd, $proc);
        return $result;
    }

    /**
     * Start MPC
     *
     * @param int $max_fork
     * @param int $max_exec
     *
     * @return $this
     */
    public function start(int $max_fork = 10, int $max_exec = 1000): self
    {
        $this->max_fork = &$max_fork;
        $this->max_exec = &$max_exec;

        $this->proc_cmd = $this->php_path . ' "' . $this->app->script_path . '" -c"/' . __CLASS__ . '/procUnit"';

        //Initialize proc_exec data
        for ($i = 0; $i < $this->max_fork; ++$i) {
            $this->proc_exec[$i] = 0;
        }

        //Register MPC closeAll function
        register_shutdown_function([$this, 'closeAll']);

        unset($max_fork, $max_exec, $i);
        return $this;
    }

    /**
     * Add MPC job
     *
     * @param string $cmd
     * @param array  $data
     * @param int    $retry
     *
     * @return int
     */
    public function add(string $cmd, array $data = [], int $retry = 0): int
    {
        try {
            //Create process
            if ((!isset($this->proc_list[$this->proc_idx]) || !is_resource($this->proc_list[$this->proc_idx])) && !$this->createProc($this->proc_idx)) {
                unset($cmd, $data, $retry);
                return 0;
            }

            //Push data via STDIN
            fwrite($this->proc_list[$this->proc_idx], json_encode(['c' => &$cmd] + $data, JSON_FORMAT) . PHP_EOL);
        } catch (\Throwable $throwable) {
            //Retry 3 times
            if (3 > ++$retry) {
                $this->add($cmd, $data, $retry);
            } else {
                unset($cmd, $data, $retry, $throwable);
                return 0;
            }
        }

        //Check max executes
        if ((++$this->proc_exec[$this->proc_idx]) >= $this->max_exec) {
            $this->closeProc($this->proc_idx);
        }

        //Move/Reset proc_idx
        if ((++$this->proc_idx) >= $this->max_fork) {
            $this->proc_idx = 0;
        }

        unset($cmd, $data, $retry);
        return 1;
    }

    /**
     * Create process
     *
     * @param int $idx
     *
     * @return bool
     */
    public function createProc(int $idx): bool
    {
        //Create process
        $proc = popen($this->os_unit->setCmd($this->proc_cmd)->setEnvPath()->fetchCmd(), 'wb');

        if (!is_resource($proc)) {
            return false;
        }

        //Save process properties
        $this->proc_exec[$idx] = 0;
        $this->proc_list[$idx] = &$proc;

        unset($idx, $proc);
        return true;
    }

    /**
     * Daemon process unit
     */
    public function procUnit(): void
    {
        //Init modules & libraries
        $this->error   = Error::new();
        $this->router  = Router::new();
        $this->execute = Execute::new();

        while (true) {
            //Pipe broken
            if (false === ($stdin = fgets(STDIN))) {
                return;
            }

            //Parse data
            if ('' === $stdin || !is_array($data = json_decode($stdin, true))) {
                continue;
            }

            //Fetch job data
            $this->execJob($data);

            //Free memory
            unset($stdin, $data);
        }
    }

    /**
     * Close a process
     *
     * @param int $idx
     */
    public function closeProc(int $idx): void
    {
        if (is_resource($this->proc_list[$idx])) {
            pclose($this->proc_list[$idx]);
        }

        unset($this->proc_list[$idx], $idx);
    }

    /**
     * Close All processes
     */
    public function closeAll(): void
    {
        foreach ($this->proc_list as $idx => $proc) {
            $this->closeProc($idx);
        }

        unset($idx, $proc);
    }

    /**
     * Close all in the end
     */
    public function __destruct()
    {
        $this->closeAll();
    }

    /**
     * Execute a job
     *
     * @param array $data
     *
     * @return void
     */
    private function execJob(array $data): void
    {
        try {
            //Parse CMD
            $this->router->parse($data['c']);

            //Call CGI
            if (!empty($this->router->cgi_cmd)) {
                //Remap input data
                $this->io_unit->src_input = $data;

                //Process CGI command
                while (is_array($cmd_pair = array_shift($this->router->cgi_cmd))) {
                    //Extract CMD contents
                    [$cmd_class, $cmd_method] = $cmd_pair;
                    //Run script method
                    $this->execute->runScript($cmd_class, $cmd_method, $cmd_pair[2] ?? implode('/', $cmd_pair));
                }
            }

            //Call CLI
            if (!empty($this->router->cli_cmd)) {
                //Remap argv data
                $this->io_unit->src_argv = $data['argv'] ?? '';

                //Process CLI command
                while (is_array($cmd_pair = array_shift($this->router->cli_cmd))) {
                    //Extract CMD contents
                    [$cmd_name, $exe_path] = $cmd_pair;

                    if ('' !== ($exe_path = trim($exe_path))) {
                        //Run external program
                        $this->execute->runProgram($this->os_unit, $cmd_name, $exe_path);
                    }
                }
            }
        } catch (\Throwable $throwable) {
            $this->error->exceptionHandler($throwable, false, false);
            unset($throwable);
        }

        unset($data, $cmd_pair, $cmd_class, $cmd_method, $cmd_name, $exe_path);
    }
}