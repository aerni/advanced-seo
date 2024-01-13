<?php

namespace Aerni\AdvancedSeo\Http\Controllers\Cp;

use Aerni\AdvancedSeo\Data\SeoDefaultSet;
use Aerni\AdvancedSeo\Data\SeoVariables;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Statamic\Facades\Site;
use Statamic\Fields\Blueprint;
use Statamic\Http\Controllers\CP\CpController;

abstract class BaseDefaultsController extends CpController
{
    abstract public function index(): View;

    abstract public function edit(Request $request, string $handle): mixed;

    abstract public function update(string $handle, Request $request): void;

    abstract protected function set(string $handle): mixed;

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

    protected function ensureSetIsAvailableOnSite(SeoDefaultSet $set, $site): ?RedirectResponse
    {
        if ($set->sites()->contains($site)) {
            return null;
        }

        return redirect(cp_route("advanced-seo.{$set->type()}.index"))
            ->with('error', __('The ":set" defaults are not available on site ":handle".', ['set' => $set->title(), 'handle' => Site::get($site)->name()]));
    }
}
