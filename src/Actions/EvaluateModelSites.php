<?php

namespace Aerni\AdvancedSeo\Actions;

use Aerni\AdvancedSeo\Contracts\SeoDefaultSet;
use Aerni\AdvancedSeo\Data\DefaultsData;
use Illuminate\Support\Collection as LaravelCollection;
use Statamic\Contracts\Entries\Collection;
use Statamic\Contracts\Taxonomies\Taxonomy;

class EvaluateModelSites
{
    public static function handle(mixed $model): ?LaravelCollection
    {
        return match (true) {
            ($model instanceof SeoDefaultSet) => $model->sites(),
            ($model instanceof Collection) => $model->sites(),
            ($model instanceof Taxonomy) => $model->sites(),
            ($model instanceof DefaultsData) => $model->sites,
            default => null
        };
    }
}
