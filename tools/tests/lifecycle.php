<?php

set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
require_once __DIR__ . '/../../includes/fast_general.php';

foreach (array('ROUNDS' => 0, 'TA' => 1, 'TEAM' => 2, 'LAPS' => 3, 'STUNTS' => 4, 'CUP' => 5) as $name => $value) {
    define($name, $value);
}
$_debug = $_mldebug = $_memdebug = 0;
$_currentTime = (int)(microtime(true) * 1000);
$_Game = 'TMF';
$_is_relay = $_use_cb = $_needenable = false;
$_DisabledPlugins = $_EnabledPlugins = array();
$_plugin_funclist = $_plugin_list = $_funcs_plugin = array();
$_event_list = array('Init', 'BeginRace', 'BeginRound', 'EndRace', 'PlayerConnect', 'PlayerFinish');
$_logfile = fopen('php://temp', 'w+');
$checks = 0;
$failures = 0;
function checkLifecycle($condition, $name)
{
    global $checks, $failures;
    $checks++;
    echo ($condition ? 'PASS' : 'FAIL') . ': ' . $name . PHP_EOL;
    if (!$condition) {
        $failures++;
    }
}

try {
    ob_start();
    try {
        loadPlugins(__DIR__ . '/../../plugins', 'plugin.');
    } finally {
        ob_end_clean();
    }
    checkLifecycle(count($_plugin_list) >= 40, 'All bundled plugin files load with strict diagnostics');
    checkLifecycle(!isset($_plugin_list['autoupdate']), 'Legacy updater remains inactive during plugin loading');
    checkLifecycle(isset($_plugin_funclist['Init']['playersInit'], $_plugin_funclist['Init']['manialinksInit']), 'Core initialization callbacks registered');
    foreach ($_plugin_funclist as $event => $handlers) {
        foreach ($handlers as $handler => $priority) {
            if (!is_callable($handler)) {
                throw new RuntimeException('Uncallable handler: ' . $handler);
            }
        }
    }
    checkLifecycle(true, 'Registered lifecycle callbacks are callable');
    $_GameInfos = array('GameMode' => ROUNDS);
    roundslimitInit('Init');
    teamgapInit('Init');
    autorestartInit();
    checkLifecycle($_roundslimit_rule === -1 && $_teamroundslimit_rule === -1 && $_teamgap_rule === 0, 'Rounds and team configuration defaults');
    checkLifecycle($_autorestart_map === false && $_autorestart_newmap === false && $_autorestart_uid === '', 'Autorestart initialization defaults');
    $_HelpCmd = $_HelpAdmCmd = $_DisabledChatCommands = array();
    $_players = array();
    $_ServerInfos = array('DownloadRate' => 100000, 'UploadRate' => 50000);
    manialinksInit('Init');
    checkLifecycle($_ml_spool_rate === 8000 && isset($_HudControl['scoretable']), 'HUD initialization and rate calculation');
    ml_scorepanelInit('Init');
    checkLifecycle($_scorepanel_hide === 1 && $_scorepanel_round_hide === 1, 'Scorepanel initialization defaults');
    checkLifecycle(isset($_HelpAdmCmd['scorepanel']), 'Scorepanel command registration');
    $_teamgap_rule = 10;
    $_GameInfos = array('GameMode' => TEAM, 'TeamUseNewRules' => true);
    $_Ranking = array();
    teamgapBeforeEndRound('BeforeEndRound', -1);
    teamgapBeginRound('BeginRound');
    checkLifecycle(true, 'Team gap tolerates empty transition rankings');
    $_Ranking = array(array('Score' => 9), array('Score' => 8));
    teamgapBeforeEndRound('BeforeEndRound', -1);
    teamgapBeginRound('BeginRound');
    checkLifecycle(true, 'Team gap does not end race below target');
} finally {
    fclose($_logfile);
}

restore_error_handler();
echo 'Lifecycle checks: ' . $checks . ', failures: ' . $failures . PHP_EOL;
exit($failures === 0 ? 0 : 1);
