<?php

require_once 'vendor/autoload.php';

CModule::AddAutoloadClasses("travelsoft.tsoperator.amointegration", [
    "\\travelsoft\\amocrm\\EventsHandlers" => "lib/EventsHandlers.php",
    "\\travelsoft\\amocrm\\Option" => "lib/Option.php",
    "\\travelsoft\\amocrm\\tables\\LeadsTable" => "lib/tables/LeadsTable.php",
    "\\travelsoft\\amocrm\\tables\\TaskQueueTable" => "lib/tables/TaskQueueTable.php",
]);
