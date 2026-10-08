<?php

set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
function registerCommand($name, $help, $admin = false) {}
function localeText($login, $key) { return ''; }
function console($message) {}
function addCall(...$args) { $GLOBALS['commandCalls'][] = $args; return 0; }
require_once __DIR__ . '/../../plugins/cmd/chat.spec.php';
require_once __DIR__ . '/../../includes/fast_common.php';

$checks = 0;
$failures = 0;
function checkCommandCallback($condition, $name)
{
    global $checks, $failures;
    $checks++;
    echo ($condition ? 'PASS' : 'FAIL') . ': ' . $name . PHP_EOL;
    if (!$condition) {
        $failures++;
    }
}
function commandFixture($handler, $password, $forced = false, $rights = false, $params = array('play'))
{
    global $_players, $_ServerOptions, $_is_relay;
    $_is_relay = false;
    $_ServerOptions = array('Password' => $password);
    $_players = array('test-player' => array('PlayRights' => $rights, 'Forced' => $forced, 'ForcedByHimself' => false));
    $GLOBALS['commandCalls'] = array();
    $handler('test-author', 'test-player', $params);
    return $GLOBALS['commandCalls'];
}
foreach (array('chat_blue' => 0, 'chat_red' => 1) as $handler => $team) {
    $calls = commandFixture($handler, 'private-password');
    checkCommandCallback(!$_players['test-player']['PlayRights'] && count($calls) === 2
        && $calls[0] === array('test-player', 'ForcePlayerTeam', 'test-player', $team), $handler . ' protected server does not grant play access');
    $calls = commandFixture($handler, '');
    checkCommandCallback($_players['test-player']['PlayRights'] && count($calls) === 4
        && $calls[1] === array('test-player', 'ForceSpectator', 'test-player', 2)
        && $calls[2] === array('test-player', 'ForceSpectator', 'test-player', 0), $handler . ' open server permits play');
    $calls = commandFixture($handler, '', true);
    checkCommandCallback(count($calls) === 2 && str_contains($calls[1][2], 'someone else forced'), $handler . ' respects externally forced state');
    $calls = commandFixture($handler, 'private-password', false, true);
    checkCommandCallback(count($calls) === 4, $handler . ' preserves existing play rights');
    $calls = commandFixture($handler, '', false, false, array());
    checkCommandCallback(count($calls) === 2 && !$_players['test-player']['PlayRights'], $handler . ' team-only selection does not change play rights');
    $_is_relay = true;
    $GLOBALS['commandCalls'] = array();
    $handler('test-author', 'test-player', array('play'));
    checkCommandCallback($GLOBALS['commandCalls'] === array(), $handler . ' ignored on relay');
}

$_debug = 0;
$_currentTime = 123456;
$_methods_related_to_call = array();
$_use_flowcontrol = true;
$_MFCTransition = array('Transition' => '');
$_MFCTransitionGet = 'Synchro -> Play';
$_transition_events = array('Synchro -> Play' => 'BeforePlay');
$_multicall_response = array('multicall' => array(array('methodName' => 'ManualFlowControlGetCurTransition')),
    array('Synchro -> Play'));
multicallAutoStoreInfos();
checkCommandCallback($_MFCTransition['Event'] === 'BeforePlay' && $_MFCTransition['Time'] === 123456,
    'Flow-control response uses shared transition mapping');
$_SystemInfo = array('ServerLogin' => 'test-server');
$_relays = array();
managePlayer('relay-server', array('Login' => 'relay-server', 'Flags' => 100000));
checkCommandCallback(isset($_relays['relay-server']), 'Relay discovery updates shared relay table');
managePlayer('test-server', array('Login' => 'test-server', 'Flags' => 100000));
checkCommandCallback(!isset($_relays['test-server']), 'Own server excluded from relay discovery');
$_relays['relay-server']['Master'] = true;
managePlayer('relay-server', array('Login' => 'relay-server', 'Flags' => 100000));
checkCommandCallback($_relays['relay-server']['Master'] === true, 'Relay refresh preserves master flag');

restore_error_handler();
echo 'Command/callback checks: ' . $checks . ', failures: ' . $failures . PHP_EOL;
exit($failures === 0 ? 0 : 1);
