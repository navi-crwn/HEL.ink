<?php

namespace Hop\Ui;

/**
 * The one list of languages every HOP app ships: nine, in the order the menus show them.
 * Each entry is the native name (menu, left) and the short label (menu, right).
 * Right-to-left languages are not part of the set.
 */
final class Locales
{
    public const DEFAULT = 'en';

    /** @var array<string, array{name: string, short: string}> */
    public const ALL = [
        'en' => ['name' => 'English', 'short' => 'EN'],
        'zh-CN' => ['name' => '简体中文', 'short' => '中文'],
        'de' => ['name' => 'Deutsch', 'short' => 'DE'],
        'id' => ['name' => 'Bahasa Indonesia', 'short' => 'ID'],
        'ja' => ['name' => '日本語', 'short' => '日本語'],
        'pt-BR' => ['name' => 'Português (Brasil)', 'short' => 'PT'],
        'es' => ['name' => 'Español', 'short' => 'ES'],
        'fr' => ['name' => 'Français', 'short' => 'FR'],
        'ru' => ['name' => 'Русский', 'short' => 'RU'],
    ];

    /** Codes stored before the set was fixed (cookies, profiles) and what they mean now. */
    private const LEGACY = ['zh' => 'zh-CN', 'pt' => 'pt-BR'];

    /** @return list<string> */
    public static function codes(): array
    {
        return array_keys(self::ALL);
    }

    /** @return array<string, string> code => native name */
    public static function names(): array
    {
        return array_map(fn (array $l) => $l['name'], self::ALL);
    }

    public static function short(string $code): string
    {
        return self::ALL[$code]['short'] ?? strtoupper($code);
    }

    /** A supported code for $value, or null: legacy `zh` and `pt` are mapped, anything else (such as `ar`) is not supported. */
    public static function resolve(?string $value): ?string
    {
        $value = trim((string) $value);
        if (isset(self::ALL[$value])) {
            return $value;
        }
        $value = self::LEGACY[strtolower($value)] ?? $value;
        foreach (self::codes() as $code) {
            if (strcasecmp($code, $value) === 0) {
                return $code;
            }
        }

        return null;
    }

    /** The language to use for a stored or requested value: English when it is empty, unknown or retired. */
    public static function normalize(?string $value): string
    {
        return self::resolve($value) ?? self::DEFAULT;
    }
}
