<?php

set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

function console($message) {}
function debugPrint($name, $data) {}

require_once __DIR__ . '/../../includes/GbxRemote.fast.php';
require_once __DIR__ . '/../../includes/GbxRemote.response.php';
require_once __DIR__ . '/../../includes/web_access.php';
require_once __DIR__ . '/../../includes/xmlrpc_db_access.php';

$checks = 0;
$failures = 0;
function checkWeb($condition, $name)
{
    global $checks, $failures;
    $checks++;
    echo ($condition ? 'PASS' : 'FAIL') . ': ' . $name . PHP_EOL;
    if (!$condition) {
        $failures++;
    }
}

class OfflineWebaccessUrl extends WebaccessUrl
{
    function _open($opentimeout = 0.0, $waittimeout = 5.0) {}
}

class FakeWebTransport
{
    public $retried = array();
    public $sent = array();
    public $response = true;

    function retry($url) { $this->retried[] = $url; }
    function request($url, $callback, $data, $xmlrpc = false)
    {
        $this->sent[] = array($url, $callback, $data, $xmlrpc);
        return $this->response;
    }
}

$web = new Webaccess();
checkWeb($web->_WebaccessList === array() && $web->getAllSpools() === array(0, 0), 'HTTP manager initialization');
$read = $write = $except = null;
checkWeb($web->select($read, $write, $except, 0) === 0 && $read === array(), 'Empty select lifecycle');
checkWeb(getHostPortPath('http://example.test:8080/rpc?test=1') === array('example.test', 8080, '/rpc?test=1'), 'URL host port and path');
checkWeb(getHostPortPath('invalid') === array(false, false, false), 'Invalid URL rejection');

$client = new OfflineWebaccessUrl($web, 'example.test', 80);
checkWeb($client->wa === $web && $client->_state === 'CLOSED' && $client->_spool === array()
    && is_int($client->_query_time), 'HTTP endpoint initialization');
$received = null;
$callback = function ($response, $context) use (&$received) { $received = array($response, $context); };
$query = array('Path' => '/rpc', 'Callback' => array($callback, 'context' => 'test-context'),
    'QueryDatas' => '<request/>', 'IsXmlrpc' => true, 'KeepaliveMinTimeout' => 300,
    'OpenTimeout' => 3, 'WaitTimeout' => 5);
checkWeb($client->request($query) === true && $query['Response'] === array(), 'Offline asynchronous request queue');
checkWeb(str_contains($query['HDatas'], "POST /rpc HTTP/1.1\r\n")
    && str_contains($query['HDatas'], "Content-length: 10\r\n"), 'Request headers and body length');
$client->_bad('fixture failure');
checkWeb($client->_state === 'BAD' && $received[1] === 'test-context'
    && str_contains($received[0]['Error'], 'fixture failure'), 'Array-safe error callback and positional context');
checkWeb($client->_bad_timeout === 20, 'Initial retry delay');
$client->_bad('fixture failure');
checkWeb($client->_bad_timeout === 40, 'Retry backoff');
$client->retry();
checkWeb($client->_bad_timeout === 0, 'Explicit retry reset');
$invalidQuery = $query;
$invalidQuery['Callback'] = array('not_a_callable');
checkWeb($client->request($invalidQuery) === false, 'Invalid callback rejection without diagnostics');

$handleHeaders = new ReflectionMethod(WebaccessUrl::class, '_handleHeaders');
$fixtures = array(
    array("HTTP/1.1 200 OK\r\nContent-Length: 5\r\nConnection: keep-alive\r\n\r\nhello", 'hello'),
    array("HTTP/1.1 200 OK\r\nContent-Length: 0\r\n\r\n", ''),
    array("HTTP/1.1 200 OK\r\nTransfer-Encoding: chunked\r\n\r\n2\r\nhe\r\n3\r\nllo\r\n0\r\n\r\n", 'hello'),
    array("HTTP/1.1 200 OK\r\nContent-Encoding: gzip\r\nContent-Length: " . strlen(gzencode('hello')) . "\r\n\r\n" . gzencode('hello'), 'hello'),
    array("HTTP/1.1 200 OK\r\nContent-Encoding: deflate\r\nContent-Length: " . strlen(gzdeflate('hello')) . "\r\n\r\n" . gzdeflate('hello'), 'hello')
);
foreach ($fixtures as $index => $fixture) {
    $endpoint = new OfflineWebaccessUrl($web, 'example.test', 80);
    $endpoint->_spool = array(array('State' => 'RECEIVE', 'Headers' => array(), 'Close' => false));
    $endpoint->_response = $fixture[0];
    checkWeb($handleHeaders->invoke($endpoint) === true && $endpoint->_spool[0]['Response']['Message'] === $fixture[1]
        && $endpoint->_spool[0]['Response']['Code'] === 200, 'HTTP response fixture ' . $index);
}
$endpoint = new OfflineWebaccessUrl($web, 'example.test', 80);
$endpoint->_spool = array(array('State' => 'RECEIVE', 'Headers' => array(), 'Close' => false));
$endpoint->_response = "HTTP/1.1 200 OK\r\nContent-Length: 5\r\nSet-Cookie: session=test; path=/rpc\r\nKeep-Alive: timeout=15, max=20\r\nConnection: keep-alive\r\n\r\nhe";
checkWeb($handleHeaders->invoke($endpoint) === false, 'Incomplete response waits');
$endpoint->_response .= 'llo';
checkWeb($handleHeaders->invoke($endpoint) === true && $endpoint->_cookies['session']['Value'] === 'test'
    && $endpoint->_serv_keepalive_timeout === 15 && $endpoint->_serv_keepalive_max === 20, 'Cookies and keepalive negotiation');

$transport = new FakeWebTransport();
$db = new XmlrpcDB($transport, 'http://example.test/rpc', 'TMF', 'test-login', 'test-password', 'FAST', '3.2.4f', 'NED');
checkWeb(count($db->_requests) === 1 && $db->_requests[0]['methodName'] === 'dedimania.Authenticate'
    && $transport->retried === array('http://example.test/rpc'), 'Database constructor and authentication queue');
checkWeb($db->addRequest(null, 'test.Method', 'value') === 1, 'Database request queue index');
$saved = $db->clearRequests(true);
checkWeb(count($saved[0]) === 2 && count($saved[1]) === 2 && count($db->_requests) === 1, 'Clear requests returns old queues and restores authentication');
checkWeb($db->_makeResponseDatas(null, array(), array()) === array('methodName' => null, 'params' => array()), 'Empty multicall response');
$db->setPackmask('Stadium');
checkWeb($db->_server['Packmask'] === 'Stadium', 'Database packmask update');
$dbReceived = null;
$dbCallback = function ($response, $context) use (&$dbReceived) { $dbReceived = array($response, $context); };
$db->addRequest(array($dbCallback, 'context' => 'db-context'), 'test.Method');
$requests = $db->_requests;
checkWeb($db->sendRequests() === true && count($db->_requests) === 1, 'Asynchronous database send resets queue');
$sent = $transport->sent[0];
$parsed = new IXR_Message($sent[2]);
checkWeb($parsed->parse() && $parsed->methodName === 'system.multicall'
    && count($parsed->params[0]) === 3 && $sent[3] === true, 'Database multicall includes authentication and warnings');
$reply = xmlrpc_response(array(array(array('authenticated' => true)), array(array('value' => 42)),
    array(array('globalTTR' => 0.25, 'methods' => array()))));
$db->_callCB(array('Code' => 200, 'Message' => $reply), $sent[1][1], $sent[1][2]);
checkWeb($dbReceived[1] === 'db-context' && $dbReceived[0]['Data']['params'] === array('value' => 42), 'Database response callback and positional context');
$transport->response = false;
$db->addRequest(null, 'test.Method');
checkWeb($db->sendRequests() === false && $db->isBad(), 'Rejected transport sets database error state');
$db->retry();
checkWeb(!$db->isBad() && count($transport->retried) === 2, 'Database retry clears error state');
checkWeb($db->RequestWait('test.Method') === false && $db->isBad()
    && count($db->_requests) === 1, 'Rejected synchronous request resets queue and returns false');

restore_error_handler();
echo 'HTTP/database checks: ' . $checks . ', failures: ' . $failures . PHP_EOL;
exit($failures === 0 ? 0 : 1);
