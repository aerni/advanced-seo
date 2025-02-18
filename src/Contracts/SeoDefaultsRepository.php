<?php

namespace Aerni\AdvancedSeo\Contracts;

use Illuminate\Support\Collection;
use Statamic\Data\DataCollection;

interface SeoDefaultsRepository
{
    public function make(): SeoDefaultSet;

    public function find(string $type, string $handle): ?SeoDefaultSet;

    public function findOrMake(string $type, string $handle): SeoDefaultSet;

    public function all(): Collection;

    public function allOfType(string $type): DataCollection;

    public function save(SeoDefaultSet $set): self;

    public function delete(SeoDefaultSet $set): bool;
}
