<?php

namespace Aerni\AdvancedSeo\Data;

use Aerni\AdvancedSeo\Concerns\HasDefaultsData;
use Aerni\AdvancedSeo\Contracts\SeoDefaultSet as Contract;
use Aerni\AdvancedSeo\Models\Defaults;
use Illuminate\Support\Collection;
use Statamic\Data\ExistsAsFile;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Site;
use Statamic\Facades\Stache;
use Statamic\Facades\Taxonomy;
use Statamic\Fields\Blueprint;
use Statamic\Support\Arr;
use Statamic\Support\Str;
use Statamic\Support\Traits\FluentlyGetsAndSets;

class SeoDefaultSet implements Contract
{
    use ExistsAsFile;
    use FluentlyGetsAndSets;
    use HasDefaultsData;

    protected string $handle;

    protected string $type;

    protected array $localizations;

    public function id(): string
    {
        return "{$this->type()}::{$this->handle()}";
    }

    public function handle($handle = null)
    {
        return $this->fluentlyGetOrSet('handle')->args(func_get_args());
    }

    public function type($type = null)
    {
        return $this->fluentlyGetOrSet('type')->args(func_get_args());
    }

    public function localizations(): Collection
    {
        return collect($this->localizations);
    }

    public function sites(): Collection
    {
        $allSites = Site::all()->keys();

        if ($parent = $this->parent()) {
            // Only return sites from the parent that are configured in Statamic's sites config
            return $allSites->filter(fn ($site) => $parent->sites()->contains($site));
        }

        return $allSites;
    }

    public function availableInSite(string $site): bool
    {
        return $this->sites()->contains($site);
    }

    public function defaultSite(): string
    {
        return $this->sites()->first();
    }

    public function selectedSite(): string
    {
        $selectedSite = Site::selected()->handle();

        return $this->sites()->contains($selectedSite)
            ? $selectedSite
            : $this->defaultSite();
    }

    public function title(): string
    {
        return Str::slugToTitle($this->handle());
    }

    public function path(): string
    {
        return vsprintf('%s/%s.yaml', [
            Stache::store('seo')->store($this->type())->directory(),
            $this->handle(),
        ]);
    }

    public function fileData(): array
    {
        $data = [
            'title' => $this->title(),
        ];

        if (! Site::multiEnabled()) {
            $data['data'] = Arr::removeNullValues(
                $this->inDefaultSite()->data()->all()
            );
        }

        return $data;
    }

    public function makeLocalization(string $site): SeoVariables
    {
        return (new SeoVariables)
            ->seoSet($this)
            ->locale($site);
    }

    public function createLocalizations(Collection $sites): self
    {
        return $this->ensureLocalizations($sites)->save();
    }

    public function createOrDeleteLocalizations(Collection $sites): self
    {
        return $this
            ->ensureLocalizations($sites)
            ->removeLocalizations($sites)
            ->save();
    }

    /**
     * TODO: We can probably refactor this to not accept a sites array but get the sites from the parent() instead.
     * But this might only work for collection and taxonomy defaults. What to do with site defaults that don't have a parent?
     */
    public function ensureLocalizations(Collection $sites): self
    {
        // We only want to handle sites that are configured in Statamic's sites config.
        $sites = $sites->intersect(Site::all()->keys());

        // Make a localization for each site if it doesn't already exist.
        $sites->each(function ($site) {
            $this->in($site) ?? $this->addLocalization($this->makeLocalization($site));
        });

        // Determine the origin and set the default data for each localization based on the provided sites.
        $this->localizations()->each(fn ($item) => $item->determineOrigin($sites)->withDefaultData());

        return $this;
    }

    public function removeLocalizations(Collection $sites): self
    {
        $localizationsToDelete = $this->localizations()->map->locale()->diff($sites);

        $localizationsToDelete->each(function ($localization) {
            $this->removeLocalization($this->localizations()->get($localization));
        });

        return $this;
    }

    public function addLocalization(SeoVariables $localization): self
    {
        $localization->seoSet($this);

        $this->localizations[$localization->locale()] = $localization;

        return $this;
    }

    public function removeLocalization(SeoVariables $localization): self
    {
        unset($this->localizations[$localization->locale()]);

        return $this;
    }

    public function in($locale): ?SeoVariables
    {
        return $this->localizations[$locale] ?? null;
    }

    public function inSelectedSite(): ?SeoVariables
    {
        return $this->in(Site::selected()->handle());
    }

    public function inCurrentSite(): ?SeoVariables
    {
        return $this->in(Site::current()->handle());
    }

    public function inDefaultSite(): ?SeoVariables
    {
        return $this->in($this->defaultSite());
    }

    public function existsIn(string $locale): bool
    {
        return $this->in($locale) !== null;
    }

    public function blueprint(): Blueprint
    {
        $blueprint = Defaults::blueprint("{$this->type}::{$this->handle}");

        return resolve($blueprint)->make()
            ->data($this->defaultsData())
            ->get();
    }

    public function parent(): mixed
    {
        return match (true) {
            ($this->type() === 'collections') => CollectionFacade::findByHandle($this->handle()),
            ($this->type() === 'taxonomies') => Taxonomy::findByHandle($this->handle()),
            default => null,
        };
    }

    public function isEnabled(): bool
    {
        return Defaults::isEnabled("{$this->type()}::{$this->handle()}");
    }

    public function save(): self
    {
        \Aerni\AdvancedSeo\Facades\Seo::save($this);

        return $this;
    }

    public function delete(): bool
    {
        \Aerni\AdvancedSeo\Facades\Seo::delete($this);

        return true;
    }

    public static function __callStatic($method, $parameters)
    {
        return \Aerni\AdvancedSeo\Facades\Seo::{$method}(...$parameters);
    }
}
