<?php

$failures = 0;

function checkRuntime($condition, $name)
{
    global $failures;
    echo ($condition ? 'PASS' : 'FAIL') . ': ' . $name . PHP_EOL;
    if (!$condition) {
        $failures++;
    }
}

checkRuntime(PHP_MAJOR_VERSION === 8 && PHP_MINOR_VERSION === 5, 'PHP 8.5: ' . PHP_VERSION);
checkRuntime(PHP_SAPI === 'cli', 'CLI runtime');
checkRuntime(error_reporting() === E_ALL, 'E_ALL diagnostics enabled');

foreach (array('xml', 'zlib', 'mbstring', 'mysqli', 'openssl', 'zip') as $extension) {
    checkRuntime(extension_loaded($extension), 'Extension: ' . $extension);
}

if (extension_loaded('xml')) {
    $parser = xml_parser_create('UTF-8');
    $values = array();
    $tags = array();
    checkRuntime(xml_parse_into_struct($parser, '<root><value>test</value></root>', $values, $tags) === 1, 'XML parsing');
    unset($parser);
}

if (extension_loaded('zlib')) {
    checkRuntime(gzinflate(gzdeflate('baseline')) === 'baseline', 'Compression round trip');
}

if (extension_loaded('mbstring')) {
    checkRuntime(mb_substr('caf' . "\xc3\xa9", 3, 1, 'UTF-8') === "\xc3\xa9", 'UTF-8 substring');
}

$state = array('login' => 'test-player', 'time' => 12345, 'spectator' => false);
checkRuntime(unserialize(serialize($state), array('allowed_classes' => false)) === $state, 'Array state serialization');

echo 'Runtime smoke-test failures: ' . $failures . PHP_EOL;
exit($failures === 0 ? 0 : 1);
