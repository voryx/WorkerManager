<?php

namespace Voryx;

use Rx\DisposableInterface;
use Rx\Observable;
use Rx\ObserverInterface;
use Rx\React\FsWatch;
use Rx\React\ProcessSubject;
use Rx\React\WatchEvent;
use Rx\Subject\Subject;

class WorkerManager extends Observable
{
    private $path;
    private $errors;
    private $fswatch;
    private $processes;

    public function __construct($path, ObserverInterface $errors = null, ObserverInterface $fswatch = null, Observable $workers = null)
    {
        $this->path    = $path;
        $this->errors  = $errors ?: new Subject();
        $this->fswatch = $fswatch ?: (new FsWatch($path, '-e ".*" -i "\\\.php$"'))->share();

        $workers = $workers ?: (new ProcessSubject("ls {$path} | grep .php", $this->errors))
            ->flatMap(function ($res) {
                return Observable::fromArray(explode("\n", $res));
            })
            ->map(function ($file) use ($path) {
                return "{$path}/{$file}";
            });

        $newWorkers = $this->fswatch
            ->filter([$this, 'fileChangeFilter'])
            ->map(function (WatchEvent $e) {
                return $e->getFile();
            });

        $this->processes = $workers
            ->merge($newWorkers)
            ->map(function ($file) {
                $php = PHP_BINARY;
                return [new ProcessSubject("exec {$php} {$file}", $this->errors), $file];
            })
            ->share();
    }

    public function _subscribe(ObserverInterface $observer): DisposableInterface
    {
        return $this->processes
            ->flatMap(function ($args) {
                /* @var $process ProcessSubject */
                list($process, $file) = $args;

                $fileUpdated = $this->fswatch
                    ->filter(function (WatchEvent $e) use ($file) {
                        return 0 === strpos($e->getFile(), $file);
                    })
                    ->filter([$this, 'fileChangeFilter']);

                return $process
                    ->takeUntil($fileUpdated)
                    ->map(function ($output) use ($process, $file) {
                        return [$output, $process->getProcess(), $file];
                    });
            })
            ->subscribe($observer);
    }

    public function fileChangeFilter(WatchEvent $e)
    {
        return $e->getBitwise() & (WatchEvent::UPDATED | WatchEvent::CREATED | WatchEvent::RENAMED);
    }

    public function getProcesses(): Observable\RefCountObservable
    {
        return $this->processes;
    }
}
