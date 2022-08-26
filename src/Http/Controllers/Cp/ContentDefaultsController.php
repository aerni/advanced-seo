<?php

namespace Aerni\AdvancedSeo\Http\Controllers\Cp;

use Aerni\AdvancedSeo\Events\SeoDefaultSetSaved;
use Aerni\AdvancedSeo\Models\Defaults;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Statamic\CP\Breadcrumbs;
use Statamic\Exceptions\NotFoundHttpException;
use Statamic\Facades\Site;
use Statamic\Facades\User;

abstract class ContentDefaultsController extends BaseDefaultsController
{
    public function index(): View
    {
        throw_unless(Defaults::enabledInType($this->type)->isNotEmpty(), new NotFoundHttpException);

        $this->authorize('index', [SeoVariables::class, $this->type]);

        return view("advanced-seo::cp.{$this->type}");
    }

    public function edit(Request $request, string $handle): mixed
    {
        throw_unless(Defaults::isEnabled("{$this->type}::{$handle}"), new NotFoundHttpException);

        $this->authorize("view seo {$handle} defaults");

        $set = $this->set($handle);
        $content = $this->content($handle);

        $requestedSite = $request->site ?? Site::selected()->handle();

        // Select the requested site if it exists in the sites configuration of the collection/taxonomy.
        // Fall back to the default site or first site in the array.
        $site = $this->evaluateSite($content->sites(), $requestedSite);

        // Create a localization for each of the provided sites. This triggers a save on the set.
        $set = $set->createLocalizations($content->sites());

        $localization = $set->in($site);

        $blueprint = $localization->blueprint();

        [$values, $meta] = $this->extractFromFields($localization, $blueprint);

        if ($hasOrigin = $localization->hasOrigin()) {
            [$originValues, $originMeta] = $this->extractFromFields($localization->origin(), $blueprint);
        }

        $user = User::fromUser($request->user());

        // This variable solely exists to prevent variable conflict in $viewData['localizations'].
        $requestLocalization = $localization;

        $viewData = [
            'title' => $set->title(),
            'reference' => $localization->reference(),
            'editing' => true,
            'actions' => [
                'save' => $localization->updateUrl(),
            ],
            'values' => $values,
            'values' => array_merge($values, ['id' => $set->id()]),
            'meta' => $meta,
            'blueprint' => $blueprint->toPublishArray(),
            'locale' => $localization->locale(),
            'localizedFields' => $localization->data()->keys()->all(),
            'isRoot' => $localization->isRoot(),
            'hasOrigin' => $hasOrigin,
            'originValues' => $originValues ?? null,
            'originMeta' => $originMeta ?? null,
            'localizations' => $content->sites()->map(function ($site) use ($set, $requestLocalization) {
                $localization = $set->in($site);
                $exists = $localization !== null;

                return [
                    'handle' => $site,
                    'name' => Site::get($site)->name(),
                    'active' => $site === $requestLocalization->locale(),
                    'exists' => $exists,
                    'root' => $exists ? $localization->isRoot() : false,
                    'origin' => $exists ? $localization->locale() === optional($requestLocalization->origin())->locale() : null,
                    'url' => $exists ? $localization->editUrl() : null,
                ];
            })->values()->all(),
            'breadcrumbs' => $this->breadcrumbs(),
            'readOnly' => $user->cant("edit seo {$handle} defaults"),
            'contentType' => $this->type,
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
        $this->authorize("edit seo {$handle} defaults");

        $set = $this->set($handle);
        $content = $this->content($handle);

        $site = $request->site ?? Site::selected()->handle();

        $blueprint = $set->blueprint();

        $fields = $blueprint->fields()->addValues($request->all());

        $fields->validate();

        $values = $fields->process()->values();

        $localization = $set->in($site)->determineOrigin($content->sites());

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
                'text' => __("advanced-seo::messages.{$this->type}"),
                'url' => cp_route("advanced-seo.{$this->type}.index"),
            ],
        ]);
    }

    protected function evaluateSite(Collection $sites, string $site): string
    {
        if ($sites->contains($site)) {
            return $site;
        }

        $defaultSite = Site::default()->handle();

        return $sites->contains($defaultSite) ? $defaultSite : $sites->first();
    }

    abstract protected function set(string $handle): mixed;

    abstract protected function content(string $handle): mixed;
}
