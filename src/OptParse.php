<?php
/**
 * Created by PhpStorm.
 * User: iwai
 * Date: 2017/08/10
 * Time: 14:44
 */

namespace Escan;


class OptParse
{

    // TODO: optparse と互換性を持たせる
    // @see https://github.com/CHH/optparse

    /**
     * @param \Exception|string $e
     */
    public function echoUsageExit($e = null)
    {
        if ($e instanceof \Exception) {
            fwrite(STDERR, "{$e->getMessage()}\n");
        }
        if (is_string($e)) {
            fwrite(STDERR, sprintf("Error: %s\n", $e));
        }
        $usage = <<<__USAGE__
Usage: php %s [--help|-h] [--wait <wait>] [--size <size>] [options for curl...] url

Examples

php %s -d '{"query": {"match_all": {}}}' 127.0.0.1:9200/index/type/_search

__USAGE__;

        global $argv;
        fwrite(STDERR, sprintf($usage, basename($argv[0]), basename($argv[0])));
        exit(1);
    }

    /**
     * @param  string|array $name
     * @return bool
     */
    public function hasName($name)
    {
        global $argv;

        $names = is_array($name) ? $name : [ $name ];

        foreach ($argv as $option) {
            if (in_array($option, $names)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param  string|array $name
     * @param  mixed $default
     * @return mixed
     */
    public function getValue($name, $default = null)
    {
        global $argv;

        $names = is_array($name) ? $name : [ $name ];

        $find = false;
        foreach ($argv as $option) {
            if ($find == true) {
                return $option;
            }
            if (in_array($option, $names)) {
                $find = true;
            }
        }
        return $default;
    }

    public function getUrl()
    {
        global $argv;

        $last_func = function ($a) { return end($a); };
        return $last_func($argv);
    }

    public function getCurlCommand($appendQuery=[], $excludeFlags=[], $excludeOptions=[], $scrollId = null)
    {
        global $argv;
        $curl_options = [];

        $elastic_url = $this->buildElasticUrl($appendQuery, $scrollId);

        if ($scrollId) {
            $excludeOptions[] = '-d';
            $excludeOptions[] = '--data';
            $excludeOptions[] = '--data-ascii';
        }

        $argv_count = count($argv);
        $find = false;
        for ($i = 1; $i < $argv_count; $i++) {
            // プログラム名と末尾のURLを無視
            if ($i == 0 || $i == ($argv_count - 1))
                continue;

            $option = $argv[$i];

            // 値付きの引数を除外
            if ($find == true) {
                $find = false;
                continue;
            }
            if (in_array($option, $excludeOptions)) {
                $find = true;
                continue;
            }

            // フラグを除外
            if (in_array($option, $excludeFlags)) {
                continue;
            }

            // ...orz
            if (preg_match('/^-/', $option) == false) {
                $curl_options[] = escapeshellarg($option);
            } else {
                $curl_options[] = $option;
            }
        }

        if ($scrollId) {
            $curl_options[] = '-d';
            $curl_options[] = escapeshellarg($scrollId);
        }
        return sprintf('curl -sS %s %s', escapeshellarg($elastic_url), implode(' ', $curl_options));
    }

    private function buildElasticUrl($appendQuery = [], $scrollId = null)
    {
        $url = $this->getUrl();

        $parsed_url = parse_url($url);

        if ($parsed_url == false) {
            throw new \Exception(sprintf('Failed parse url: %s', $url));
        }

        if (isset($parsed_url['query'])) {
            $parsed_url['query'] = $parsed_url['query'] . '&' . http_build_query($appendQuery);
        } else {
            $parsed_url['query'] = http_build_query($appendQuery);
        }

        if ($scrollId) {
            if (isset($parsed_url['host'])) {
                $parsed_url['path'] = '/_search/scroll';
            } else {
                list($host, $_) = explode('/', $parsed_url['path'], 2);
                $parsed_url['host'] = $host;
                $parsed_url['path'] = '/_search/scroll';
            }
        }

        return $this->buildUrl($parsed_url);
    }

    private function buildUrl($parts)
    {
        $url = '';
        if (isset($parts['scheme'])) {
            $url = $parts['scheme'] . '://';
        }
        if (isset($parts['host'])) {
            $url .= $parts['host'];
        }
        if (isset($parts['port'])) {
            $url .= ':'.$parts['port'];
        }
        if (isset($parts['path'])) {
            $url .= $parts['path'];
        }
        if (isset($parts['query'])) {
            $url .= '?'. $parts['query'];
        }

        return $url;
    }
}