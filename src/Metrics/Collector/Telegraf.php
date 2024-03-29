<?php

/*
 * This file is part of the beberlei/metrics project.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Beberlei\Metrics\Collector;

use Beberlei\Metrics\Utils\Box;

/**
 * Sends statistics to the StatsD daemon over UDP,
 * ad hoc implementation for the StatsD - Telegraf integration,
 * support tagging.
 */
class Telegraf implements CollectorInterface, GaugeableCollectorInterface
{
    private array $data = [];
    private string $tags;

    public function __construct(
        private readonly string $host = 'localhost',
        private readonly int $port = 8125,
        private readonly string $prefix = '',
        array $tags = [],
    ) {
        $this->tags = http_build_query($tags, '', ',');
        $this->tags = \strlen($this->tags) > 0 ? ',' . $this->tags : $this->tags;
    }

    public function measure(string $variable, int $value, array $tags = []): void
    {
        $this->data[] = sprintf('%s%s:%s|c', $variable, $this->tags, $value);
    }

    public function increment(string $variable, array $tags = []): void
    {
        $this->data[] = $variable . $this->tags . ':1|c';
    }

    public function decrement(string $variable, array $tags = []): void
    {
        $this->data[] = $variable . $this->tags . ':-1|c';
    }

    public function timing(string $variable, int $time, array $tags = []): void
    {
        $this->data[] = sprintf('%s%s:%s|ms', $variable, $this->tags, $time);
    }

    public function gauge(string $variable, string|int $value, array $tags = []): void
    {
        $this->data[] = sprintf('%s%s:%s|g', $variable, $this->tags, $value);
    }

    public function set(string $variable, string $value): void
    {
        $this->data[] = sprintf('%s%s:%s|s', $variable, $this->tags, $value);
    }

    public function flush(): void
    {
        if (!$this->data) {
            return;
        }

        Box::box($this->doFlush(...));
    }

    private function doFlush(): void
    {
        $fp = fsockopen('udp://' . $this->host, $this->port, $errno, $errstr, 1.0);

        if (!$fp) {
            return;
        }

        foreach ($this->data as $line) {
            fwrite($fp, $this->prefix . $line);
        }

        fclose($fp);

        $this->data = [];
    }
}
