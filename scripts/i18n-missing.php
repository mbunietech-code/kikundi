<?php

/*
 * Lists translation keys used in the code that have no English entry in
 * lang/en.json. Source text is Swahili; English lives in en.json.
 *
 *   php scripts/i18n-missing.php          missing keys, one per line
 *   php scripts/i18n-missing.php --unused keys in en.json no longer used
 */

$root = dirname(__DIR__);
$dirs = ['app', 'resources/views', 'routes'];
$keys = [];

foreach ($dirs as $dir) {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("{$root}/{$dir}", FilesystemIterator::SKIP_DOTS));

    foreach ($files as $file) {
        if (! str_ends_with($file->getFilename(), '.php')) {
            continue;
        }

        $code = file_get_contents($file->getPathname());
        // __('...') and __("...") with a literal first argument.
        preg_match_all("/__\\(\\s*'((?:[^'\\\\]|\\\\.)*)'/s", $code, $single);
        preg_match_all('/__\\(\\s*"((?:[^"\\\\]|\\\\.)*)"/s', $code, $double);

        foreach ($single[1] as $key) {
            $keys[str_replace(["\\'", '\\\\'], ["'", '\\'], $key)] = true;
        }
        foreach ($double[1] as $key) {
            if (! str_contains($key, '$')) {
                $keys[stripcslashes($key)] = true;
            }
        }
    }
}

$en = json_decode(file_get_contents("{$root}/lang/en.json") ?: '{}', true, flags: JSON_THROW_ON_ERROR);

if (in_array('--unused', $argv, true)) {
    foreach (array_diff_key($en, $keys) as $key => $value) {
        echo $key, PHP_EOL;
    }
    exit;
}

$missing = array_diff_key($keys, $en);
foreach (array_keys($missing) as $key) {
    echo json_encode($key, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
}
fwrite(STDERR, count($keys).' keys used, '.count($missing)." missing English\n");
exit($missing ? 1 : 0);
