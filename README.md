# punchmachine

Benchmarker framework written in PHP, prefork worker.

## Setup

punchmachine uses composer for installing dependencies.

```
composer install
```

And requires PHP `pcntl` and `posix` functions.
You have to compile the CLI version of PHP with `--enable-pcntl` configuration option when compiling PHP to enable Process Control support.
(`posix` is enabled by default. Do not disable POSIX-like functions with `--disable-posix`)

## Benchmarker

Autoloader loads benchmarker classes from `./benchmarker/ClassName.php`.

Benchmarker class must extend `Punchmachine\Benchmarker\Benchmarker` class.

### Example

```
class FooBenchmarker extends Benchmarker {
}
```

## Configurations

punchmachine requires ini-like configration file.
The ini file have to contains `global` section, and optional by benchmarker.

### configration example

```
[global]
benchmarker = "DummyBenchmarker"

servers[] = '127.0.0.1'

benchmark_timeout = 10
failure_limit = 50
initializer = ""

[benchmarker.processes]
sleep_call = 1
```

* global.benchmarker
  * Benchmarker name. `punchmachine`'s main process loads this class and call `run()` method.
* global.servers
  * List of target servers. It depends on benchmarker.
* global.benchmark\_timeout
  * Main process timeout
* global.failure\_limit
  * Failure limit. When failure counts reaches, abort benchmark processes.
* global.initializer
  * Call initializer method before benchmarker processes.
  * If empty, skip initializer process.
* benchmarker.processes
  * worker process number
  * Like "`Benchmarker->method`\ = NUM"

## Usage

```
./bin/punchmachine.php configration.ini
```


## Contributing

1. Fork it ( https://github.com/t-cyrill/punchmachine/fork )
2. Create your feature branch (`git checkout -b my-new-feature`)
3. Commit your changes (`git commit -am 'Add some feature'`)
4. Push to the branch (`git push origin my-new-feature`)
5. Create a new Pull Request
