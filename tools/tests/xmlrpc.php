<?php

set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

require_once __DIR__ . '/../../includes/GbxRemote.fast.php';
require_once __DIR__ . '/../../includes/GbxRemote.response.php';

$checks = 0;
$failures = 0;

function checkProtocol($condition, $name)
{
    global $checks, $failures;
    $checks++;
    echo ($condition ? 'PASS' : 'FAIL') . ': ' . $name . PHP_EOL;
    if (!$condition) {
        $failures++;
    }
}

$values = array(
    array(true, '<boolean>1</boolean>'),
    array(false, '<boolean>0</boolean>'),
    array(42, '<int>42</int>'),
    array(-7, '<int>-7</int>'),
    array(1.5, '<double>1.5</double>'),
    array('A&B <C> "D" \'E\'', '<string>A&amp;B &lt;C&gt; &quot;D&quot; \'E\'</string>'),
    array('', '<string></string>'),
    array(null, '<string></string>'),
    array(array(), '<array><data></data></array>'),
    array(array(1, false), '<array><data><value><int>1</int></value><value><boolean>0</boolean></value></data></array>'),
    array(array('Login' => 'test'), '<struct><member><name>Login</name><value><string>test</string></value></member></struct>')
);
foreach ($values as $index => $case) {
    checkProtocol((new IXR_Value($case[0]))->getXml() === $case[1], 'Value encoding ' . $index);
}

$binary = "\x00\xffGBX";
checkProtocol((new IXR_Base64($binary))->getXml() === '<base64>' . base64_encode($binary) . '</base64>', 'Binary encoding');
$date = new IXR_Date('20241015T12:34:56');
checkProtocol($date->getIso() === '20241015T12:34:56', 'ISO date constructor');
checkProtocol((new IXR_Value($date))->getXml() === '<dateTime.iso8601>20241015T12:34:56</dateTime.iso8601>', 'Date value encoding');

$request = new IXR_Request('Authenticate', array('test-user', 'test-password'));
$expected = '<?xml version="1.0" encoding="utf-8" ?><methodCall><methodName>Authenticate</methodName><params>' . LF
    . '<param><value><string>test-user</string></value></param>' . LF
    . '<param><value><string>test-password</string></value></param>' . LF
    . '</params></methodCall>';
checkProtocol($request->getXml() === $expected, 'Request wire format');
checkProtocol($request->getLength() === strlen($expected), 'Request byte length');
$parsed = new IXR_Message($request->getXml());
checkProtocol($parsed->parse() && $parsed->messageType === 'methodCall'
    && $parsed->methodName === 'Authenticate' && $parsed->params === array('test-user', 'test-password'), 'Request parsing');

$state = array('Login' => 'test-player', 'Time' => 12345, 'Spectator' => false,
    'Checkpoints' => array(1000, 2000), 'Details' => array('Score' => 1.5));
$response = new IXR_Message(xmlrpc_response($state));
checkProtocol($response->parse() && $response->messageType === 'methodResponse'
    && $response->params === array($state), 'Nested response round trip');
checkProtocol(xml_decode_rpc(xmlrpc_response($state)) === $state, 'Response decode helper');
$empty = new IXR_Message(xmlrpc_response(''));
checkProtocol($empty->parse() && $empty->params === array(''), 'Empty typed string response');
$untyped = new IXR_Message('<methodResponse><params><param><value>plain text</value></param></params></methodResponse>');
checkProtocol($untyped->parse() && $untyped->params === array('plain text'), 'Untyped string response');
$binaryResponse = new IXR_Message(xmlrpc_response(new IXR_Base64($binary)));
checkProtocol($binaryResponse->parse() && $binaryResponse->params === array($binary), 'Binary response round trip');
$dateResponse = new IXR_Message(xmlrpc_response($date));
checkProtocol($dateResponse->parse() && $dateResponse->params[0] instanceof IXR_Date
    && $dateResponse->params[0]->getIso() === $date->getIso(), 'Date response round trip');

$error = new IXR_Error(-1000, 'test fault');
$fault = new IXR_Message($error->getXml());
checkProtocol($fault->parse() && $fault->messageType === 'fault'
    && $fault->faultCode === -1000 && $fault->faultString === 'test fault', 'Fault constructor and parsing');
$helperFault = new IXR_Message(xmlrpc_error(-1001, 'helper fault'));
checkProtocol($helperFault->parse() && $helperFault->faultCode === -1001
    && $helperFault->faultString === 'helper fault', 'Fault helper parsing');
foreach (array('', '<methodResponse>', '<methodResponse><params></methodResponse>') as $index => $xml) {
    $invalid = new IXR_Message($xml);
    checkProtocol($invalid->parse() === false, 'Reject invalid XML ' . $index);
}

$callback = new IXR_Message(xmlrpc_request('TrackMania.PlayerConnect', array('test-player', false)));
checkProtocol($callback->parse() && $callback->methodName === 'TrackMania.PlayerConnect'
    && $callback->params === array('test-player', false), 'Callback parsing');
$noArgs = new IXR_Message(xmlrpc_request('TrackMania.BeginRound', array()));
checkProtocol($noArgs->parse() && $noArgs->params === array(), 'Zero-argument callback');

$standardRequest = new IXR_RequestStd('GetPlayerInfo', array('test-player'));
checkProtocol($standardRequest->getXml() === xmlrpc_request('GetPlayerInfo', array('test-player'))
    && $standardRequest->getLength() === strlen($standardRequest->getXml()), 'Standard request constructor');
$standardResponse = new IXR_ResponseStd($state);
checkProtocol($standardResponse->getXml() === xmlrpc_response($state)
    && $standardResponse->getLength() === strlen($standardResponse->getXml()), 'Standard response constructor');

$client = new IXR_Client_Gbx();
checkProtocol($client->socket === false && $client->reqhandle === 0x80000000, 'Client initialization');
checkProtocol($client->query('GetVersion') === false && $client->getErrorCode() === -32300, 'Uninitialized query error');
$client->resetError();
checkProtocol(!$client->isError(), 'Error reset');
$multicall = new IXR_ClientMulticall_Gbx();
checkProtocol($multicall->reqhandle === 0x80000000 && $multicall->addCall('GetVersion', array()) === 0
    && $multicall->calls === array(array('methodName' => 'GetVersion', 'params' => array())), 'Multicall inherited constructor and queue');
$client->cb_message = array(array('TrackMania.BeginRound', array()));
checkProtocol($client->getCBResponses() === array(array('TrackMania.BeginRound', array()))
    && $client->getCBResponses() === array(), 'Callback queue drain');

$client->async_responses = array('RH1' => false, 'RH2' => null, 'RH3' => $state);
checkProtocol($client->getAsyncResponses('RH1') === array('RH1' => false), 'False-valued async response retrieved');
checkProtocol($client->getAsyncResponses('RH2') === array('RH2' => null), 'Null-valued async response retrieved');
checkProtocol($client->getAsyncResponses('unknown') === array()
    && $client->async_responses === array('RH3' => $state), 'Unknown async handle preserves queue');
checkProtocol($client->getAsyncResponses() === array('RH3' => $state)
    && $client->getAsyncResponses() === array(), 'Async queue drain');
checkProtocol($client->getAsyncResponses('RH1') === array(), 'Async handle consumed exactly once');
checkProtocol($client->readAsync(0) === false && $client->getErrorCode() === -32300, 'Uninitialized asynchronous poll reports error');

class OfflineAsyncClient extends IXR_Client_Gbx
{
    public $polledTimeout;

    function readCB($timeout = 2000)
    {
        $this->polledTimeout = $timeout;
        $this->async_responses['RHfixture'] = false;
        return false;
    }
}
$asyncClient = new OfflineAsyncClient();
checkProtocol($asyncClient->readAsync(1234) === true && $asyncClient->polledTimeout === 1234,
    'Async poll delegates timeout and reports response availability');
checkProtocol($asyncClient->getAsyncResponses('RHfixture') === array('RHfixture' => false),
    'Polled false response remains retrievable');

big_endian_test();
checkProtocol(multi_endian_unpack('Vsize/Vhandle', pack('VV', 42, 0x80000001))
    === array('size' => 42, 'handle' => 0x80000001), 'GBX header decoding');
$is_little_endian = false;
checkProtocol(multi_endian_unpack('Vsize/Vhandle', pack('VV', 42, 0x80000001))
    === array('size' => 42, 'handle' => 0x80000001), 'Alternate-endian header decoding');
unset($is_little_endian);

restore_error_handler();
echo 'Protocol checks: ' . $checks . ', failures: ' . $failures . PHP_EOL;
exit($failures === 0 ? 0 : 1);
