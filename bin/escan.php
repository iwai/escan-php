#!/usr/bin/env php
<?php
/**
 * Created by PhpStorm.
 * User: iwai
 * Date: 2017/08/03
 * Time: 21:54
 */

ini_set('date.timezone', 'Asia/Tokyo');

if (PHP_SAPI !== 'cli') {
    echo sprintf('Warning: %s should be invoked via the CLI version of PHP, not the %s SAPI'.PHP_EOL, $argv[0], PHP_SAPI);
    exit(1);
}

require_once __DIR__.'/../vendor/autoload.php';


$parser = new \Escan\OptParse();

$wait         = $parser->getValue('--wait', 1);
$size         = $parser->getValue('--size', 10000);
$data         = $parser->getValue(['-d', '--data', '--data-ascii'], '{"query": {"match_all": {}}}');
$disable_scan = $parser->hasName(['--disable-scan']);
$debug        = $parser->hasName(['--debug']);

if ($parser->hasName(['-h', '--help'])) {
    $parser->echoUsageExit();
} elseif (!$data) {
    $parser->echoUsageExit('Undefined [-d|--data|--data-ascii <data>] option for curl.');
}

$query = [
    'size'   => $size,
    'scroll' => '10m'
];
if (!$disable_scan) {
    $query['search_type'] = 'scan';
}

$scroll_id = null;
$output    = null;
$pager_end = false;
$page      = 0;

do {
    unset($output);
    $output = [];

    $command = $parser->getCurlCommand(
        $query,
        ['--disable-scan', '--debug'],
        ['--wait', '--size'],
        $scroll_id
    );

    if ($debug) {
        fwrite(STDERR, $command . PHP_EOL);
    }

    if (exec($command, $output, $command_return) !== false && $command_return === 0) {
        $scroll_id = null;
        if (preg_match('/"_scroll_id":"([=a-zA-Z0-9]+)"/', $output[0], $matches) === 1) {
            $scroll_id = $matches[1];
            $page = $page + 1;
        } else {
            fwrite(STDERR, implode('', $output) . PHP_EOL);
            $pager_end = true;
        }
        if ($page > 1 && preg_match('/"hits":\[\]/', $output[0], $matches) === 1) {
            $pager_end = true;
        }
        if ($debug) {
            fwrite(STDERR, sprintf('NextScrollId: %s', $scroll_id) . PHP_EOL);
        }
        echo implode('', $output), PHP_EOL;
    }

    if ($debug) {
        fwrite(STDERR, sprintf('CommandReturn: %s', $command_return) . PHP_EOL);
        fwrite(STDERR, sprintf('MemoryUsage: %d kb', memory_get_usage() / 1024) . PHP_EOL . PHP_EOL);
    }

    $query = [ 'scroll' => '10m' ];

    sleep($wait);

} while ($command_return === 0 && $pager_end !== true);
