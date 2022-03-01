<?php

namespace Aerni\AdvancedSeo\Actions;

use Aerni\AdvancedSeo\Data\DefaultsData;
use Statamic\Contracts\Entries\Collection;
use Statamic\Contracts\Taxonomies\Taxonomy;

class EvaluateModelType
{
    public static function handle(mixed $model): ?string
    {
        return match (true) {
            ($model instanceof Collection) => 'collections',
            ($model instanceof Taxonomy) => 'taxonomies',
            ($model instanceof DefaultsData) => $model->type,
            default => null
        };
    }
}
