<?php

set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
require_once __DIR__ . '/../../includes/fast_general.php';

$checks = 0;
$failures = 0;
function checkText($condition, $name)
{
    global $checks, $failures;
    $checks++;
    echo ($condition ? 'PASS' : 'FAIL') . ': ' . $name . PHP_EOL;
    if (!$condition) {
        $failures++;
    }
}

$_Game = 'TMF';
$value = 'one\r\ntwo\nthree\rfour';
convertSpecialChars($value);
checkText($value === "one\ntwo\nthree\nfour", 'Scalar escaped newlines converted');
$value = array('first' => 'one\ntwo', 'nested' => array('second' => 'three\rfour'));
convertSpecialChars($value);
checkText($value === array('first' => "one\ntwo", 'nested' => array('second' => "three\nfour")), 'Nested escaped newlines converted');
$value = '$l[http://example.test]label$l';
stripLinksFromArray($value);
checkText($value === '$l[http://example.test]label$l', 'Supported game preserves scalar links');
$_Game = 'unsupported';
$value = '$l[http://example.test]label$l';
stripLinksFromArray($value);
checkText($value === 'label', 'Unsupported game strips scalar links');
$value = array('link' => '$l[http://example.test]label$l', 'nested' => array('text' => 'plain'));
stripLinksFromArray($value);
checkText($value === array('link' => 'label', 'nested' => array('text' => 'plain')), 'Nested link stripping');
$value = '$l[http://example.test]label$l\nnext';
convertSpecialChars($value);
checkText($value === "label\nnext", 'Scalar newline and link conversion combined');
$value = '$$literal';
stripLinksFromArray($value);
checkText($value === '$$literal', 'Escaped dollars preserved');
$value = '';
convertSpecialChars($value);
stripLinksFromArray($value);
checkText($value === '', 'Empty scalar remains empty');

restore_error_handler();
echo 'Text checks: ' . $checks . ', failures: ' . $failures . PHP_EOL;
exit($failures === 0 ? 0 : 1);
