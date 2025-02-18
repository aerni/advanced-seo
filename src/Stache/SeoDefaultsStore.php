<?php

namespace Aerni\AdvancedSeo\Stache;

use Aerni\AdvancedSeo\Contracts\SeoDefaultSet;
use Aerni\AdvancedSeo\Data\SeoVariables;
use Aerni\AdvancedSeo\Facades\Seo;
use Illuminate\Support\Str;
use Statamic\Facades\File;
use Statamic\Facades\Path;
use Statamic\Facades\Site;
use Statamic\Facades\YAML;
use Statamic\Stache\Stores\ChildStore;
use Statamic\Support\Arr;
use Symfony\Component\Finder\SplFileInfo;

class SeoDefaultsStore extends ChildStore
{
    public function getItemFilter(SplFileInfo $file): bool
    {
        $filename = Path::tidy($file->getRelativePathname());

        // Only get the SeoDefaultSet that exists in the root. Don't get the SeoVariables.
        return substr_count($filename, '/') === 0
            && $file->getExtension() === 'yaml';
    }

    public function makeItemFromFile($path, $contents): SeoDefaultSet
    {
        $data = YAML::file($path)->parse($contents);

        return Site::multiEnabled()
            ? $this->makeMultiSiteDefaultFromFile($path)
            : $this->makeSingleSiteDefaultFromFile($path, $data);
    }

    protected function makeBaseDefaultFromFile(string $path): SeoDefaultSet
    {
        [$type, $handle] = $this->extractAttributesFromPath($path);

        return Seo::make()
            ->handle($handle)
            ->type($type)
            ->initialPath($path);
    }

    protected function makeSingleSiteDefaultFromFile(string $path, array $data): SeoDefaultSet
    {
        $set = $this->makeBaseDefaultFromFile($path);

        $localization = $set->makeLocalization(Site::default()->handle())
            ->initialPath($path)
            ->merge($data['data'] ?? []);

        return $set->addLocalization($localization);
    }

    protected function makeMultiSiteDefaultFromFile(string $path): SeoDefaultSet
    {
        $set = $this->makeBaseDefaultFromFile($path);

        Site::all()->filter(function ($site) use ($set) {
            return File::exists("{$this->directory()}/{$site->handle()}/{$set->handle()}.yaml");
        })->map->handle()->map(function ($site) use ($set) {
            return $this->makeVariables($set, $site);
        })->filter()->each(function ($variables) use ($set) {
            $set->addLocalization($variables);
        });

        return $set;
    }

    protected function makeVariables(SeoDefaultSet $set, string $site): ?SeoVariables
    {
        $variables = $set->makeLocalization($site);

        // TODO: cache the reading and parsing of the file

        if (! File::exists($path = $variables->path())) {
            return null;
        }

        $data = YAML::file($path)->parse();

        $variables
            ->initialPath($path)
            ->merge(Arr::except($data, 'origin'))
            ->origin(Arr::get($data, 'origin'));

        return $variables;
    }

    protected function extractAttributesFromPath(string $path): array
    {
        $relative = Str::after($path, $this->parent->directory());
        $type = pathinfo($relative, PATHINFO_DIRNAME);
        $handle = pathinfo($relative, PATHINFO_FILENAME);

        return [$type, $handle];
    }

    public function save($set): void
    {
        parent::save($set);

        // TODO: This could likely be simplified by using the sites() method on the $set.
        // See makeModelFromContract() on Eloquent SeoDefaultSet.
        if (Site::multiEnabled()) {
            Site::all()->each(function ($site) use ($set) {
                $site = $site->handle();
                $set->existsIn($site) ? $set->in($site)->writeFile() : $set->makeLocalization($site)->deleteFile();
            });
        }
    }

    public function delete($set): void
    {
        parent::delete($set);

        if (Site::multiEnabled()) {
            $set->localizations()->each(function ($localization) {
                $localization->deleteFile();
            });
        }
    }
}
