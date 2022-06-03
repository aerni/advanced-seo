<?php

namespace Aerni\AdvancedSeo\View;

use Aerni\AdvancedSeo\Actions\EvaluateContextType;
use Aerni\AdvancedSeo\Actions\GetContentDefaults;
use Aerni\AdvancedSeo\Actions\GetPageData;
use Aerni\AdvancedSeo\Actions\GetSiteDefaults;
use Aerni\AdvancedSeo\Data\DefaultsData;
use Aerni\AdvancedSeo\Facades\SocialImage;
use Aerni\AdvancedSeo\Models\Defaults;
use Aerni\AdvancedSeo\Support\Helpers;
use Illuminate\Support\Collection;
use Spatie\SchemaOrg\Schema;
use Statamic\Contracts\Entries\Entry;
use Statamic\Facades\Blink;
use Statamic\Facades\Data;
use Statamic\Facades\Site;
use Statamic\Facades\URL;
use Statamic\Fields\Value;
use Statamic\Stache\Query\TermQueryBuilder;
use Statamic\Support\Str;
use Statamic\Tags\Context;
use Statamic\Taxonomies\Taxonomy;

class Cascade
{
    protected Context|DefaultsData $context;
    protected Collection $data;

    public function __construct(Context|DefaultsData $context)
    {
        $this->context = $context;
        $this->data = collect();
    }

    public static function from(Context|DefaultsData $context): self
    {
        return new static($context);
    }

    public function withSiteDefaults(): self
    {
        $siteDefaults = GetSiteDefaults::handle($this->context);
        $siteDefaults = $this->removeSeoPrefixFromKeys($siteDefaults);

        $this->data = $this->data->merge($siteDefaults);

        return $this;
    }

    public function withContentDefaults(): self
    {
        $contentDefaults = GetContentDefaults::handle($this->context);
        $contentDefaults = $this->removeSeoPrefixFromKeys($contentDefaults);

        $this->data = $this->data->merge($contentDefaults);

        return $this;
    }

    public function withPageData(): self
    {
        if (! $this->context instanceof Context) {
            throw new \Exception("The context needs to be an instance of Statamic\Tags\Context in order to get the page data.");
        }

        $pageData = GetPageData::handle($this->context);
        $pageData = $this->removeSeoPrefixFromKeys($pageData);

        $this->data = $this->data->merge($pageData);

        return $this;
    }

    public function withComputedData(): self
    {
        if (! $this->context instanceof Context) {
            throw new \Exception("The context needs to be an instance of Statamic\Tags\Context in order to get the computed data.");
        }

        $this->data = $this->data->merge([
            'title' => $this->compiledTitle(),
            'og_image' => $this->ogImage(),
            'og_image_size' => $this->ogImageSize(),
            'og_title' => $this->ogTitle(),
            'twitter_card' => $this->twitterCard(),
            'twitter_image' => $this->twitterImage(),
            'twitter_image_size' => $this->twitterImageSize(),
            'twitter_title' => $this->twitterTitle(),
            'twitter_handle' => $this->twitterHandle(),
            'indexing' => $this->indexing(),
            'locale' => $this->locale(),
            'hreflang' => $this->hreflang(),
            'canonical' => $this->canonical(),
            'prev_url' => $this->prevUrl(),
            'next_url' => $this->nextUrl(),
            'schema' => $this->schema(),
            'breadcrumbs' => $this->breadcrumbs(),
        ])->filter();

        return $this;
    }

    public function all(): array
    {
        return $this->data->all();
    }

    public function get(string $key): mixed
    {
        return $this->data->get($key);
    }

    public function value(string $key): mixed
    {
        return $this->data->get($key)?->value();
    }

    public function raw(string $key): mixed
    {
        return $this->data->get($key)?->raw();
    }

    public function processForFrontend(): array
    {
        return $this
            ->withSiteDefaults()
            ->withPageData()
            ->ensureOverrides()
            ->withComputedData()
            ->applyWhitelist()
            ->sortKeys()
            ->all();
    }

    public function processForFieldtype(): self
    {
        return $this
            ->withSiteDefaults()
            ->withContentDefaults()
            ->ensureOverrides()
            ->sortKeys();
    }

    /**
     * Make sure to get the site defaults if there is no value
     * for the overrides keys in the current data.
     */
    protected function ensureOverrides(): self
    {
        // The keys that should be considered for the overrides.
        $overrides = ['noindex', 'nofollow', 'og_image', 'twitter_summary_image', 'twitter_summary_large_image'];

        // The values that should be used as overrides.
        $defaults = GetSiteDefaults::handle($this->context)->only($overrides);

        // The values from the existing data that should be overriden.
        $data = $this->data->only($overrides)->filter(fn ($item) => $item->value());

        // Only merge the defaults overrides if they don't exist in the data.
        $merged = $defaults->diffKeys($data)->merge($data);

        $this->data = $this->data->merge($merged);

        return $this;
    }

    protected function removeSeoPrefixFromKeys(Collection $data): Collection
    {
        return $data->mapWithKeys(fn ($item, $key) => [Str::remove('seo_', $key) => $item]);
    }

    protected function sortKeys(): self
    {
        $this->data = $this->data->sortKeys();

        return $this;
    }

    protected function isType(string $type): bool
    {
        return EvaluateContextType::handle($this->context) === $type;
    }

    protected function applyWhitelist(): self
    {
        // Remove all the keys from the data that won't be used in any view on the frontend.
        $this->data = $this->data->only([
            'use_fathom',
            'fathom_domain',
            'fathom_id',
            'fathom_spa',
            'use_cloudflare_web_analytics',
            'cloudflare_web_analytics',
            'use_google_tag_manager',
            'google_tag_manager',
            'title',
            'description',
            'canonical',
            'prev_url',
            'next_url',
            'favicon_svg',
            'hreflang',
            'indexing',
            'schema',
            'breadcrumbs',
            'site_name',
            'locale',
            'og_title',
            'og_description',
            'og_image',
            'generate_social_images',
            'og_image_size',
            'google_site_verification_code',
            'bing_site_verification_code',
            'twitter_card',
            'twitter_title',
            'twitter_description',
            'twitter_handle',
            'twitter_image',
            'twitter_image_size',
        ]);

        // Remove any analytics data if analytics isn't enabled for the current environment.
        if (! in_array(app()->environment(), config('advanced-seo.analytics.environments', ['production']))) {
            $this->data->forget([
                'use_fathom',
                'fathom_domain',
                'fathom_id',
                'fathom_spa',
                'use_cloudflare_web_analytics',
                'cloudflare_web_analytics',
                'use_google_tag_manager',
                'google_tag_manager',
            ]);
        }

        return $this;
    }

    protected function compiledTitle(): string
    {
        return $this->value('title_position')->value() === 'before'
            ? "{$this->title()} {$this->titleSeparator()} {$this->siteName()}"
            : "{$this->siteName()} {$this->titleSeparator()} {$this->title()}";
    }

    protected function title(): string
    {
        return match (true) {
            $this->isType('taxonomy') => $this->context->get('title'),
            $this->isType('error') => $this->context->get('response_code'),
            default => $this->get('title'),
        };
    }

    protected function titleSeparator(): string
    {
        return $this->get('title_separator');
    }

    protected function siteName(): string
    {
        return $this->get('site_name') ?? config('app.name');
    }

    protected function ogTitle(): string
    {
        return $this->get('og_title') ?? $this->title();
    }

    protected function ogImage(): ?Value
    {
        return $this->value('generate_social_images')
            ? $this->get('generated_og_image')
            : $this->get('og_image');
    }

    protected function ogImageSize(): ?array
    {
        if (! $this->ogImage()) {
            return null;
        }

        return collect(SocialImage::findModel('open_graph'))
            ->only(['width', 'height'])
            ->all();
    }

    protected function twitterTitle(): string
    {
        return $this->get('twitter_title') ?? $this->title();
    }

    protected function twitterCard(): string
    {
        if ($card = $this->get('twitter_card')) {
            return $card;
        }

        /**
         * Determine the twitter card based on the images set in the social media defaults.
         * This is used on taxonomy and error pages.
         */
        $image = $this->get('twitter_summary_large_image') ?? $this->get('twitter_summary_image');

        return $image?->field()?->config()['twitter_card'] ?? Defaults::data('collections')->get('seo_twitter_card');
    }

    protected function twitterImage(): ?Value
    {
        if (! $model = SocialImage::findModel("twitter_{$this->twitterCard()}")) {
            return null;
        }

        return $this->value('generate_social_images')
            ? $this->get('generated_twitter_image')
            : $this->get($model['handle']);
    }

    protected function twitterImageSize(): ?array
    {
        if (! $this->twitterImage()) {
            return null;
        }

        return collect(SocialImage::findModel("twitter_{$this->twitterCard()}"))
            ->only(['width', 'height'])
            ->all();
    }

    protected function twitterHandle(): ?string
    {
        $twitterHandle = $this->value('twitter_handle');

        return $twitterHandle ? Str::start($twitterHandle, '@') : null;
    }

    protected function indexing(): string
    {
        return collect([
            'noindex' => $this->value('noindex'),
            'nofollow' => $this->value('nofollow'),
        ])->filter()->keys()->implode(', ');
    }

    protected function locale(): string
    {
        return Helpers::parseLocale(Site::current()->locale());
    }

    protected function hreflang(): ?array
    {
        /*
        TODO: Support collection taxonomy index page.
        Return if we're on a collection taxonomy index page.
        Statamic has yet to provide a way to get the URLs of collection taxonomies.
        */
        if ($this->context->has('segment_2') && $this->context->get('terms') instanceof TermQueryBuilder) {
            return null;
        }

        /*
        TODO: Support collection term show page.
        Return if we're on a collection term show page.
        Statamic has yet to provide a way to get the URLs of collection terms.
        */
        if ($this->context->has('segment_3') && $this->context->value('is_term') === true) {
            return null;
        }

        // Handles taxonomy index page.
        if ($this->context->has('segment_1') && $this->context->get('terms') instanceof TermQueryBuilder) {
            $taxonomy = $this->context->get('terms')->first()->taxonomy();

            $initialSite = Site::current()->handle();

            $data = $taxonomy->sites()->map(function ($locale) use ($taxonomy) {
                // Set the current site so we can get the localized absolute URLs of the taxonomy.
                Site::setCurrent($locale);

                return [
                    'url' => $taxonomy->absoluteUrl(),
                    'locale' => Helpers::parseLocale(Site::get($locale)->locale()),
                ];
            })->toArray();

            // Reset the site to the original.
            Site::setCurrent($initialSite);

            return $data;
        }

        // Handle entries and term show page.
        $data = Data::find($this->context->get('id'));

        if (! $data) {
            return null;
        }

        $sites = $data instanceof Entry
            ? $data->sites()
            : $data->taxonomy()->sites();

        // We only want to return data for published entries and terms.
        $alternates = $sites->filter(fn ($locale) => $data->in($locale)?->published())->values();

        return $alternates->map(fn ($locale) => [
            'url' => $data->in($locale)->absoluteUrl(),
            'locale' => Helpers::parseLocale(Site::get($locale)->locale()),
        ])->toArray();
    }

    protected function canonical(): ?string
    {
        // We don't want to output a canonical tag if noindex is true.
        if ($this->value('noindex')) {
            return null;
        }

        $type = $this->value('canonical_type')?->value();

        if ($type === 'other') {
            return $this->value('canonical_entry')?->absoluteUrl();
        }

        if ($type === 'custom') {
            return $this->value('canonical_custom');
        }

        // Handle canonical type "current".
        $currentUrl = $this->context->get('current_url');

        // Don't add the pagination parameter if it doesn't exists or there's no paginator on the page.
        if (! app('request')->has('page') || ! Blink::get('tag-paginator')) {
            return $currentUrl;
        }

        $page = (int) app('request')->get('page');

        // Don't include the pagination parameter for the first page. We don't want the same site to be indexed with and without parameter.
        return $page === 1
            ? $currentUrl
            : "{$currentUrl}?page={$page}";
    }

    protected function prevUrl(): ?string
    {
        if (! $paginator = Blink::get('tag-paginator')) {
            return null;
        }

        $currentUrl = $this->context->get('current_url');

        $page = $paginator->currentPage();

        // Don't include the pagination parameter for the first page. We don't want the same site to be indexed with and without parameter.
        if ($page === 2) {
            return $currentUrl;
        }

        return $page > 1 && $page <= $paginator->lastPage()
            ? $currentUrl.'?page='.($page - 1)
            : null;
    }

    protected function nextUrl(): ?string
    {
        if (! $paginator = Blink::get('tag-paginator')) {
            return null;
        }

        $currentUrl = $this->context->get('current_url');

        $page = $paginator->currentPage();

        return $page < $paginator->lastPage()
            ? $currentUrl.'?page='.($page + 1)
            : null;
    }

    protected function schema(): ?string
    {
        $schema = $this->siteSchema().$this->entrySchema();

        return ! empty($schema) ? $schema : null;
    }

    protected function siteSchema(): ?string
    {
        $type = $this->value('site_json_ld_type')?->value();

        if ($type === 'none') {
            return null;
        }

        if ($type === 'custom') {
            $data = $this->value('site_json_ld')?->value();

            return $data
                ? '<script type="application/ld+json">'.$data.'</script>'
                : null;
        }

        if ($type === 'organization') {
            $schema = Schema::organization()
                ->name($this->value('organization_name'))
                ->url($this->context->get('site')->absoluteUrl());

            if ($logo = $this->value('organization_logo')) {
                $logo = Schema::imageObject()
                    ->url($logo->absoluteUrl())
                    ->width($logo->width())
                    ->height($logo->height());

                $schema->logo($logo);
            }
        }

        if ($type === 'person') {
            $schema = Schema::person()
                ->name($this->value('person_name'))
                ->url($this->context->get('site')->absoluteUrl());
        }

        return $schema->toScript();
    }

    protected function entrySchema(): ?string
    {
        $data = $this->value('json_ld')?->value();

        return $data
            ? '<script type="application/ld+json">'.$data.'</script>'
            : null;
    }

    protected function breadcrumbs(): ?string
    {
        // Don't render breadcrumbs if deactivated in the site defaults.
        if (! $this->value('use_breadcrumbs')) {
            return null;
        }

        // Don't render breadcrumbs on the homepage.
        if ($this->context->get('is_homepage')) {
            return null;
        }

        $listItems = $this->breadcrumbsListItems()->map(function ($crumb) {
            return Schema::listItem()
                ->position($crumb['position'])
                ->name($crumb['title'])
                ->item($crumb['url']);
        })->all();

        return Schema::breadcrumbList()->itemListElement($listItems);
    }

    protected function breadcrumbsListItems(): Collection
    {
        $url = URL::makeAbsolute(URL::getCurrent());
        $url = Str::removeLeft($url, Site::current()->absoluteUrl());
        $url = Str::ensureLeft($url, '/');

        $segments = explode('/', $url);
        $segments[0] = '/';

        $crumbs = collect($segments)->map(function () use (&$segments) {
            $uri = URL::tidy(implode('/', $segments));
            array_pop($segments);

            return Data::findByUri(Str::ensureLeft($uri, '/'), Site::current()->handle());
        })
        ->filter()
        ->reverse()
        ->values()
        ->map(function ($item, $key) {
            return [
                'position' => $key + 1,
                'title' => method_exists($item, 'title') ? $item->title() : $item->value('title'),
                'url' => $item->absoluteUrl(),
            ];
        });

        return $crumbs;
    }
}
