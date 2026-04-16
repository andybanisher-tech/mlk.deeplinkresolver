<?php
namespace Mlk\AppDeepLinkResolver\Resolver;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields;
use Bitrix\Main\ORM\Fields\Validators\LengthValidator;

class RuleTable extends DataManager
{
    public static function getTableName()
    {
        return 'mlk_appdeeplink_resolver_rule';
    }

    public static function getMap()
    {
        return [
            (new Fields\IntegerField('ID'))
                ->configurePrimary()
                ->configureAutocomplete(),
            (new Fields\StringField('ACTIVE'))
                ->configureDefaultValue('Y')
                ->configureSize(1),
            (new Fields\IntegerField('SORT'))
                ->configureDefaultValue(500),
            (new Fields\StringField('NAME'))
                ->configureRequired(true)
                ->addValidator(new LengthValidator(1, 255)),
            (new Fields\TextField('URL_TEMPLATE'))
                ->configureRequired(true),
            (new Fields\EnumField('OBJECT_TYPE'))
                ->configureValues(['ELEMENT', 'SECTION'])
                ->configureDefaultValue('ELEMENT'),
            (new Fields\IntegerField('IBLOCK_ID'))
                ->configureRequired(true),
            (new Fields\StringField('CONTENT_TYPE'))
                ->configureRequired(true)
                ->configureSize(255),
            (new Fields\EnumField('DEEPLINK_MODE'))
                ->configureValues(['auto', 'manual'])
                ->configureDefaultValue('auto'),
            (new Fields\EnumField('DEEPLINK_SOURCE'))
                ->configureValues(['FIELD', 'PROPERTY'])
                ->configureDefaultValue('FIELD'),
            (new Fields\StringField('DEEPLINK_CODE'))
                ->configureRequired(false)
                ->configureSize(255),
            (new Fields\TextField('DEEPLINK_TEMPLATE'))
                ->configureNullable(),
            (new Fields\TextField('PLACEHOLDER_MAPPING'))
                ->configureNullable(),
            (new Fields\DatetimeField('CREATED_AT'))
                ->configureDefaultValue(new \Bitrix\Main\Type\DateTime()),
            (new Fields\DatetimeField('UPDATED_AT'))
                ->configureDefaultValue(new \Bitrix\Main\Type\DateTime()),
        ];
    }
}