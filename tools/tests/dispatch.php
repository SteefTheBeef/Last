<?php

set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
require_once __DIR__ . '/../../includes/fast_general.php';

$checks = 0;
$failures = 0;
$trace = array();
function checkDispatch($condition, $name)
{
    global $checks, $failures;
    $checks++;
    echo ($condition ? 'PASS' : 'FAIL') . ': ' . $name . PHP_EOL;
    if (!$condition) {
        $failures++;
    }
}
function firstTest($event, $value)
{
    global $trace, $_callFuncsArgs;
    $trace[] = 'first:' . $value;
    $_callFuncsArgs[1] = 'modified';
}
function secondTest($event, $value) { global $trace; $trace[] = 'second:' . $value; }
function firstTest_Reverse($event, $value) { global $trace; $trace[] = 'reverse-first'; }
function secondTest_Reverse($event, $value) { global $trace; $trace[] = 'reverse-second'; }
function firstTest_Post($event, $value) { global $trace; $trace[] = 'post-first'; }
function secondTest_Post($event, $value) { global $trace; $trace[] = 'post-second'; }
function dropperTest($event, $value) { global $trace; $trace[] = 'dropper'; return 'DropEvent'; }
function explicitDropTest($event, $value) { global $trace; $trace[] = 'explicit-drop'; dropEvent(); }
function directFixture($value) { global $trace; $trace[] = 'direct:' . $value; }

$_debug = $_mldebug = $_memdebug = 0;
$_pdebug = $_memdebugs = array();
$_memdebugmode = false;
$_needenable = false;
$_DisabledPlugins = $_EnabledPlugins = array();
$_event_list = array('Test');
$_plugin_funclist = $_funcs_plugin = $_plugin_list = array();
registerPlugin('second', 20);
registerPlugin('first', 10);
callFuncsArray(array('Test', 'initial'));
checkDispatch($trace === array('first:initial', 'second:modified', 'reverse-second', 'reverse-first', 'post-first', 'post-second'), 'Priority reverse post ordering and argument mutation');
$trace = array();
callFuncsArray(array('event' => 'Test', 'context' => 'initial'));
checkDispatch($trace[0] === 'first:initial' && $trace[1] === 'second:modified', 'String-keyed event arguments remain positional');
$trace = array();
callFuncsArray(array('event' => 'Function', 'function' => 'directFixture', 'context' => 'value'));
checkDispatch($trace === array('direct:value'), 'Special function event positional dispatch');
callFuncsArray(array());
checkDispatch($trace === array('direct:value'), 'Empty event ignored');
$_DisabledPlugins['disabled'] = true;
registerPlugin('disabled');
checkDispatch(!isset($_plugin_list['disabled']), 'Disabled plugin not registered');
$_needenable = true;
registerPlugin('notEnabled');
checkDispatch(!isset($_plugin_list['notEnabled']), 'Custom plugin requires enabling');
$_EnabledPlugins['enabled'] = true;
registerPlugin('enabled');
checkDispatch(isset($_plugin_list['enabled']), 'Explicitly enabled custom plugin registered');
$_needenable = false;
registerPlugin('dropper', 15);
$trace = array();
callFuncs('Test', 'initial');
checkDispatch($trace === array('first:initial', 'dropper', 'reverse-first', 'post-first'), 'DropEvent stops lower-priority handlers');
$_plugin_funclist['Test'] = array('explicitDropTest' => 5, 'secondTest' => 20);
$trace = array();
callFuncs('Test', 'initial');
checkDispatch($trace === array('explicit-drop'), 'Explicit dropEvent stops propagation');

$directory = sys_get_temp_dir() . '/fast-dispatch-' . bin2hex(random_bytes(8));
mkdir($directory);
try {
    file_put_contents($directory . '/plugin.fixture.php', '<?php $GLOBALS["loadedFixture"] = true;');
    mkdir($directory . '/plug');
    loadPlugins($directory, 'plug');
    checkDispatch(!empty($GLOBALS['loadedFixture']), 'PHP plugin file loaded without including suffix-less directory');
    loadPlugins($directory . '/missing', 'plugin.');
    checkDispatch(true, 'Missing optional plugin directory ignored');
} finally {
    unlink($directory . '/plugin.fixture.php');
    rmdir($directory . '/plug');
    rmdir($directory);
}

restore_error_handler();
echo 'Dispatch checks: ' . $checks . ', failures: ' . $failures . PHP_EOL;
exit($failures === 0 ? 0 : 1);
