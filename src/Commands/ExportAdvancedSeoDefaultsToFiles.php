<?php

namespace Aerni\AdvancedSeo\Commands;

use Aerni\AdvancedSeo\Contracts\SeoDefaultsRepository as SeoDefaultsRepositoryContract;
use Aerni\AdvancedSeo\Eloquent\SeoDefaultSet;
use Aerni\AdvancedSeo\Stache\SeoDefaultsRepository;
use Closure;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Facade;
use Statamic\Console\RunsInPlease;
use Statamic\Statamic;

class ExportAdvancedSeoDefaultsToFiles extends Command
{
    use RunsInPlease;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'statamic:eloquent:export-advanced-seo';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Exports eloquent-based Advanced SEO defaults to flat files.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->usingDefaultRepositories(fn () => $this->exportAdvancedSeoDefaults());

        return 0;
    }

    private function usingDefaultRepositories(Closure $callback)
    {
        Facade::clearResolvedInstance(SeoDefaultsRepositoryContract::class);

        Statamic::repository(SeoDefaultsRepositoryContract::class, SeoDefaultsRepository::class);

        $callback();
    }

    private function exportAdvancedSeoDefaults()
    {
        $this->withProgressBar(app('statamic.eloquent.advanced_seo.model')::all(), function ($model) {
            SeoDefaultSet::fromModel($model)->save();
        });

        $this->newLine();
        $this->info('Advanced SEO defaults exported');
    }
}
