<?php
use Bitrix\Main\Loader;

$moduleId = 'mlk.dlresolver';

Loader::registerAutoLoadClasses($moduleId, [
    'Mlk\\DlResolver\\Resolver\\Rule' => 'lib/Resolver/Rule.php',
    'Mlk\\DlResolver\\Resolver\\RuleTable' => 'lib/Resolver/RuleTable.php',
    'Mlk\\DlResolver\\Resolver\\ResolverEngine' => 'lib/Resolver/ResolverEngine.php',
    'Mlk\\DlResolver\\Resolver\\ResponseBuilder' => 'lib/Resolver/ResponseBuilder.php',
    'Mlk\\DlResolver\\Api\\ResolverController' => 'lib/Api/ResolverController.php',
]);
?>