<?php

namespace Aerni\AdvancedSeo\Actions;

use Aerni\AdvancedSeo\Contracts\SeoDefaultSet;
use Aerni\AdvancedSeo\Data\DefaultsData;
use Illuminate\Support\Str;
use Statamic\Contracts\Entries\Collection;
use Statamic\Contracts\Entries\Entry;
use Statamic\Contracts\Taxonomies\Taxonomy;
use Statamic\Contracts\Taxonomies\Term;
use Statamic\Events\EntryBlueprintFound;
use Statamic\Events\TermBlueprintFound;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Facades\Site;
use Statamic\Statamic;
use Statamic\Tags\Context;

class EvaluateModelLocale
{
    public static function handle(mixed $model): ?string
    {
        return match (true) {
            ($model instanceof SeoDefaultSet) => self::seoDefaultSet($model),
            ($model instanceof Collection) => self::collection(),
            ($model instanceof Entry) => $model->locale(),
            ($model instanceof EntryBlueprintFound) => self::entryBlueprintFound(),
            ($model instanceof Taxonomy) => self::taxonomy(),
            ($model instanceof Term) => self::term($model),
            ($model instanceof TermBlueprintFound) => self::termBlueprintFound(),
            ($model instanceof Context) => $model->get('site')?->handle() ?? Site::current()->handle(),
            ($model instanceof DefaultsData) => $model->locale,
            default => null
        };
    }

    protected static function seoDefaultSet(SeoDefaultSet $model): string
    {
        return request()->get('site') ?? $model->selectedSite();
    }

    protected static function collection(): string
    {
        // If we're not in the CP, simply return the current locale.
        if (! Statamic::isCpRoute()) {
            return Site::current()->handle();
        }

        // If we're creating an entry, get the locale from the path.
        if (Str::contains(request()->path(), 'create')) {
            return basename(request()->path());
        }

        // If we're editing an existing entry, get the locale of the entry.
        if ($entry = EntryFacade::find(basename(request()->path()))) {
            return $entry->locale();
        }

        // Return the selected site if no locale has been evaluated so far.
        return Site::selected()->handle();
    }

    protected static function entryBlueprintFound(): string
    {
        return Statamic::isCpRoute()
            ? basename(request()->path())
            : Site::current()->handle();
    }

    protected static function taxonomy(): string
    {
        return Statamic::isCpRoute()
            ? basename(request()->path())
            : Site::current()->handle();
    }

    protected static function term(Term $model): string
    {
        return Statamic::isCpRoute()
            ? basename(request()->path())
            : $model->locale();
    }

    protected static function termBlueprintFound(): string
    {
        return Statamic::isCpRoute()
            ? basename(request()->path())
            : Site::current()->handle();
    }
}
