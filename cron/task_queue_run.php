<?php

define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
define('BX_CRONTAB', true);
define('NO_AGENT_CHECK', true);

$_SERVER['DOCUMENT_ROOT'] = $_SERVER['DOCUMENT_ROOT'] ?? realpath(__DIR__ . '/../../../..');
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

use Bitrix\Main\Loader;
use travelsoft\amocrm\tables\TaskQueueTable;
use travelsoft\amocrm\Utils;
use travelsoft\booking\Logger;
use Bitrix\Main\Type\DateTime;

if (!Loader::includeModule('travelsoft.travelbooking')) {
    return;
}

if (!Loader::includeModule('travelsoft.tsoperator.amointegration')) {
    return;
}

$logPath = $_SERVER['DOCUMENT_ROOT'] . '/upload/amocrm_integration_errors_logs/task_queue_' . date('d_m_y') . '.txt';
$logger = new Logger($logPath);

$tasks = TaskQueueTable::getList([
    'filter' => [
        [
            'LOGIC' => 'OR',
            ['=DATE_RUN' => null],
        ],
    ],
    'order' => ['ID' => 'ASC'],
    'limit' => 50,
])->fetchAll();

foreach ($tasks as $task) {

    try {
        TaskQueueTable::update($task['ID'], ['DATE_RUN' => new DateTime()]);
        switch ($task['TYPE']) {
            case TaskQueueTable::TYPE_ORDER_LEAD:
                Utils::enqueueOrderLeadTask((int) $task['OBJECT_ID']);
                break;
            case TaskQueueTable::TYPE_CALLBACK_LEAD:
                Utils::createLeadAndContactFromIblockElement((int) $task['OBJECT_ID']);
                break;
            default:
                $logger->write('Unknown task type: ' . $task['TYPE']);
        }

        TaskQueueTable::delete($task['ID']);
    } catch (\Throwable $e) {
        $logger->write($e->getMessage());
    }
}
