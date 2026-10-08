<?php

set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
function registerPlugin($name, $priority, $version, $dependency = null) {}
function console2($message) {}
function localeText($login, $key) { return ''; }
function sendToRelay($login, $message) { $GLOBALS['relayMessages'][] = $message; }
function sendToMaster($message) { $GLOBALS['masterMessages'][] = $message; }
function insertEvent(...$args) { $GLOBALS['stateEvents'][] = $args; }
function addCall(...$args) { $GLOBALS['stateCalls'][] = $args; }
require_once __DIR__ . '/../../plugins/plugin.01.players.php';

class StateWakeupFixture
{
    function __wakeup() { $GLOBALS['stateWakeup'] = true; }
}

$checks = 0;
$failures = 0;
function checkState($condition, $name)
{
    global $checks, $failures;
    $checks++;
    echo ($condition ? 'PASS' : 'FAIL') . ': ' . $name . PHP_EOL;
    if (!$condition) {
        $failures++;
    }
}
function restoreFixture($data, $ratios = null, $previous = false, $live = true)
{
    global $_StoreFile, $_CallVoteRatios, $_RestorePrevious, $_RestoreLive, $_StoredInfos;
    $GLOBALS['stateEvents'] = $GLOBALS['stateCalls'] = array();
    $_CallVoteRatios = $ratios ?? array(array('Command' => 'FastKeepAlive:' . time()));
    $_RestorePrevious = $previous;
    $_RestoreLive = $live;
    file_put_contents($_StoreFile, $data);
    playersRestoreFastState();
    return $GLOBALS['stateEvents'][0];
}

$_debug = 0;
$_is_relay = false;
$_ChallengeInfo = array('UId' => 'test-map');
$_PlayerList = array(array('Login' => 'test-player'));
$_Ranking = array(array('Login' => 'test-player', 'BestTime' => 12345));
$_StoreFile = tempnam(sys_get_temp_dir(), 'fast-state-');
$valid = array('ChallengeInfo' => $_ChallengeInfo, 'PlayerList' => $_PlayerList, 'Ranking' => $_Ranking);
try {
    $event = restoreFixture(serialize($valid));
    checkState($event[1] === 'live' && $event[2] >= 0 && $event[2] < 90 && !$event[3] && !$event[4], 'Valid live snapshot restoration');
    checkState($_StoredInfos === $valid, 'Snapshot array format preserved');
    $changed = $valid;
    $changed['PlayerList'] = array();
    $changed['Ranking'] = array();
    $event = restoreFixture(serialize($changed));
    checkState($event[1] === 'live' && $event[3] && $event[4], 'Player and ranking changes detected');
    foreach (array('', 'not serialized', 'a:1:{', serialize(false), serialize(null), serialize(42), serialize('text')) as $index => $invalid) {
        $event = restoreFixture($invalid);
        checkState($event === array('RestoreInfos', 'start', -1, true, true) && $_StoredInfos === array(), 'Invalid snapshot rejected ' . $index);
    }
    $event = restoreFixture(serialize(new StateWakeupFixture()));
    checkState($event[1] === 'start' && empty($GLOBALS['stateWakeup']), 'Serialized objects do not instantiate or wake up');
    foreach (array('PlayerList', 'Ranking') as $field) {
        $incomplete = $valid;
        unset($incomplete[$field]);
        checkState(restoreFixture(serialize($incomplete))[1] === 'start', 'Missing live field falls back ' . $field);
    }
    $incomplete = $valid;
    $incomplete['PlayerList'] = 'invalid';
    checkState(restoreFixture(serialize($incomplete))[1] === 'start', 'Wrong live field type falls back');
    $incomplete['ChallengeInfo'] = new StateWakeupFixture();
    checkState(restoreFixture(serialize($incomplete))[1] === 'start' && empty($GLOBALS['stateWakeup']), 'Object challenge metadata cannot become live state');
    $ratios = array(null, 'invalid', new stdClass(), array(), array('Command' => null), array('Command' => 'FastKeepAlive:invalid'), array('Command' => 'FastKeepAlive:99999999999999999999999999'));
    checkState(restoreFixture(serialize($valid), $ratios)[1] === 'start', 'Invalid timestamp data is ignored');
    $ratios[] = array('Command' => 'FastKeepAlive:' . time());
    checkState(restoreFixture(serialize($valid), $ratios)[1] === 'live', 'Valid keepalive after invalid entry is accepted');
    checkState(restoreFixture(serialize($valid), array(array('Command' => 'FastKeepAlive:' . (time() - 120))))[1] === 'start', 'Expired live state not restored');
    checkState(restoreFixture(serialize($valid), array(array('Command' => 'FastKeepAlive:' . (time() + 120))))[1] === 'start', 'Future timestamp not restored');
    $otherMap = $valid;
    $otherMap['ChallengeInfo']['UId'] = 'other-map';
    checkState(restoreFixture(serialize($otherMap))[1] === 'start', 'Different map prevents live restoration');
    checkState(restoreFixture(serialize(array()), array(), true)[1] === 'previous', 'Previous configuration restoration retained');
    checkState(restoreFixture('invalid', array(), false, false)[1] === 'start', 'Disabled restore bypasses decoding');
    unlink($_StoreFile);
    $GLOBALS['stateEvents'] = array();
    $_RestoreLive = true;
    playersRestoreFastState();
    checkState($GLOBALS['stateEvents'][0][1] === 'start', 'Missing state file starts cleanly');
    $_is_relay = true;
    $GLOBALS['masterMessages'] = $GLOBALS['stateEvents'] = array();
    playersRestoreFastState();
    checkState($GLOBALS['masterMessages'] === array('GetFastConfigs') && $GLOBALS['stateEvents'] === array(), 'Relay requests master state without local decoding');
} finally {
    if (file_exists($_StoreFile)) {
        unlink($_StoreFile);
    }
}

restore_error_handler();
echo 'State checks: ' . $checks . ', failures: ' . $failures . PHP_EOL;
exit($failures === 0 ? 0 : 1);
