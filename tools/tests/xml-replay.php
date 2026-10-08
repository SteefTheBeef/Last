<?php

set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
require_once __DIR__ . '/../../includes/xml_parser.php';
require_once __DIR__ . '/../../includes/replayparser.inc.php';

$checks = 0;
$failures = 0;
function checkXmlReplay($condition, $name)
{
    global $checks, $failures;
    $checks++;
    echo ($condition ? 'PASS' : 'FAIL') . ': ' . $name . PHP_EOL;
    if (!$condition) {
        $failures++;
    }
}

$xml = '<root><name>test</name><enabled>1</enabled></root>';
checkXmlReplay(xml_parse_string($xml) === array('root' => array('name' => 'test', 'enabled' => '1')), 'Basic XML parsing');
$repeated = xml_parse_string('<root><item id="a">one</item><item id="b">two</item></root>');
checkXmlReplay($repeated['root']['item'] === array('.multi_same_tag.' => true, 'one', 'two')
    && $repeated['root']['.attr.item'] === array(array('id' => 'a'), array('id' => 'b')), 'Repeated tags and attributes');
checkXmlReplay(xml_parse_string('<root><item id="a">one</item></root>', true)
    === array('root' => array('item' => array('id' => 'a', 'value' => 'one'))), 'Mixed attribute mode');
checkXmlReplay(xml_parse_string(xml_build($repeated)) === $repeated, 'Repeated XML build and parse round trip');
foreach (array('', '<root>', '<root><item>test</root>', '<root/>junk') as $index => $invalid) {
    checkXmlReplay(xml_parse_string($invalid) === array(), 'Reject malformed XML ' . $index);
}
checkXmlReplay(xml_parse_string($xml)['root']['name'] === 'test', 'Valid parsing after failure');
checkXmlReplay(xml_parse_string('<root><name>caf' . "\xc3\xa9" . '</name></root>')['root']['name'] === 'caf' . "\xc3\xa9", 'XML UTF-8 preservation');
$file = tempnam(sys_get_temp_dir(), 'fast-xml-');
try {
    checkXmlReplay(xml_build_to_file($repeated, $file) > 0 && xml_parse_file($file) === $repeated, 'XML file round trip');
} finally {
    unlink($file);
}

function replayString($value)
{
    return pack('V', strlen($value)) . $value;
}
function replayFixture($xml, $version = 2, $stringType = 6)
{
    $data = "GBX\x06\x00BUCR" . pack('N', 0x00300903) . pack('VV', 0, $version);
    for ($index = 0; $index < $version; $index++) {
        $data .= pack('NV', 0, 0);
    }
    $data .= pack('V', $stringType) . pack('VV', 3, 0x80000000)
        . replayString('fixture-uid') . pack('V', 0x40000000) . replayString('Stadium')
        . pack('V', 0x40000000) . replayString('test-author') . pack('V', 12345) . replayString('test-nickname');
    if ($stringType >= 6) {
        $data .= replayString('test-login');
    }
    if ($version >= 2) {
        $data .= replayString($xml);
    }
    return $data;
}

$metadata = '<header version="2" exever="0.1.9.0"><ident name="test"/>'
    . '<times respawns="1" stuntscore="0" validable="1"/>'
    . '<checkpoints cur="3" onelap="3"/><deps><dep name="fixture"/></deps></header>';
$fixture = replayFixture($metadata);
$replay = new ReplayParser($fixture);
checkXmlReplay($replay->uid === 'fixture-uid' && $replay->envir === 'Stadium'
    && $replay->author === 'test-author' && $replay->login === 'test-login' && $replay->replay === 12345, 'Replay binary metadata');
checkXmlReplay($replay->xmlver === '2' && $replay->exever === '0.1.9.0'
    && $replay->respawns === '1' && $replay->validable === '1' && $replay->cpscur === '3', 'Replay XML metadata');
checkXmlReplay($replay->parsedxml['DEPS'] === array(array('NAME' => 'fixture')), 'Replay dependency callbacks');
$oldReplay = new ReplayParser(replayFixture('', 1, 4));
checkXmlReplay($oldReplay->uid === 'fixture-uid' && $oldReplay->version === 1 && $oldReplay->login === null, 'Version 1 replay without XML or login');
$minimal = new ReplayParser(replayFixture('<header/>'));
checkXmlReplay($minimal->uid === 'fixture-uid' && $minimal->xmlver === null && $minimal->respawns === null, 'Optional XML metadata');
$utf8 = new ReplayParser(replayFixture('<header><ident name="caf' . "\xc3\xa9" . ' &amp; tea & coffee"/></header>'));
checkXmlReplay($utf8->parsedxml['IDENT']['NAME'] === 'caf' . "\xc3\xa9" . ' & tea & coffee', 'UTF-8 and ampersand handling');
$latin1 = new ReplayParser(replayFixture('<header><ident name="caf' . "\xe9" . '"/></header>'));
checkXmlReplay($latin1->parsedxml['IDENT']['NAME'] === 'caf' . "\xc3\xa9", 'Latin-1 replay XML fallback');
$badXml = new ReplayParser(replayFixture('<header><times></header>'));
checkXmlReplay($badXml->uid === null && $badXml->parsedxml === array(), 'Malformed replay XML does not terminate execution');
$truncatedRejected = true;
for ($length = 0; $length < strlen($fixture); $length++) {
    $truncated = new ReplayParser(substr($fixture, 0, $length));
    if ($truncated->uid !== null) {
        $truncatedRejected = false;
        break;
    }
}
checkXmlReplay($truncatedRejected, 'Every truncated fixture prefix is rejected safely');
foreach (array(null, 123, 'not a replay', "GBX\x06\x00") as $index => $invalid) {
    checkXmlReplay((new ReplayParser($invalid))->uid === null, 'Invalid replay input ' . $index);
}
$oversize = substr($fixture, 0, 49) . pack('V', 0x10000);
checkXmlReplay((new ReplayParser($oversize))->uid === null, 'Oversized replay string rejection');

restore_error_handler();
echo 'XML/replay checks: ' . $checks . ', failures: ' . $failures . PHP_EOL;
exit($failures === 0 ? 0 : 1);
