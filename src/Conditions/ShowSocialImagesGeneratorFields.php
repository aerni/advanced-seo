<?php

namespace Aerni\AdvancedSeo\Conditions;

use Aerni\AdvancedSeo\Data\DefaultsData;
use Aerni\AdvancedSeo\Facades\Seo;
use Statamic\Facades\Site;

class ShowSocialImagesGeneratorFields
{
    public static function handle(DefaultsData $data): bool
    {
        // Don't show the generator section if the generator is disabled.
        if (! config('advanced-seo.social_images.generator.enabled', false)) {
            return false;
        }

        // Terms are not yet supported.
        if ($data->type === 'taxonomies') {
            return false;
        }

        $enabledCollections = Seo::find('site', 'social_media')
            ?->in(Site::selected()->handle())
            ?->value('social_images_generator_collections') ?? [];

        // Don't show the generator section if the collection is not configured.
        if ($data->type === 'collections' && ! in_array($data->handle, $enabledCollections)) {
            return false;
        }

        return true;
    }
}
