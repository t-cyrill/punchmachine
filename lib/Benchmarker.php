<?php
class Benchmarker {
    const TIME_SCALE = 1000000; // 1/1 microsec
    const TICK = 1000000; // 1 * TIME_SCALE;
    const SHARED_TMP_MAP = '/tmp/punchmachine_score_map';

    protected $config;
    private $loop_count,
            $success_count,
            $failure_count;
    private $logger;

    public function __construct($config) {
        $this->logger = new \Punchmachine\Logger;
        $this->config = $config;
    }

    public function run() {
        $config = $this->config;
        $fork_num = 0;
        $fork_process_mode = [];

        foreach ($config['process'] as $process) {
            for ($i = 0; $i < $process['count']; $i++) {
                $fork_process_mode[] = $process['name'];
            }
        }

        $fork = count($fork_process_mode);
        $forked = 1;
        $processes = [];
        foreach ($fork_process_mode as $mode) {
            $this->log_info("Prepare child processes. [{$forked}/{$fork}]");
            $pid = pcntl_fork();
            if ($pid == -1) {
                die('Error: fork returns -1 failed.');
            } else if ($pid) {
                // NOTE: Master process
                $processes[] = $pid;
                $forked++;
            } else {
                // NOTE: Forked child process
                $this->log_info("Forking benchmark worker process [{$i}]");
                $this->call_child_process($mode);
                $this->log_info('Child process exit.');
                exit;
            }
        }
        // NOTE: Master process initializer >>> MUST BE CALLED AFTER FORKING <<<
        call_user_func([$this, $config['initializer']]);

        // NOTE: wait prefork process
        usleep(100 * 1000);

        $this->log_info('Start benchmark workers');
        foreach ($processes as $pid) {
            posix_kill($pid, SIGUSR1);
        }

        // NOTE: wait prefork process
        $this->log_info('Waiting children');
        $child_pid = pcntl_wait($status);
        usleep(100 * 1000);

        $scores = $this->figure_scores($processes);

        $this->log_info('All child process finished. Checking child process pids');
        while (count($processes) > 0) {
            $child_pid = pcntl_waitpid(-1, $status, WUNTRACED);
            $key = array_search($child_pid, $processes, true);
            if ($key === false) break;
            unset($processes[$key]);
        }

        foreach ($scores as $k => $v) {
            $this->log_info(">>> total {$k} score = " . sprintf("%0.6f", $v));
        }

        $this->log_info('Process finished.');
    }

    private function call_child_process($method_name) {
        pcntl_sigprocmask(SIG_BLOCK, [SIGUSR1]);
        pcntl_sigwaitinfo([SIGUSR1], $info);

        call_user_func([$this, $method_name]);
    }

    protected function log_info($string) {
        $pid = $this->get_pid();
        $this->logger->info("[{$pid}] $string");
    }

    protected function get_loop_count() {
        return $this->loop_count;
    }

    protected function get_failure_count() {
        return $this->failure_count;
    }

    protected function get_pid() {
        static $pid;
        if (!isset($pid)) $pid = getmypid();
        return $pid;
    }

    protected function notify_failure($identifier = '') {
        $this->log_info(">>> {$identifier} failed.");
        $this->failure_count += 1;
        $this->loop_count += 1;
        if ($this->failure_count >= 10) {
            return false;
        }
        return true;
    }

    protected function notify_success($identifier = '') {
        $this->loop_count += 1;
        $this->success_count += 1;
        return true;
    }

    protected function notify_score($identifier, $score) {
        $pid = $this->get_pid();

        // NOTE: Lock and append map
        $fp = fopen(self::SHARED_TMP_MAP, 'r+');
        if (flock($fp, LOCK_EX)) {
            $map = json_decode(fread($fp, filesize(self::SHARED_TMP_MAP)), true);
            $map = ($map !== null) ? $map : [];
            $map["score_{$pid}_type"] = $identifier;
            $map["score_{$pid}_value"] = (string) $score;

            ftruncate($fp, 0);
            fwrite($fp, json_encode($map));
            fflush($fp);
            flock($fp, LOCK_UN);
        }
        fclose($fp);
    }

    protected function figure_scores($pids) {
        $scores = [];

        // NOTE: Lock and read map
        $fp = fopen(self::SHARED_TMP_MAP, 'r');
        if (flock($fp, LOCK_EX)) {
            $map = json_decode(fread($fp, filesize(self::SHARED_TMP_MAP)), true);
            $map = ($map !== null) ? $map : [];
            flock($fp, LOCK_UN);
        }
        fclose($fp);

        foreach ($pids as $pid) {
            $key = $map["score_{$pid}_type"];
            if ($key !== null) {
                $scores[$key] = isset($scores[$key]) ? $scores[$key] : 0;
                $scores[$key] += $map["score_{$pid}_value"];
            }
        }
        return $scores;
    }

    protected static function tick_process(callable $callable, $tick = 1, $timeout = -1) {
        $start = microtime(true);
        call_user_func($callable);
        $duration = microtime(true) - $start;

        if ($duration > $tick) return 1;
        if ($timeout < 0 || $timeout - $start > $tick) {
            $sleep_time = (int) (self::TICK - ($duration * self::TIME_SCALE));
            usleep($sleep_time);
        } else {
            $sleep_time = ((int) (self::TICK - ($duration * self::TIME_SCALE))) / 10;
            for ($i = 0; $i < 20; $i++) {
                $now = microtime(true);
                if ($now > $timeout) { return 2; }
                usleep($sleep_time);
            }
        }
        return 0;
    }
}

