<?php

namespace Mlk\DeepLinkResolver\Resolver;

use Bitrix\Main\Web\Json;

class ResponseBuilder
{
	public static function success(string $contentType, string $deeplink): string
	{
		return Json::encode([
			'success' => true,
			'content_type' => $contentType,
			'deeplink' => $deeplink
		]);
	}

	public static function error(string $message): string
	{
		return Json::encode([
			'success' => false,
			'error' => $message
		]);
	}
}
