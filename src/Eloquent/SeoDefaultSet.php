<?php

namespace Aerni\AdvancedSeo\Eloquent;

use Aerni\AdvancedSeo\Contracts\SeoDefaultSet as SeoDefaultSetContract;
use Aerni\AdvancedSeo\Data\SeoDefaultSet as StacheSeoDefaultSet;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Statamic\Facades\Site;

class SeoDefaultSet extends StacheSeoDefaultSet
{
    protected Model $model;

    public static function fromModel(Model $model): SeoDefaultSetContract
    {
        $seoDefaultSet = (new static)
            ->type($model->type)
            ->handle($model->handle)
            ->model($model);

        if (! Site::multiEnabled()) {
            $localization = $seoDefaultSet
                ->makeLocalization(Site::default()->handle())
                ->merge($model->data);

            return $seoDefaultSet->addLocalization($localization);
        }

        $model->data->each(function ($data, $site) use ($seoDefaultSet) {
            $localization = $seoDefaultSet
                ->makeLocalization($site)
                ->merge(Arr::except($data, 'origin'))
                ->origin(Arr::get($data, 'origin'));

            $seoDefaultSet->addLocalization($localization);
        });

        return $seoDefaultSet;
    }

    public function toModel(): Model
    {
        return self::makeModelFromContract($this);
    }

    public static function makeModelFromContract(SeoDefaultSetContract $source): Model
    {
        $class = app('statamic.eloquent.advanced_seo.model');

        $data = $source->localizations()
            ->intersectByKeys($source->sites()->flip()) /* Only keep data of configured sites. */
            ->map->fileData()
            ->when(! Site::multiEnabled(), fn ($data) => $data->first()); /* Don't key data by site when not using multi-site. */

        return $class::firstOrNew([
            'type' => $source->type(),
            'handle' => $source->handle(),
        ])->fill([
            'data' => $data,
        ]);
    }

    public function model($model = null)
    {
        if (func_num_args() === 0) {
            return $this->model;
        }

        $this->model = $model;

        return $this;
    }
}
