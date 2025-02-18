<?php

namespace Aerni\AdvancedSeo\Listeners;

use Aerni\AdvancedSeo\Contracts\SeoDefaultSet;
use Aerni\AdvancedSeo\Events\SeoDefaultSetSaved;
use Illuminate\Contracts\Queue\ShouldQueue;
use Statamic\Facades\File;
use Statamic\Facades\Path;

class GenerateFavicons implements ShouldQueue
{
    protected function shouldHandle(SeoDefaultSet $defaults): bool
    {
        return $defaults->handle() === 'favicons'
            && config('advanced-seo.favicons.enabled', true)
            && config('advanced-seo.favicons.generator.enabled', false);
    }

    public function handle(SeoDefaultSetSaved $event): void
    {
        if (! $this->shouldHandle($event->defaults)) {
            return;
        }

        $favicon = $event->defaults->inDefaultSite()
            ->augmentedValue('favicon_svg')
            ->value();

        $exportPath = Path::tidy($favicon->container()->diskPath().'/'.$favicon->folder());

        $svg = File::get($favicon->resolvedPath());

        $iOSBackground = $event->defaults->inDefaultSite()->get('favicon_ios_color');

        $this->createThumbnail($svg, $exportPath.'/apple-touch-icon.png', 180, 180, $iOSBackground, 15);
        $this->createThumbnail($svg, $exportPath.'/android-chrome-512x512.png', 512, 512, 'transparent', false);
    }

    private function createThumbnail($svg, $exportPath, $width, $height, $background, $border)
    {
        $svgObj = simplexml_load_string($svg);

        $viewBox = explode(' ', $svgObj['viewBox']);
        $viewBoxWidth = $viewBox[2];
        $viewBoxHeight = $viewBox[3];

        if ($viewBoxWidth >= $viewBoxHeight) {
            $ratio = $width / $viewBoxWidth;
        } else {
            $ratio = $height / $viewBoxHeight;
        }

        $imagick = new \Imagick;

        $imagick->setResolution($viewBoxWidth * $ratio * 2, $viewBoxHeight * $ratio * 2);
        $imagick->setBackgroundColor(new \ImagickPixel($background));
        $imagick->readImageBlob($svg);
        $imagick->resizeImage($viewBoxWidth * $ratio, $viewBoxHeight * $ratio, \imagick::FILTER_LANCZOS, 1);

        if ($viewBoxWidth >= $viewBoxHeight) {
            $compensateHeight = $height - ($viewBoxHeight * $ratio);
            $imagick->extentImage($width, $height - $compensateHeight / 2, 0, $compensateHeight * -.5);
            $imagick->extentImage($width, $height, 0, 0);
        } else {
            $compensateWidth = $width - ($viewBoxWidth * $ratio);
            $imagick->extentImage($width - $compensateWidth / 2, $height, $compensateWidth * -.5, 0);
            $imagick->extentImage($width, $height, 0, 0);
        }

        if ($border) {
            $imagick->borderImage($background, $border, $border);
            $imagick->resizeImage($width, $height, \imagick::FILTER_LANCZOS, 1);
        }

        $imagick->setImageFormat('png32');

        File::put($exportPath, $imagick->getImageBlob());

        $imagick->clear();
        $imagick->destroy();
    }
}
