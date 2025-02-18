<?php

namespace Aerni\AdvancedSeo\Http\Controllers\Cp;

use Aerni\AdvancedSeo\Contracts\SeoDefaultSet;
use Aerni\AdvancedSeo\Data\SeoVariables;
use Aerni\AdvancedSeo\Events\SeoDefaultSetSaved;
use Aerni\AdvancedSeo\Facades\Seo;
use Aerni\AdvancedSeo\Models\Defaults;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Statamic\CP\Breadcrumbs;
use Statamic\Exceptions\NotFoundHttpException;
use Statamic\Facades\Site;
use Statamic\Facades\User;
use Statamic\Fields\Blueprint;
use Statamic\Http\Controllers\CP\CpController;

class SeoDefaultsController extends CpController
{
    public function index(): View
    {
        $defaults = $this->defaults();

        if ($defaults->isEmpty()) {
            $this->flashDefaultsUnavailable();
        }

        $this->authorize('index', [SeoVariables::class, $this->type()]);

        return view("advanced-seo::cp.{$this->type()}", [
            'defaults' => $defaults,
        ]);
    }

    public function edit(Request $request, string $handle): mixed
    {
        throw_unless(Defaults::isEnabled("{$this->type()}::{$handle}"), new NotFoundHttpException);

        $set = $this->set($handle);

        $site = $request->site ?? Site::selected()->handle();

        if (! $set->availableInSite($site)) {
            return $this->redirectToIndex($set, $site);
        }

        $this->authorize('view', [SeoVariables::class, $set]);

        // Create a localization for each of the provided sites. This triggers a save on the set.
        // TODO: Do we really need to create the localizations or can we simply ensure them with ensureLocalizations()?
        // Ensuring wouldn't save them to file. But maybe we don't even have to do that?
        // TODO: Probably don't need to pass the sites anymore as we are getting those in the seoDefaultsSet now.
        $set = $set->createLocalizations($set->sites());

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
            'values' => array_merge($values, ['id' => $set->id()]),
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
            'contentType' => $this->type(),
        ];

        if ($request->wantsJson()) {
            return $viewData;
        }

        return view('advanced-seo::cp/edit', array_merge($viewData, [
            'set' => $set,
            'variables' => $localization,
        ]));
    }

    public function update(Request $request, string $handle): void
    {
        $set = $this->set($handle);

        $this->authorize('edit', [SeoVariables::class, $set]);

        $site = $request->site ?? Site::selected()->handle();

        $localization = $set->in($site)->determineOrigin($set->sites());

        $blueprint = $localization->blueprint();

        $fields = $blueprint->fields()->addValues($request->all());

        $fields->validate();

        $values = $fields->process()->values();

        $localization->hasOrigin()
            ? $localization->data($values->only($request->input('_localized')))
            : $localization->merge($values);

        $localization = $localization->save();

        // TODO: We should probably dispatch this event in the save method of the SeoDefaultSet class or the repository.
        SeoDefaultSetSaved::dispatch($localization->seoSet());
    }

    protected function set(string $handle): SeoDefaultSet
    {
        return Seo::findOrMake($this->type(), $handle);
    }

    protected function extractFromFields(SeoVariables $localization, Blueprint $blueprint): array
    {
        $fields = $blueprint
            ->fields()
            ->addValues($localization->values()->all())
            ->preProcess();

        return [$fields->values()->all(), $fields->meta()->all()];
    }

    protected function authorizedSites(SeoDefaultSet $set): Collection
    {
        return $set->sites()->intersect(Site::authorized());
    }

    protected function defaults(): Collection
    {
        return Defaults::enabledInType($this->type())
            ->filter(fn ($default) => $default['set']->availableInSite(Site::selected()->handle()))
            ->filter(fn ($default) => User::current()->can('view', [SeoVariables::class, $default['set']]));
    }

    protected function flashDefaultsUnavailable(): void
    {
        session()->now('error', __('There are no :type defaults available for the selected site.', [
            'type' => Str::singular($this->type()),
        ]));

        throw new NotFoundHttpException;
    }

    protected function redirectToIndex(SeoDefaultSet $set, string $site): RedirectResponse
    {
        return redirect(cp_route("advanced-seo.{$set->type()}.index"))
            ->with('error', __('The :set :type is not available in the selected site.', [
                'set' => $set->title(),
                'type' => Str::singular($this->type()),
            ]));
    }

    protected function breadcrumbs(): Breadcrumbs
    {
        return new Breadcrumbs([
            [
                'text' => __("advanced-seo::messages.{$this->type()}"),
                'url' => cp_route("advanced-seo.{$this->type()}.index"),
            ],
        ]);
    }

    protected function type(): string
    {
        $segments = request()->segments();
        $key = array_search('advanced-seo', $segments) + 1;

        return $segments[$key];
    }
}
