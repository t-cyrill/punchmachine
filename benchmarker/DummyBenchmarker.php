<?php
namespace Punchmachine\Benchmarker;

class DummyBenchmarker extends Benchmarker {
    const DELTA = 100;

    protected function initialize() {
        $this->log_info('Initialize');
        $this->log_info('Configrations:' . json_encode($this->config, JSON_PRETTY_PRINT));
    }

    private function getBenchmarkTimeout() {
        return $this->config['global']['benchmark_timeout'];
    }

    private function processGeneralLoop(callable $callable, $identifier) {
        $timeout = microtime(true) + $this->getBenchmarkTimeout();
        $limit_per_sec = isset($this->config['benchmarker.processes']["{$identifier}_limit_per_sec"])
                            ? $this->config['benchmarker.processes']["{$identifier}_limit_per_sec"] : 2^32;

        for ($cnt = 0;; $cnt++) {
            $tick_result = self::tick_process(function () use ($timeout, $callable, $identifier, $limit_per_sec) {
                for ($i = 0; $i < $limit_per_sec; $i++) {
                    $is_continue = $callable() ? $this->notify_success($identifier)
                                               : $this->notify_failure($identifier);
                    if (!$is_continue || (($i % self::DELTA) == 0 && (microtime(true) > $timeout))) break;
                }
            }, 1, $timeout);
            if ($tick_result == 2 || (microtime(true) > $timeout)) break;
        }
    }

    protected function sleep_call() {
        $this->log_info('Call ' . __METHOD__);

        $start = microtime(true);

        $this->processGeneralLoop(function () {
            sleep(1000000000);
            return true;
        }, 'sleep_call');

        $duration = microtime(true) - $start;
        $this->notify_score(__METHOD__, (string) ($this->get_loop_count() / $duration));

        $this->log_info('Finished ' . __METHOD__);
    }
}
