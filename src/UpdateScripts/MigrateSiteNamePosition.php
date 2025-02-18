<?php

namespace Aerni\AdvancedSeo\UpdateScripts;

use Aerni\AdvancedSeo\Facades\Seo;
use Aerni\AdvancedSeo\Models\Defaults;
use Illuminate\Support\Arr;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\Site;
use Statamic\Facades\Taxonomy;
use Statamic\Facades\Term;
use Statamic\UpdateScripts\UpdateScript;

class MigrateSiteNamePosition extends UpdateScript
{
    public function shouldUpdate($newVersion, $oldVersion)
    {
        return $this->isUpdatingTo('1.3.0');
    }

    public function update()
    {
        // The mapping of old and new values.
        $mapping = [
            'before' => 'end',
            'after' => 'start',
        ];

        // Get all localizations and make sure they exist.
        $siteDefaults = Seo::find('site', 'general')
            ?->ensureLocalizations(Site::all())
            ->localizations();

        // Get the title positions from each localization.
        $titlePositions = $siteDefaults
            ->map(fn ($data) => $data->get('title_position'))
            ->filter()
            ->map(fn ($value) => Arr::get($mapping, $value));

        // Set the site name position for all collection defaults.
        Defaults::enabledInType('collections')
            ->map(fn ($default) => Seo::findOrMake($default['type'], $default['handle']))
            ->map(function ($default) {
                $sites = Collection::find($default->handle())->sites();

                return $default->ensureLocalizations($sites);
            })
            ->each(function ($default) use ($titlePositions) {
                $titlePositions->each(function ($value, $site) use ($default) {
                    $default->in($site)?->set('seo_site_name_position', $value)->save();
                });
            });

        // Set the site name position for all taxonomy defaults.
        Defaults::enabledInType('taxonomies')
            ->map(fn ($default) => Seo::findOrMake($default['type'], $default['handle']))
            ->map(function ($default) {
                $sites = Taxonomy::find($default->handle())->sites();

                return $default->ensureLocalizations($sites);
            })
            ->each(function ($default) use ($titlePositions) {
                $titlePositions->each(function ($value, $site) use ($default) {
                    $default->in($site)?->set('seo_site_name_position', $value)->save();
                });
            });

        // Set the site name position on entries.
        Entry::query()
            ->whereNull('origin') // Only set on default site.
            ->whereNotNull('seo_title')
            ->get()
            ->each(fn ($entry) => $entry->set('seo_site_name_position', '@default')->saveQuietly());

        // Set the site name position on terms.
        Term::all()
            ->filter(fn ($term) => ! $term->hasOrigin()) // Only set on default site.
            ->filter(fn ($term) => $term->has('seo_title'))
            ->each(fn ($term) => $term->set('seo_site_name_position', '@default')->save());

        // Remove the old title position from the site defaults.
        $siteDefaults
            ->filter(fn ($default) => $default->has('title_position')) // Prevent saving of defaults with no title position
            ->each(fn ($default) => $default->remove('title_position')->save());

        $this->console()->info("Successfully migrated 'title_position' to 'site_name_position'.");
    }
}
