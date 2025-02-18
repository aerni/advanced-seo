<?php

namespace Aerni\AdvancedSeo\UpdateScripts;

use Illuminate\Support\Facades\File;
use Statamic\UpdateScripts\UpdateScript;

class CreateSocialImagesTheme extends UpdateScript
{
    public function shouldUpdate($newVersion, $oldVersion)
    {
        return $this->isUpdatingTo('1.1.0');
    }

    public function update()
    {
        $filesToMove = collect([
            'open_graph.antlers.html',
            'twitter_summary.antlers.html',
            'twitter_summary_large_image.antlers.html',
        ])->filter(fn ($file) => File::exists(resource_path("views/social_images/{$file}")));

        if ($filesToMove->isEmpty()) {
            return;
        }

        File::ensureDirectoryExists(resource_path('views/social_images/default'));

        $filesToMove->each(function ($file) {
            $path = resource_path("views/social_images/{$file}");
            $target = resource_path("views/social_images/default/{$file}");

            File::move($path, $target);
        });

        $this->console()->info('Successfully migrated social images theme.');
    }
}
