<?php

declare(strict_types=1);

namespace App\Http;

use Symfony\Component\HttpFoundation\Request;

final class Preferences
{
    public const string LANGUAGE_COOKIE = 'faultline_lang';
    public const string THEME_COOKIE = 'faultline_theme';
    public const array LANGUAGES = ['en', 'pl'];
    public const array THEMES = ['light', 'dark'];

    public static function language(Request $request): ?string
    {
        $cookie = $request->cookies->get(self::LANGUAGE_COOKIE);
        if (\is_string($cookie) && \in_array($cookie, self::LANGUAGES, true)) {
            return $cookie;
        }

        return $request->getPreferredLanguage(self::LANGUAGES);
    }

    public static function theme(Request $request): ?string
    {
        $cookie = $request->cookies->get(self::THEME_COOKIE);

        return \is_string($cookie) && \in_array($cookie, self::THEMES, true) ? $cookie : null;
    }
}
