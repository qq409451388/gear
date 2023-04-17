<?php
class AnnoationRule implements EzHelper
{
    private function __construct() {
    }

    /**
     * @param string $document
     * @param int $at {@see AnnoElementType}
     * @param string $annoName implements Anno
     * @return AnnoItem
     */
    public static function searchCertainlyNormalAnnoation($document, $at, $annoName) {
        if (empty($annoName)) {
            return null;
        }
        $s= "/(.*)@$annoName\(\s?\'?\"?(?<content>[\/a-zA-Z0-9\#\{\}\*]+)\'?\"?\s?\)/";
        preg_match($s, $document, $matched);
        if (empty($matched['content'])) {
            Logger::warn("[Gear] Empty Content AnnoInfo:{} ", $annoName);
            return null;
        }
        $content = $matched['content'];
        if (!is_subclass_of($annoName, Anno::class)) {
            Logger::warn("[Gear] UnExpected AnnoInfo:{} ({})", $annoName, $content);
            return null;
        }
        return AnnoItem::create($annoName, $content, $at);
    }

    /**
     * @param string $document
     * @param int $at {@see AnnoElementType}
     * @return array<AnnoItem>
     */
    public static function searchNormalAnnoation($document, $at) {
        $list = [];
        /**
         * 注解第一种类型，参数为普通字符串
         * @example: @XXX("qqq") 或 @YYY('qqq')
         */
        $s= "/(.*)@(?<annoName>[a-zA-Z0-9]+)\(\s?\'?\"?(?<content>[\/a-zA-Z0-9\#\{\}\*]+)\'?\"?\s?\)/";
        preg_match_all($s, $document, $matchedes, 2);
        foreach ($matchedes as $matched) {
            if (empty($matched['annoName'])) {
                continue;
            }
            $annoName = $matched['annoName'];
            if (empty($matched['content'])) {
                Logger::warn("[Gear] Empty Content AnnoInfo:{} ", $annoName);
                continue;
            }
            $content = $matched['content'];
            if (!is_subclass_of($annoName, Anno::class)) {
                Logger::warn("[Gear] UnExpected AnnoInfo:{} ({})", $annoName, $content);
                continue;
            }
            $list[] = AnnoItem::create($annoName, $content, $at);
        }
        return $list;
    }

    /**
     * @param string $document
     * @param int $at {@see AnnoElementType}
     * @return array<AnnoItem>
     */
    public static function searchJsonAnnoation($document, $at) {
        $list = [];
        /**
         * 注解第二种类型，参数为JSON字符串
         * @example: @XXX({"a":"a", "b":"b"})
         */
        $s = "/(.*)@(?<annoName>[a-zA-Z0-9]+)\(\{(?<content>(.*)+)\}\)/";
        preg_match_all($s, $document, $matchedes, 2);
        foreach ($matchedes as $matched) {
            if (empty($matched['annoName'])) {
                continue;
            }
            $annoName = $matched['annoName'];
            if (empty($matched['content'])) {
                Logger::warn("[Gear] Empty Content AnnoInfo:{} ", $annoName);
                continue;
            }
            $content = "{".$matched['content']."}";
            if (!EzDataUtils::isJson($content)) {
                Logger::warn("[Gear] Invalid Content AnnoInfo:{}, Content:{}", $annoName, $content);
                continue;
            }
            if (!is_subclass_of($annoName, Anno::class)) {
                Logger::warn("[Gear] UnExpected AnnoInfo:{} ({})", $annoName, $content);
                continue;
            }
            $content = EzCollectionUtils::decodeJson($content);
            $list[] = AnnoItem::createComplex($annoName, $content, $at);
        }
        return $list;
    }

    /**
     * @param string $document
     * @param int $at {@see AnnoElementType}
     * @return array<AnnoItem>
     */
    public static function searchLiteAnnoation($document, $at) {
        $list = [];
        /**
         * 注解第三种类型，无任何参数
         * @example: @XXX
         */
        $s = "/(.*)@(?<annoName>[a-zA-Z0-9]+)[\f\n\r\t\v]+/";
        preg_match_all($s, $document, $matchedes, 2);
        foreach ($matchedes as $matched) {
            if (empty($matched['annoName'])) {
                continue;
            }
            $annoName = $matched['annoName'];
            if (!is_subclass_of($annoName, Anno::class)) {
                Logger::warn("[Gear] UnExpected AnnoInfo:{}", $annoName);
                continue;
            }
            $list[] = AnnoItem::createComplex($annoName, null, $at);
        }
        return $list;
    }

    /**
     * 冲突！和searchNormal
     * @param string $document
     * @param int $at {@see AnnoElementType}
     * @return array<AnnoItem>
     */
    public static function searchRelationshipAnnoation($document, $at) {
        $list = [];
        /**
         * 注解第四种类型，参数为箭头映射
         * @example: @XXX(a=>1, b=>2)
         */
        $s = "/\s?@(?<annoName>[a-zA-Z]+)\s?\((?<content>[0-9a-zA-Z\s=>\"\',]+)\)/";
        preg_match_all($s, $document, $matchedes, 2);
        $s2 = "/(?<key>[a-zA-Z]+)\s?=>\s?\"?(?<value>[0-9a-zA-Z\s]+)\"?/";
        foreach ($matchedes as $matched) {
            if (empty($matched['annoName'])) {
                continue;
            }
            $annoName = $matched['annoName'];
            if (!is_subclass_of($annoName, Anno::class)) {
                Logger::warn("[Gear] UnExpected AnnoInfo:{}", $annoName);
                continue;
            }
            preg_match_all($s2, $matched['content'], $matchedes2, 2);
            if (empty($matchedes2)) {
                continue;
            }
            $arr = array_combine(array_column($matchedes2, "key"), array_column($matchedes2, "value"));
            $list[] = AnnoItem::createComplex($annoName, $arr, $at);
        }
        return $list;
    }

    /**
     * @param string $document
     * @param int $at {@see AnnoElementType}
     * @return array<AnnoItem>
     */
    public static function searchAnnoationFromDocument($document, $at) {
        return array_merge(
            self::searchNormalAnnoation($document, $at),
            self::searchJsonAnnoation($document, $at),
            self::searchLiteAnnoation($document, $at),
            self::searchRelationshipAnnoation($document, $at)
        );
    }

}
