<?php

$en = include __DIR__.'/../lang/en/validation.php';
$json = file_get_contents('https://raw.githubusercontent.com/Laravel-Lang/lang/main/locales/pt_BR/php.json');
$pt = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

function translateRule(string $key, mixed $value, array $pt): mixed
{
    if (is_array($value)) {
        $out = [];
        foreach ($value as $k => $v) {
            $out[$k] = translateRule($key.'.'.$k, $v, $pt);
        }

        return $out;
    }

    return $pt[$key] ?? $value;
}

$result = [];
foreach ($en as $k => $v) {
    if (in_array($k, ['custom', 'attributes'], true)) {
        continue;
    }
    $result[$k] = translateRule($k, $v, $pt);
}

$result['custom'] = [];
$result['attributes'] = include __DIR__.'/../lang/pt_BR/attributes.php';

$dir = __DIR__.'/../lang/pt_BR';
if (! is_dir($dir)) {
    mkdir($dir, 0777, true);
}

$content = "<?php\n\nreturn ".var_export($result, true).";\n";
file_put_contents($dir.'/validation.php', $content);

echo "Written lang/pt_BR/validation.php\n";
