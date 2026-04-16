<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');
use Bitrix\Main\Loader;
use Bitrix\Main\Context;
use Mlk\AppDeepLinkResolver\Api\ResolverController;

if (!Loader::includeModule('mlk.appdeeplinkresolver')) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Module not loaded']);
    die();
}

$request = Context::getCurrent()->getRequest();
$controller = new ResolverController();
$response = $controller->execute($request);
$response->send();