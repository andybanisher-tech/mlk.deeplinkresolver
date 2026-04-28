<?php

namespace Mlk\DlResolver\Resolver;

class Rule
{
    protected $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public static function getById($id): ?self
    {
        $row = RuleTable::getById($id)->fetch();
        return $row ? new self($row) : null;
    }

    public function getId()
    {
        return $this->data['ID'];
    }
    public function getActive()
    {
        return $this->data['ACTIVE'];
    }
    public function getSort()
    {
        return $this->data['SORT'];
    }
    public function getName()
    {
        return $this->data['NAME'];
    }
    public function getUrlTemplate()
    {
        return $this->data['URL_TEMPLATE'] ?? '';
    }
    public function getObjectType()
    {
        return $this->data['OBJECT_TYPE'];
    }
    public function getIblockId()
    {
        return $this->data['IBLOCK_ID'];
    }
    public function getContentType()
    {
        return $this->data['CONTENT_TYPE'];
    }
    public function getDeeplinkMode()
    {
        return $this->data['DEEPLINK_MODE'] ?? 'auto';
    }
    public function getDeeplinkSource()
    {
        return $this->data['DEEPLINK_SOURCE'];
    }
    public function getDeeplinkCode()
    {
        return $this->data['DEEPLINK_CODE'] ?? '';
    }
    public function getDeeplinkTemplate()
    {
        return $this->data['DEEPLINK_TEMPLATE'] ?? null;
    }
    public function getPlaceholderMapping(): array
    {
        $mapping = $this->data['PLACEHOLDER_MAPPING'] ?? '{}';
        $decoded = json_decode($mapping, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Извлекает значения плейсхолдеров из URL по шаблону.
     * Поддерживает {*} – любая последовательность символов (включая пустую).
     */
    public function extractValuesFromUrl(string $url): ?array
    {
        $template = $this->getUrlTemplate();
        if (empty($template)) {
            return null;
        }

        // Разбиваем на части: текст и плейсхолдеры
        $parts = preg_split('/\{([a-zA-Z0-9_*]+)\}/', $template, -1, PREG_SPLIT_DELIM_CAPTURE);
        $pattern = '';
        $i = 0;
        foreach ($parts as $part) {
            if ($i % 2 == 0) {
                // Обычный текст – экранируем
                $pattern .= preg_quote($part, '/');
            } else {
                // Плейсхолдер
                if ($part === '*') {
                    $pattern .= '(.*)'; // захватывает всё, включая пустую строку
                } else {
                    $pattern .= '(?P<' . $part . '>[^/]+)';
                }
            }
            $i++;
        }
        $pattern = '#^' . $pattern . '$#u';

        if (preg_match($pattern, $url, $matches)) {
            $result = [];
            foreach ($this->getPlaceholderMapping() as $placeholder => $fieldCode) {
                $key = ($placeholder === '*') ? 'star' : $placeholder;
                if (isset($matches[$key])) {
                    $result[$placeholder] = urldecode($matches[$key]);
                } elseif ($placeholder === '*' && isset($matches['star'])) {
                    $result[$placeholder] = urldecode($matches['star']);
                }
            }
            return $result;
        }
        return null;
    }
}
