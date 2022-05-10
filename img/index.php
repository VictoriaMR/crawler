<?php
//基础文件
define('APP_PATH', strtr(__DIR__, '\\', '/'));
define('BASE_PATH', dirname(APP_PATH));
require APP_PATH.'/start.php';
//进程循环
foreach ($aQueue as $sQueue)
{
    if ($oQueue->get($sQueue) == 'ok') {
        continue;
    }
    $sResult = $oRobot->$sQueue();
    // 完成步骤记录进程
    if ($sResult) {
        $oQueue->add($sQueue, 'ok');
    }
}
dd('end');