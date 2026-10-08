<?php

set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});
function registerPlugin($name, $priority, $version) {}
function console($message) {}
function debugPrint($name, $data) {}
function tm_substr($text) { return $text; }
require_once __DIR__ . '/../../plugins/plugin.02.mysql.php';
$_is_relay = false;
require_once __DIR__ . '/../../plugins/plugin.85.match.php';

class FakeMysqlConnection
{
    public $errno = 0;
    public $error = '';
    public $result = true;
    public $exception = null;
    public $queries = array();
    public $closed = false;
    public $escaped = array();

    function query($query)
    {
        $this->queries[] = $query;
        if ($this->exception) {
            throw $this->exception;
        }
        return $this->result;
    }
    function close() { $this->closed = true; }
    function real_escape_string($value)
    {
        $this->escaped[] = $value;
        return addslashes($value);
    }
}

$checks = 0;
$failures = 0;
function checkMysql($condition, $name)
{
    global $checks, $failures;
    $checks++;
    echo ($condition ? 'PASS' : 'FAIL') . ': ' . $name . PHP_EOL;
    if (!$condition) {
        $failures++;
    }
}

$_debug = 0;
dbmysqlInit('Init');
checkMysql($_DB === false && $_DBretry === false && $_DBretry_queries === array(), 'Unconfigured database initialization');
checkMysql(dbmysql_query('SELECT 1') === false && $_DBretry_queries === array(), 'Disabled database does not queue');
$_DB = new FakeMysqlConnection();
$connection = $_DB;
checkMysql(dbmysql_query('UPDATE test SET value=1') === true && $connection->queries === array('UPDATE test SET value=1'), 'Successful query uses explicit connection');
$connection->result = (object)array('row' => 1);
checkMysql(dbmysql_query('SELECT 1') === $connection->result, 'Result object is preserved');
$connection->result = false;
$connection->errno = 1064;
$connection->error = 'fixture syntax error';
checkMysql(dbmysql_query('invalid SQL') === false && $_DBretry_queries === array(), 'SQL error returns false without retry queue');
$connection->exception = new mysqli_sql_exception('fixture SQL exception', 1064);
checkMysql(dbmysql_query('invalid SQL') === false, 'Strict mysqli exception returns false');
$connection->exception = new mysqli_sql_exception('fixture connection lost', 2006);
$_DBkeepalive = 15;
checkMysql(dbmysql_query('INSERT fixture') === false && $_DB === false && $_DBretry
    && $_DBkeepalive === 8 && $_DBretry_queries === array('INSERT fixture') && $connection->closed, 'Connection loss closes connection and queues failed query');
checkMysql(dbmysql_query('INSERT second') === false && $_DBretry_queries === array('INSERT fixture', 'INSERT second'), 'Disconnected retry queues next query');
$_DBretry_queries = array_fill(0, 3000, 'fixture');
checkMysql(dbmysql_query('INSERT overflow') === false && count($_DBretry_queries) === 3000, 'Retry queue bounded to 3000 queries');
$_DB = new FakeMysqlConnection();
$connection = $_DB;
dbmysql_close();
checkMysql($_DB === false && $connection->closed, 'Explicit close restores false state');
dbmysql_close();
checkMysql($_DB === false, 'Repeated close is safe');
$_DBretry = false;
$_DBretry_queries = array();
$_DBkeepalive = 0;
dbmysqlInit('Init');
checkMysql($_DBkeepalive === 1 && !$_DBretry, 'Initialization clamps keepalive and resets retry state');
matchDbStore(array(), array(), array());
checkMysql($_DB === false, 'Match persistence disabled without database');
$_DB = new FakeMysqlConnection();
$_match_db_table = 'test_matches';
$_match_conf = array('Title' => "test's match");
$_SystemInfo = array('ServerLogin' => 'test-server');
$_ServerOptions = array('Name' => 'test server');
$_FGameMode = "mode's name";
$ranking = array(array('Login' => 'test-player', 'NickName' => "player's name", 'BestTime' => 12345,
    'Score' => 10, 'BestCheckpoints' => array(1000, 2000)));
matchDbStore($ranking, array('UId' => 'test-map', 'Name' => 'test map', 'Environnement' => 'Stadium'), array('GameMode' => 0));
checkMysql(count($_DB->queries) === 1 && str_contains($_DB->queries[0], 'INSERT INTO `test_matches`'), 'Match insert uses database wrapper');
checkMysql(in_array("mode's name", $_DB->escaped, true) && in_array("player's name", $_DB->escaped, true)
    && str_contains($_DB->queries[0], "test\\'s match"), 'Match text fields escaped by explicit connection');
dbmysql_close();

restore_error_handler();
echo 'MySQL checks: ' . $checks . ', failures: ' . $failures . PHP_EOL;
exit($failures === 0 ? 0 : 1);
