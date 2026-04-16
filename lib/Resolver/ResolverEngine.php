<?php

namespace Mlk\DeepLinkResolver\Resolver;

use Bitrix\Main\Loader;
use CIBlockElement;
use CIBlockSection;

class ResolverEngine
{
	protected $url;
	protected $matchedRule;
	protected $contentType;
	protected $deeplink;
	protected $debug = [];

	public function resolve(string $url, bool $debug = false): array
	{
		$this->url = $url;
		$this->matchedRule = null;
		$this->contentType = null;
		$this->deeplink = null;
		$this->debug = [];

		if ($debug) {
			$this->debug['request_url'] = $url;
			$this->debug['rules_checked'] = [];
		}

		if (!Loader::includeModule('iblock')) {
			return [
				'success' => false,
				'error' => 'Iblock module not loaded',
				'debug' => $debug ? $this->debug : null
			];
		}

		$rules = RuleTable::getList([
			'filter' => ['=ACTIVE' => 'Y'],
			'order' => ['SORT' => 'ASC', 'ID' => 'ASC']
		])->fetchCollection();

		if ($debug) {
			$this->debug['total_active_rules'] = $rules->count();
		}

		foreach ($rules as $ruleEntity) {
			$rule = new Rule($ruleEntity->collectValues());
			$ruleData = [
				'id' => $rule->getId(),
				'name' => $rule->getName(),
				'template' => $rule->getUrlTemplate(),
				'mapping' => $rule->getPlaceholderMapping(),
				'iblock_id' => $rule->getIblockId(),
				'object_type' => $rule->getObjectType()
			];

			if ($debug) {
				$this->debug['rules_checked'][] = $ruleData;
			}

			$values = $rule->extractValuesFromUrl($this->url);
			if ($values === null) {
				if ($debug) {
					$idx = count($this->debug['rules_checked']) - 1;
					$this->debug['rules_checked'][$idx]['matched'] = false;
					$this->debug['rules_checked'][$idx]['extracted'] = null;
				}
				continue;
			}

			if ($debug) {
				$idx = count($this->debug['rules_checked']) - 1;
				$this->debug['rules_checked'][$idx]['matched'] = true;
				$this->debug['rules_checked'][$idx]['extracted'] = $values;
			}

			$this->matchedRule = $rule;
			$this->contentType = $rule->getContentType();

			$deeplinkResult = $this->fetchDeeplink($rule, $values, $debug);

			if ($deeplinkResult !== null) {
				$this->deeplink = $deeplinkResult;
				break;
			} elseif ($debug) {
				$idx = count($this->debug['rules_checked']) - 1;
				$this->debug['rules_checked'][$idx]['element_found'] = false;
				$this->debug['rules_checked'][$idx]['deeplink'] = null;
			}
		}

		if (!$this->matchedRule || $this->deeplink === null) {
			return [
				'success' => false,
				'error' => 'No matching rule or deeplink not found',
				'debug' => $debug ? $this->debug : null
			];
		}

		$result = [
			'success' => true,
			'content_type' => $this->contentType,
			'deeplink' => $this->deeplink
		];

		if ($debug) {
			$result['debug'] = $this->debug;
		}

		return $result;
	}

	protected function fetchDeeplink(Rule $rule, array $values, bool $debug): ?string
	{
		$mode = $rule->getDeeplinkMode();
		$template = $rule->getDeeplinkTemplate();
		$iblockId = $rule->getIblockId();
		$objectType = $rule->getObjectType();
		$mapping = $rule->getPlaceholderMapping();

		$filter = ['IBLOCK_ID' => $iblockId];
		foreach ($mapping as $placeholder => $fieldCode) {
			$value = $values[$placeholder] ?? null;
			if ($value === null) {
				continue;
			}
			if (strpos($fieldCode, 'PROPERTY:') === 0) {
				$propCode = substr($fieldCode, 9);
				$filter['PROPERTY_' . $propCode] = $value;
			} else {
				$fieldName = substr($fieldCode, 6);
				$filter['=' . $fieldName] = $value;
			}
		}

		if ($debug) {
			$this->debug['last_filter'] = $filter;
		}

		$objectId = null;
		if ($objectType === 'ELEMENT') {
			$element = CIBlockElement::GetList([], $filter, false, ['nTopCount' => 1], ['ID'])->Fetch();
			if ($element) {
				$objectId = $element['ID'];
			}
		} else {
			$section = CIBlockSection::GetList([], $filter, false, ['ID'], ['nTopCount' => 1])->Fetch();
			if ($section) {
				$objectId = $section['ID'];
			}
		}

		if ($debug) {
			if ($objectId) {
				$this->debug['last_object_id'] = $objectId;
			} else {
				$this->debug['object_not_found'] = true;
				$this->debug['object_not_found_reason'] = ($objectType === 'ELEMENT') 
					? 'No element matches filter' 
					: 'No section matches filter';
			}
		}

		$sourceValue = null;
		if ($objectId) {
			$sourceType = $rule->getDeeplinkSource();
			$code = $rule->getDeeplinkCode();
			if ($sourceType === 'FIELD') {
				if ($objectType === 'ELEMENT') {
					$res = CIBlockElement::GetList([], ['ID' => $objectId], false, false, ['ID', $code])->Fetch();
					$sourceValue = $res[$code] ?? null;
				} else {
					$res = CIBlockSection::GetList([], ['ID' => $objectId], false, ['ID', $code])->Fetch();
					$sourceValue = $res[$code] ?? null;
				}
			} else {
				if ($objectType === 'ELEMENT') {
					$prop = CIBlockElement::GetProperty($iblockId, $objectId, [], ['CODE' => $code])->Fetch();
					$sourceValue = $prop ? (string)$prop['VALUE'] : null;
				} else {
					$dbRes = CIBlockSection::GetList([], ['ID' => $objectId], false, ['UF_' . $code]);
					if ($sect = $dbRes->Fetch()) {
						$sourceValue = $sect['UF_' . $code] ?? null;
					}
				}
			}
		}

		if ($mode === 'manual' && !empty($template)) {
			$replace = $values;
			$replace['element_id'] = $objectId;
			$replace['section_id'] = $objectId;
			$replace['iblock_id'] = $iblockId;
			if ($sourceValue !== null) {
				$replace['source'] = $sourceValue;
			}

			$deeplink = preg_replace_callback('/\{([a-zA-Z0-9_]+)\}/', function($m) use ($replace) {
				$key = $m[1];
				return isset($replace[$key]) ? (string)$replace[$key] : $m[0];
			}, $template);

			if ($debug) {
				$this->debug['deeplink_mode'] = 'manual';
				$this->debug['deeplink_template'] = $template;
				$this->debug['deeplink_placeholders'] = $replace;
				$this->debug['deeplink_generated'] = $deeplink;
			}
			return $deeplink;
		}

		if (!$objectId || $sourceValue === null) {
			return null;
		}

		if ($debug) {
			$this->debug['deeplink_mode'] = 'auto';
			$this->debug['deeplink_source_value'] = $sourceValue;
		}
		return $sourceValue;
	}
}
