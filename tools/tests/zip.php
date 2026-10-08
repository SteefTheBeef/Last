<?php

set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});
require_once __DIR__ . '/../../includes/fast_general.php';

$checks = 0;
$failures = 0;
function checkZip($condition, $name)
{
    global $checks, $failures;
    $checks++;
    echo ($condition ? 'PASS' : 'FAIL') . ': ' . $name . PHP_EOL;
    if (!$condition) {
        $failures++;
    }
}
function makeZipFixture($filename, $entries)
{
    $zip = new ZipArchive();
    if ($zip->open($filename, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Cannot create ZIP fixture');
    }
    foreach ($entries as $name => $value) {
        if ($value === null) {
            $zip->addEmptyDir($name);
        } else {
            $zip->addFromString($name, $value);
        }
    }
    $zip->close();
}
function removeZipFixture($directory)
{
    foreach (new FilesystemIterator($directory) as $entry) {
        if ($entry->isDir() && !$entry->isLink()) {
            removeZipFixture($entry->getPathname());
        } else {
            unlink($entry->getPathname());
        }
    }
    rmdir($directory);
}

$originalDirectory = getcwd();
$directory = sys_get_temp_dir() . '/fast-zip-' . bin2hex(random_bytes(8));
mkdir($directory);
chdir($directory);
try {
    makeZipFixture('valid.zip', array('nested/file.txt' => 'hello', 'empty.txt' => '', 'binary.bin' => "\x00\xff", 'empty-dir/' => null));
    checkZip(unpackZip('valid.zip') === true, 'Valid ZIP extraction');
    checkZip(file_get_contents('nested/file.txt') === 'hello', 'Nested file content');
    checkZip(file_get_contents('empty.txt') === '', 'Empty file extraction');
    checkZip(file_get_contents('binary.bin') === "\x00\xff", 'Binary content preservation');
    checkZip(is_dir('empty-dir'), 'Empty directory extraction');
    file_put_contents('nested/file.txt', 'old');
    checkZip(unpackZip('valid.zip') === true && file_get_contents('nested/file.txt') === 'hello', 'Existing file overwrite');
    checkZip(unpackZip('missing.zip') === false, 'Missing archive rejection');
    file_put_contents('invalid.zip', 'not a zip');
    checkZip(unpackZip('invalid.zip') === false, 'Invalid archive rejection');
    foreach (array('../escape.txt', '/absolute.txt', 'C:/absolute.txt', 'nested/../../escape.txt', '..\\escape.txt', 'nested/./file.txt', 'nested//file.txt') as $index => $name) {
        makeZipFixture('unsafe.zip', array('should-not-extract.txt' => 'test', $name => 'unsafe'));
        checkZip(unpackZip('unsafe.zip') === false && !file_exists('should-not-extract.txt'), 'Unsafe entry rejected before writes ' . $index);
    }
    makeZipFixture('normalized.zip', array('windows\\file.txt' => 'normalized'));
    checkZip(unpackZip('normalized.zip') === true && file_get_contents('windows/file.txt') === 'normalized', 'Windows separator normalization');
} finally {
    chdir($originalDirectory);
    removeZipFixture($directory);
}

$_allow_legacy_autoupdate = false;
$_logfile = fopen('php://temp', 'w+');
ob_start();
try {
    require_once __DIR__ . '/../../plugins/plugin.99.autoupdate.php';
    fastAutoupdate();
    $output = ob_get_contents();
} finally {
    ob_end_clean();
    fclose($_logfile);
}
checkZip(str_contains($output, 'Legacy autoupdate disabled'), 'Legacy updater disabled by default');

restore_error_handler();
echo 'ZIP checks: ' . $checks . ', failures: ' . $failures . PHP_EOL;
exit($failures === 0 ? 0 : 1);
