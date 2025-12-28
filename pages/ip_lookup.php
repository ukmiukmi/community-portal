<?php
function getIpInfo($ip)
{
  $cacheFile = __DIR__ . '/cache/ip_geo_cache.json';
  $cache = file_exists($cacheFile) ? json_decode(file_get_contents($cacheFile), true) : [];

  if (isset($cache[$ip])) return $cache[$ip];

  $res = @json_decode(file_get_contents("http://ip-api.com/json/$ip"), true);
  if (!$res || $res['status'] !== 'success') return null;

  $data = [
    'country' => $res['country'],
    'code' => strtolower($res['countryCode']),
    'city' => $res['city'],
    'region' => $res['regionName'],
    'isp' => $res['isp'],
    'proxy' => $res['proxy'] ?? false
  ];

  $cache[$ip] = $data;
  file_put_contents($cacheFile, json_encode($cache));

  return $data;
}
