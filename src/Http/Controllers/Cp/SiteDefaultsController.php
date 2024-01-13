<?php

namespace Aerni\AdvancedSeo\Http\Controllers\Cp;

use Aerni\AdvancedSeo\Data\SeoDefaultSet;
use Aerni\AdvancedSeo\Data\SeoVariables;
use Aerni\AdvancedSeo\Events\SeoDefaultSetSaved;
use Aerni\AdvancedSeo\Facades\Seo;
use Aerni\AdvancedSeo\Models\Defaults;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Statamic\CP\Breadcrumbs;
use Statamic\Exceptions\NotFoundHttpException;
use Statamic\Facades\Site;
use Statamic\Facades\User;

class SiteDefaultsController extends BaseDefaultsController
{
    public function index(): View
    {
        $hasDefaultsForSite = Defaults::enabledInType('site')
            ->filter(fn ($default) => $default['set']->sites()->contains(Site::selected()->handle()))
            ->isNotEmpty();

        // TODO: Can we tidy this up with the $this->ensureSetIsAvailableOnSite() method instead?
        if (! $hasDefaultsForSite) {
            session()->flash('error', __('There are no :type defaults available on site ":handle".', [
                'type' => 'Site',
                'handle' => Site::selected()->name(),
            ]));

            throw new NotFoundHttpException();
        }

        $this->authorize('index', [SeoVariables::class, 'site']);

        return view('advanced-seo::cp.site');
    }

    public function edit(Request $request, string $handle): mixed
    {
        throw_unless(Defaults::isEnabled("site::{$handle}"), new NotFoundHttpException);

        $set = $this->set($handle);

        $this->authorize('view', [SeoVariables::class, $set]);

        $site = $request->site ?? Site::selected()->handle();

        $sites = Site::all()->map->handle();

        // TODO: Probably don't need to pass the sites anymore as we are getting those in the seoDefaultsSet now.
        $set = $set->createLocalizations($sites);

        if ($response = $this->ensureSetIsAvailableOnSite($set, $site)) {
            return $response;
        }

        $localization = $set->in($site);

        $blueprint = $localization->blueprint();

        [$values, $meta] = $this->extractFromFields($localization, $blueprint);

        if ($hasOrigin = $localization->hasOrigin()) {
            [$originValues, $originMeta] = $this->extractFromFields($localization->origin(), $blueprint);
        }

        // This variable solely exists to prevent variable conflict in $viewData['localizations'].
        $requestLocalization = $localization;

        $viewData = [
            'title' => $set->title(),
            'reference' => $localization->reference(),
            'editing' => true,
            'actions' => [
                'save' => $localization->updateUrl(),
            ],
            // TODO: Make this work with $set->id()
            // 'values' => array_merge($values, ['id' => $set->id()]),
            'values' => array_merge($values, ['id' => "{$set->type()}::{$set->handle()}"]),
            'meta' => $meta,
            'blueprint' => $blueprint->toPublishArray(),
            'locale' => $localization->locale(),
            'localizedFields' => $localization->data()->keys()->all(),
            'isRoot' => $localization->isRoot(),
            'hasOrigin' => $hasOrigin,
            'originValues' => $originValues ?? null,
            'originMeta' => $originMeta ?? null,
            'localizations' => $this->authorizedSites($set)->map(function ($site) use ($set, $requestLocalization) {
                $localization = $set->in($site);
                $exists = $localization !== null;

                return [
                    'handle' => $site,
                    'name' => Site::get($site)->name(),
                    'active' => $site === $requestLocalization->locale(),
                    'exists' => $exists,
                    'published' => true,
                    'root' => $exists ? $localization->isRoot() : false,
                    'origin' => $exists ? $localization->locale() === optional($requestLocalization->origin())->locale() : null,
                    'url' => $exists ? $localization->editUrl() : null,
                ];
            })->values()->all(),
            'breadcrumbs' => $this->breadcrumbs(),
            'readOnly' => User::current()->cant("edit seo {$handle} defaults"),
            'contentType' => 'site',
        ];

        if ($request->wantsJson()) {
            return $viewData;
        }

        return view('advanced-seo::cp/edit', array_merge($viewData, [
            'set' => $set,
            'variables' => $localization,
        ]));
    }

    public function update(string $handle, Request $request): void
    {
        $set = $this->set($handle);

        $this->authorize('edit', [SeoVariables::class, $set]);

        $site = $request->site ?? Site::selected()->handle();

        $sites = Site::all()->map->handle();

        $localization = $set->in($site)->determineOrigin($sites);

        $blueprint = $localization->blueprint();

        $fields = $blueprint->fields()->addValues($request->all());

        $fields->validate();

        $values = $fields->process()->values();

        $localization->hasOrigin()
            ? $localization->data($values->only($request->input('_localized')))
            : $localization->merge($values);

        $localization = $localization->save();

        SeoDefaultSetSaved::dispatch($localization->seoSet());
    }

    protected function breadcrumbs(): Breadcrumbs
    {
        return new Breadcrumbs([
            [
                'text' => __('advanced-seo::messages.site'),
                'url' => cp_route('advanced-seo.site.index'),
            ],
        ]);
    }

    protected function set(string $handle): SeoDefaultSet
    {
        return Seo::findOrMake('site', $handle);
    }
}
