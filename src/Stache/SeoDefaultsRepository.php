<?php

namespace Aerni\AdvancedSeo\Stache;

use Aerni\AdvancedSeo\Contracts\SeoDefaultSet;
use Aerni\AdvancedSeo\Contracts\SeoDefaultsRepository as Contract;
use Illuminate\Support\Collection;
use Statamic\Data\DataCollection;
use Statamic\Stache\Stache;
use Statamic\Stache\Stores\Store;

class SeoDefaultsRepository implements Contract
{
    protected Stache $stache;

    protected Store $store;

    public function __construct(Stache $stache)
    {
        $this->stache = $stache;
        $this->store = $stache->store('seo');
    }

    public function make(): SeoDefaultSet
    {
        return app(SeoDefaultSet::class);
    }

    public function find(string $type, string $handle): ?SeoDefaultSet
    {
        return $this->store->store($type)->getItem("{$type}::{$handle}");
    }

    public function findOrMake(string $type, string $handle): SeoDefaultSet
    {
        return $this->find($type, $handle) ?? $this->make()->type($type)->handle($handle);
    }

    public function all(): Collection
    {
        return $this->store->discoverStores()->map(function ($store, $type) {
            return $this->allOfType($type);
        });
    }

    public function allOfType(string $type): DataCollection
    {
        $keys = $this->store->store($type)->paths()->keys();

        return DataCollection::make($this->store->store($type)->getItems($keys));
    }

    public function save(SeoDefaultSet $set): self
    {
        $this->store->store($set->type())->save($set);

        return $this;
    }

    public function delete(SeoDefaultSet $set): bool
    {
        $this->store->store($set->type())->delete($set);

        return true;
    }

    public static function bindings(): array
    {
        return [
            \Aerni\AdvancedSeo\Contracts\SeoDefaultSet::class => \Aerni\AdvancedSeo\Data\SeoDefaultSet::class,
        ];
    }
}
