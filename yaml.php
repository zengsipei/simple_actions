<?php

use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\Yaml\Yaml;

require_once 'vendor/autoload.php';

date_default_timezone_set('PRC');

function request($method, $url, array $options = []): bool|string
{
    try {
        d($method, $url);

        $client = new Client();
        $resp = $client->request($method, $url, $options);

        if (200 != $resp->getStatusCode()) {
            d($resp->getReasonPhrase());
            return false;
        }

        $contents = $resp->getBody()->getContents();

        if (empty($contents)) {
            d('no contents');
            return false;
        }
        return $contents;
    } catch (GuzzleException $e) {
        d('GuzzleException', $e->getMessage());
    }
    return false;
}

try {
    $start = microtime(true);
    dd('v2rayshare.com 提供了免费的两种订阅，MrdoorVpn 挂了');

    $base = Yaml::parseFile('base.yml');
    $proxies = $base['proxies'] ?: [];

    do {
        // v2rayshare.com
        $now = Carbon::now();
        $file_name = $now->format('Ymd') . '.yaml';
        $url = [
            'https://v2rayshare.githubrowcontent.com',
            $now->year,
            $now->format('m'),
            $file_name
        ];
        $url = implode('/', $url);

        $contents = request('get', $url, [
            'verify' => false,
        ]);

        if (false === $contents) {
            $yaml = Yaml::parseFile('v2rayshare.yml');

            if (empty($yaml)) break;
        } else {
            $yaml = Yaml::parse($contents);
        }
        if (empty($yaml)) {
            d('parse contents fail');
            break;
        }

        file_put_contents('v2rayshare.yml', Yaml::dump($yaml));

        foreach ($yaml['proxies'] as $k => $v) {
            if ($v['plugin']) unset($yaml['proxies'][$k]); // 不支持插件
        }

        $proxies = array_merge($proxies, $yaml['proxies']);
    } while (0);
    do {
        // MrdoorVpn
        $json = json_decode(file_get_contents('mrdoor.json'), true);
        $is_expire = true;

        if (is_int($json['expire'])) {
            $is_expire = Carbon::createFromTimestampMs($json['expire'])->lessThan($now);
        }
        if ($is_expire) {
            // autosign
            $options = [
                'form_params' => [
                    'device_id' => 'C5' . mt_rand(11111, 99999) . '-3ED4-449C-806F-16167220F497'
                ]
            ];
            $autosign = request('post', 'http://192.53.161.228/api/v2/autosignup', $options);

            if (false === $autosign) break;

            $autosign = json_decode($autosign, true);
            $json = $autosign['data'];

            // account
            $options = [
                'headers' => [
                    'uuid' => $json['uuid']
                ]
            ];
            $account = request('get', 'http://192.53.161.228/api/v2/account', $options);

            if (false === $account) break;

            $account = json_decode($account, true);
            $json = array_merge($json, $account['data']);
            file_put_contents('mrdoor.json', json_encode($json));
        }
        if (empty($json['uuid'])) break;

        // servers
        $options = [
            'headers' => [
                'uuid' => $json['uuid']
            ]
        ];
        $servers = request('get', 'http://192.53.161.228/api/v3/servers', $options);

        if (false === $servers) break;

        $servers = json_decode($servers, true);
        $servers['proxies'] = [];
        $type_demo = json_decode(file_get_contents('type.json'), true) ?: [];

        foreach ($servers['data'] as $v) {
            if ('Shadowsocks' == $v['type']) {
                $servers['proxies'][] = [
                    'name' => $v['name'],
                    'server' => $v['host'],
                    'port' => $v['port'],
                    'type' => 'ss',
                    'cipher' => $v['method'],
                    'password' => $v['password']
                ];
                continue;
            }

            $type_demo[$v['type']] = $v;
        }
        if (!empty($type_demo)) file_put_contents('type.json', json_encode($type_demo));

        $proxies = array_merge($proxies, $servers['proxies']);
    } while (0);

    $base['proxies'] = $proxies;
    $proxies_name_list = array_column($proxies, 'name');

    foreach ($base['proxy-groups'] as &$v) {
        if (!in_array($v['name'], ['URL-TEST', 'LOAD-BALANCE', 'SELECT'])) continue;

        $v['proxies'] = $proxies_name_list;
    }
    unset($v);

    $res = file_put_contents('3Q.yml', Yaml::dump($base));
    d('$res', $res);
} catch (Exception $e) {
    d('$e', $e->getMessage());
} finally {
    dd('end', microtime(true) - $start);
}