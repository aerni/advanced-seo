<?php

namespace Aerni\AdvancedSeo\Support;

use Illuminate\Support\Str;
use Statamic\Statamic;

class Helpers
{
    public static function parseLocale(string $locale): string
    {
        $parsed = preg_replace('/\.utf8/i', '', $locale);

        return \WhiteCube\Lingua\Service::create($parsed)->toW3C();
    }

    public static function isDisabled(string $type, string $handle): bool
    {
        return in_array($handle, config("advanced-seo.disabled.{$type}", []));
    }

    public static function isAddonCpRoute(): bool
    {
        return Statamic::isCpRoute() && Str::contains(request()->path(), 'advanced-seo');
    }

    public static function isBlueprintCpRoute(): bool
    {
        return Statamic::isCpRoute() && Str::contains(request()->path(), 'blueprints');
    }

    public static function isEntryEditRoute(): bool
    {
        return request()->route()?->getName() === 'statamic.cp.collections.entries.edit';
    }

    /**
     * Return true if we're on any custom route other than the defined exceptions below.
     * This includes any routes defined with `Route::get()` and `Route::statamic()`.
     */
    public static function isCustomRoute(): bool
    {
        return ! in_array(request()->route()?->getName(), ['statamic.site', 'social_images.show']);
    }
}
