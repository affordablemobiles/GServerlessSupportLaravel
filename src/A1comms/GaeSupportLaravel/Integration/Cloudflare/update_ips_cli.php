<?php

declare(strict_types=1);

define('CF_V4_IPS_URL', 'https://www.cloudflare.com/ips-v4');
define('CF_V6_IPS_URL', 'https://www.cloudflare.com/ips-v6');

$ipv4_list = file_get_contents(CF_V4_IPS_URL);
$ipv4_arr = explode("\n", $ipv4_list);

$ipv6_list = file_get_contents(CF_V6_IPS_URL);
$ipv6_arr = explode("\n", $ipv6_list);

writeFile(__DIR__.'/cf-ipv4.php', remove_empty($ipv4_arr));
writeFile(__DIR__.'/cf-ipv6.php', remove_empty($ipv6_arr));

function remove_empty($array)
{
    $array = array_map('trim', $array);

    return array_filter($array, '_remove_empty_internal');
}

function _remove_empty_internal($value)
{
    return !empty($value) || 0 === $value;
}

function writeFile($file, $array): void
{
    file_put_contents($file, '<?php return '.var_export($array, true).';'.PHP_EOL);
}
