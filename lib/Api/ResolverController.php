<?php
namespace Mlk\AppDeepLinkResolver\Api;

use Bitrix\Main\HttpRequest;
use Bitrix\Main\HttpResponse;
use Bitrix\Main\Web\Json;
use Mlk\AppDeepLinkResolver\Resolver\ResolverEngine;

class ResolverController
{
    public function execute(HttpRequest $request): HttpResponse
    {
        $response = new HttpResponse();
        $response->addHeader('Content-Type', 'application/json; charset=utf-8');
        $response->addHeader('X-Powered-By', 'MLK DeepLink Resolver');

        $url = trim($request->getPost('url') ?: $request->getQuery('url'));
        $debug = (bool)($request->getPost('debug') ?: $request->getQuery('debug'));

        if (empty($url)) {
            return $this->error($response, 'Missing "url" parameter', 400, $debug);
        }

        if (strpos($url, '/') !== 0 && strpos($url, 'http://') !== 0 && strpos($url, 'https://') !== 0) {
            return $this->error($response, 'Invalid URL format (must be absolute or start with /)', 400, $debug);
        }

        $engine = new ResolverEngine();
        $result = $engine->resolve($url, $debug);

        if (!$result['success']) {
            return $this->error($response, $result['error'], 404, $debug, $result['debug'] ?? null);
        }

        $responseData = ['success' => true, 'content_type' => $result['content_type'], 'deeplink' => $result['deeplink']];
        if ($debug && isset($result['debug'])) $responseData['debug'] = $result['debug'];
        $response->setContent(Json::encode($responseData));
        return $response;
    }

    protected function error(HttpResponse $response, string $message, int $code, bool $debug = false, ?array $debugData = null): HttpResponse
    {
        $response->setStatus($code);
        $data = ['success' => false, 'error' => $message];
        if ($debug && $debugData !== null) $data['debug'] = $debugData;
        $response->setContent(Json::encode($data));
        return $response;
    }
}
?>