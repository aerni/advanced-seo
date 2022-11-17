<?php

namespace Aerni\AdvancedSeo\Tags;

use Aerni\AdvancedSeo\Support\Helpers;
use Aerni\AdvancedSeo\View\AntlersCascade;
use Illuminate\Support\Arr;
use Illuminate\View\View;
use Statamic\Facades\Blink;
use Statamic\Tags\Tags;

class AdvancedSeoTags extends Tags
{
    protected static $handle = 'seo';

    /**
     * Returns a specific variable from the seo cascade.
     */
    public function wildcard(): mixed
    {
        if (! $this->canProcessCascade()) {
            return null;
        }

        return Arr::get($this->cascade(), $this->method);
    }

    /**
     * Renders the head view with the seo cascade.
     */
    public function head(): ?View
    {
        if (! $this->canProcessCascade()) {
            return null;
        }

        return view('advanced-seo::head', $this->cascade());
    }

    /**
     * Renders the body view with the seo cascade.
     */
    public function body(): ?View
    {
        if (! $this->canProcessCascade()) {
            return null;
        }

        return view('advanced-seo::body', $this->cascade());
    }

    /**
     * Dumps the seo cascade for easier debugging.
     */
    public function dump(): void
    {
        if ($this->canProcessCascade()) {
            dd($this->cascade());
        }
    }

    /**
     * Returns the computed seo cascade.
     */
    protected function cascade(): array
    {
        return Blink::once('advanced-seo::cascade::antlers', function () {
            return AntlersCascade::from($this->context)->process()->all();
        });
    }

    /**
     * Check if we can process the cascade.
     */
    protected function canProcessCascade(): bool
    {
        // Custom routes don't have the necessary data to compose the SEO cascade.
        if (Helpers::isCustomRoute()) {
            return false;
        }

        // Don't add data for collections that are excluded in the config.
        if ($this->context->has('is_entry') && in_array($this->context->get('collection')->raw()->handle(), config('advanced-seo.disabled.collections', []))) {
            return false;
        }

        // Don't add data for taxonomy terms that are excluded in the config.
        if ($this->context->has('is_term') && in_array($this->context->get('taxonomy')->raw()->handle(), config('advanced-seo.disabled.taxonomies', []))) {
            return false;
        }

        // Don't add data for taxonomies that are excluded in the config.
        if ($this->context->has('terms') && in_array($this->context->get('handle')->raw(), config('advanced-seo.disabled.taxonomies', []))) {
            return false;
        }

        return true;
    }
}
