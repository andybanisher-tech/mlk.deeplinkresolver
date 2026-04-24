<?php

namespace Mlk\DlResolver\Api;

use Bitrix\Main\HttpRequest;
use Bitrix\Main\HttpResponse;
use Bitrix\Main\Web\Json;
use Mlk\DlResolver\Resolver\ResolverEngine;

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
            $errorMsg = $result['error'] ?? 'No matching rule or deeplink not found';
            return $this->error($response, $errorMsg, 404, $debug, $result['debug'] ?? null);
        }

        $responseData = [
            'status' => 'success',
            'data' => [
                'content_type' => $result['content_type'],
                'deeplink' => $result['deeplink']
            ]
        ];

        if ($debug && isset($result['debug'])) {
            $responseData['debug'] = $result['debug'];
        }

        $response->setContent(Json::encode($responseData));
        return $response;
    }

    protected function error(HttpResponse $response, string $message, int $code, bool $debug = false, ?array $debugData = null): HttpResponse
    {
        $response->setStatus($code);
        $data = [
            'status' => 'error',
            'message' => $message
        ];
        if ($debug && $debugData !== null) {
            $data['debug'] = $debugData;
        }
        $response->setContent(Json::encode($data));
        return $response;
    }
}
