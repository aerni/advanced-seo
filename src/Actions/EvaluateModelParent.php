<?php

namespace Aerni\AdvancedSeo\Actions;

use Aerni\AdvancedSeo\Contracts\SeoDefaultSet;
use Illuminate\Support\Str;
use Statamic\Contracts\Entries\Collection;
use Statamic\Contracts\Entries\Entry;
use Statamic\Contracts\Taxonomies\Taxonomy;
use Statamic\Contracts\Taxonomies\Term;
use Statamic\Events\EntryBlueprintFound;
use Statamic\Events\TermBlueprintFound;
use Statamic\Facades\Collection as CollectionApi;
use Statamic\Facades\Taxonomy as TaxonomyApi;
use Statamic\Fields\Value;
use Statamic\Stache\Query\TermQueryBuilder;
use Statamic\Tags\Context;

class EvaluateModelParent
{
    public static function handle(mixed $data): mixed
    {
        return match (true) {
            ($data instanceof SeoDefaultSet) => $data,
            ($data instanceof Collection) => $data,
            ($data instanceof Entry) => $data->collection(),
            ($data instanceof EntryBlueprintFound) => CollectionApi::find(Str::after($data->blueprint->namespace(), '.')),
            ($data instanceof Context && $data->get('collection') instanceof Value) => $data->get('collection')->value(),
            ($data instanceof Taxonomy) => $data,
            ($data instanceof Term) => $data->taxonomy(),
            ($data instanceof TermBlueprintFound) => TaxonomyApi::find(Str::after($data->blueprint->namespace(), '.')),
            ($data instanceof Context && $data->get('taxonomy') instanceof Value) => $data->get('taxonomy')->value(),
            ($data instanceof Context && $data->get('terms') instanceof TermQueryBuilder) => TaxonomyApi::find($data->get('handle')->value()),
            default => null
        };
    }
}
