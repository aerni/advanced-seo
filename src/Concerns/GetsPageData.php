<?php

namespace Aerni\AdvancedSeo\Concerns;

use Aerni\AdvancedSeo\Actions\GetDefaultsData;
use Aerni\AdvancedSeo\Blueprints\OnPageSeoBlueprint;
use Illuminate\Support\Collection;
use Statamic\Facades\Blink;
use Statamic\Tags\Context;

trait GetsPageData
{
    public function getPageData(Context $context): Collection
    {
        $data = GetDefaultsData::handle($context);

        return Blink::once(
            "advanced-seo::page::{$data->locale}",
            fn () => $context->intersectByKeys(OnPageSeoBlueprint::make()->data($data)->items())
        );
    }
}
