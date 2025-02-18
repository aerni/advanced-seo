<?php

namespace Aerni\AdvancedSeo\Eloquent;

use Aerni\AdvancedSeo\Contracts\SeoDefaultSet;
use Aerni\AdvancedSeo\Stache\SeoDefaultsRepository as StacheRepository;
use Illuminate\Support\Collection;
use Statamic\Data\DataCollection;
use Statamic\Facades\Blink;

class SeoDefaultsRepository extends StacheRepository
{
    public function find(string $type, string $handle): ?SeoDefaultSet
    {
        return Blink::once("eloquent-advanced-seo-defaults-{$type}-{$handle}", function () use ($type, $handle) {
            $model = app('statamic.eloquent.advanced_seo.model')::query()
                ->whereType($type)
                ->whereHandle($handle)
                ->first();

            return $model
                ? app(SeoDefaultSet::class)->fromModel($model)
                : null;
        });
    }

    public function all(): Collection
    {
        return Blink::once('eloquent-advanced-seo-defaults', function () {
            return app('statamic.eloquent.advanced_seo.model')::all()
                ->map(fn ($model) => app(SeoDefaultSet::class)::fromModel($model));
        });
    }

    public function allOfType(string $type): DataCollection
    {
        return Blink::once("eloquent-advanced-seo-defaults-{$type}", function () use ($type) {
            $models = app('statamic.eloquent.advanced_seo.model')::query()
                ->whereType($type)
                ->get()
                ->map(fn ($model) => app(SeoDefaultSet::class)::fromModel($model));

            return DataCollection::make($models);
        });
    }

    public function save(SeoDefaultSet $set): self
    {
        $model = $set->toModel();

        $model->save();

        $set->model($model->fresh());

        Blink::forget('eloquent-advanced-seo-defaults');
        Blink::forget("eloquent-advanced-seo-defaults-{$set->type()}");
        Blink::forget("eloquent-advanced-seo-defaults-{$set->type()}-{$set->handle()}");

        return $this;
    }

    public function delete(SeoDefaultSet $set): bool
    {
        $set->model()->delete();

        Blink::forget('eloquent-advanced-seo-defaults');
        Blink::forget("eloquent-advanced-seo-defaults-{$set->type()}");
        Blink::forget("eloquent-advanced-seo-defaults-{$set->type()}-{$set->handle()}");

        return true;
    }

    public static function bindings(): array
    {
        return [
            \Aerni\AdvancedSeo\Contracts\SeoDefaultSet::class => \Aerni\AdvancedSeo\Eloquent\SeoDefaultSet::class,
        ];
    }
}
