<?php
use Bitrix\Main\Loader;

$moduleId = 'mlk.deeplinkresolver';

Loader::registerAutoLoadClasses($moduleId, [
    'Mlk\\DeepLinkResolver\\Resolver\\Rule' => 'lib/Resolver/Rule.php',
    'Mlk\\DeepLinkResolver\\Resolver\\RuleTable' => 'lib/Resolver/RuleTable.php',
    'Mlk\\DeepLinkResolver\\Resolver\\ResolverEngine' => 'lib/Resolver/ResolverEngine.php',
    'Mlk\\DeepLinkResolver\\Resolver\\ResponseBuilder' => 'lib/Resolver/ResponseBuilder.php',
    'Mlk\\DeepLinkResolver\\Api\\ResolverController' => 'lib/Api/ResolverController.php',
]);