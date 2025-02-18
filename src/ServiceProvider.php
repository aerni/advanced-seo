<?php

namespace Aerni\AdvancedSeo;

use Aerni\AdvancedSeo\Data\SeoVariables;
use Aerni\AdvancedSeo\GraphQL\Fields\SeoField;
use Aerni\AdvancedSeo\GraphQL\Queries\SeoDefaultsQuery;
use Aerni\AdvancedSeo\GraphQL\Queries\SeoMetaQuery;
use Aerni\AdvancedSeo\GraphQL\Queries\SeoSitemapsQuery;
use Aerni\AdvancedSeo\GraphQL\Types\AnalyticsDefaultsType;
use Aerni\AdvancedSeo\GraphQL\Types\ComputedMetaDataType;
use Aerni\AdvancedSeo\GraphQL\Types\ContentDefaultsType;
use Aerni\AdvancedSeo\GraphQL\Types\FaviconsDefaultsType;
use Aerni\AdvancedSeo\GraphQL\Types\GeneralDefaultsType;
use Aerni\AdvancedSeo\GraphQL\Types\HreflangType;
use Aerni\AdvancedSeo\GraphQL\Types\IndexingDefaultsType;
use Aerni\AdvancedSeo\GraphQL\Types\RawMetaDataType;
use Aerni\AdvancedSeo\GraphQL\Types\RenderedViewsType;
use Aerni\AdvancedSeo\GraphQL\Types\SeoDefaultsType;
use Aerni\AdvancedSeo\GraphQL\Types\SeoMetaType;
use Aerni\AdvancedSeo\GraphQL\Types\SeoSitemapsType;
use Aerni\AdvancedSeo\GraphQL\Types\SeoSitemapType;
use Aerni\AdvancedSeo\GraphQL\Types\SiteDefaultsType;
use Aerni\AdvancedSeo\GraphQL\Types\SitemapAlternatesType;
use Aerni\AdvancedSeo\GraphQL\Types\SocialImagePresetType;
use Aerni\AdvancedSeo\GraphQL\Types\SocialMediaDefaultsType;
use Aerni\AdvancedSeo\Models\Defaults;
use Aerni\AdvancedSeo\Stache\SeoStore;
use Aerni\AdvancedSeo\View\CascadeComposer;
use Facades\Statamic\Console\Processes\Composer;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Statamic\Facades\CP\Nav;
use Statamic\Facades\Git;
use Statamic\Facades\GraphQL;
use Statamic\Facades\Permission;
use Statamic\Facades\Site;
use Statamic\Facades\User;
use Statamic\GraphQL\Types\EntryInterface;
use Statamic\GraphQL\Types\TermInterface;
use Statamic\Providers\AddonServiceProvider;
use Statamic\Stache\Stache;
use Statamic\Statamic;

class ServiceProvider extends AddonServiceProvider
{
    protected $actions = [
        Actions\Statamic\GenerateSocialImages::class,
    ];

    protected $policies = [
        \Aerni\AdvancedSeo\Data\SeoVariables::class => \Aerni\AdvancedSeo\Policies\SeoVariablesPolicy::class,
    ];

    protected $routes = [
        'actions' => __DIR__.'/../routes/actions.php',
        'cp' => __DIR__.'/../routes/cp.php',
        'web' => __DIR__.'/../routes/web.php',
    ];

    protected $vite = [
        'input' => [
            'resources/js/cp.js',
            'resources/css/cp.css',
        ],
        'publicDirectory' => 'resources/dist',
        'hotFile' => __DIR__.'/../resources/dist/hot',
    ];

    public function bootAddon(): void
    {
        $this
            ->bootStacheStore()
            ->bootNav()
            ->bootPermissions()
            ->bootGit()
            ->bootViewCascade()
            ->bootBladeDirective()
            ->bootGraphQL()
            ->bootMigrations()
            ->autoPublishConfig();
    }

    public function register(): void
    {
        $this->usesEloquentDriver()
            ? $this->registerEloquentDriver()
            : $this->registerStacheDriver();
    }

    protected function usesEloquentDriver(): bool
    {
        return Composer::isInstalled('statamic/eloquent-driver')
            && config('statamic.eloquent-driver.advanced_seo.driver') === 'eloquent';
    }

    protected function registerEloquentDriver(): void
    {
        $config = array_merge([
            'driver' => 'eloquent',
            'model' => \Aerni\AdvancedSeo\Eloquent\SeoDefaultModel::class,
        ], config()->get('statamic.eloquent-driver.advanced_seo', []));

        config()->set('statamic.eloquent-driver.advanced_seo', $config);

        Statamic::repository(Contracts\SeoDefaultsRepository::class, Eloquent\SeoDefaultsRepository::class);

        $this->app->bind('statamic.eloquent.advanced_seo.model', function () {
            return config('statamic.eloquent-driver.advanced_seo.model');
        });
    }

    protected function registerStacheDriver(): void
    {
        Statamic::repository(Contracts\SeoDefaultsRepository::class, \Aerni\AdvancedSeo\Stache\SeoDefaultsRepository::class);
    }

    protected function bootStacheStore(): self
    {
        $seoStore = app(SeoStore::class)->directory(config('advanced-seo.directory'));

        app(Stache::class)->registerStore($seoStore);

        return $this;
    }

    protected function bootNav(): self
    {
        Nav::extend(function ($nav) {
            Defaults::enabled()
                ->filter(fn ($default) => $default['set']->availableInSite(Site::selected()->handle()))
                ->filter(fn ($default) => User::current()->can('view', [SeoVariables::class, $default['set']]))
                ->groupBy('type')
                ->each(function ($defaults, $type) use ($nav) {
                    $nav->create(ucfirst($type))
                        ->section('SEO')
                        ->route("advanced-seo.{$type}.index")
                        ->active("advanced-seo/{$type}")
                        ->icon($defaults->first()['type_icon'])
                        ->children(
                            $defaults->map(function ($default) use ($nav, $type) {
                                return $nav->item($default['title'])
                                    ->route("advanced-seo.{$default['type']}.edit", $default['handle'])
                                    ->active("advanced-seo/{$type}/{$default['handle']}");
                            })->toArray()
                        );
                });
        });

        return $this;
    }

    protected function bootPermissions(): self
    {
        Permission::extend(function () {
            Permission::group('advanced-seo', 'Advanced SEO', function () {
                Defaults::enabled()->groupBy('type')->each(function ($items, $group) {
                    Permission::register("view seo {$group} defaults", function ($permission) use ($group, $items) {
                        $permission
                            ->label('View '.ucfirst($group))
                            ->children([
                                Permission::make('view seo {group} defaults')
                                    ->label('View :group')
                                    ->replacements('group', function () use ($items) {
                                        return $items->map(function ($item) {
                                            return [
                                                'value' => $item['handle'],
                                                'label' => $item['title'],
                                            ];
                                        });
                                    })
                                    ->children([
                                        Permission::make('edit seo {group} defaults')
                                            ->label('Edit :group'),
                                    ]),
                            ]);
                    });
                });
            });
        });

        return $this;
    }

    protected function bootGit(): self
    {
        if (config('statamic.git.enabled')) {
            Git::listen(\Aerni\AdvancedSeo\Events\SeoDefaultSetSaved::class);
        }

        return $this;
    }

    protected function bootViewCascade(): self
    {
        View::composer([
            ...Arr::wrap(config('advanced-seo.view_composer', '*')),
            'advanced-seo::head',
            'advanced-seo::body',
            'social_images.*',
        ], CascadeComposer::class);

        return $this;
    }

    protected function bootBladeDirective(): self
    {
        Blade::directive('seo', function ($tag) {
            return "<?php echo \Facades\Aerni\AdvancedSeo\Tags\AdvancedSeoDirective::render($tag, \$__data) ?>";
        });

        return $this;
    }

    protected function bootGraphQL(): self
    {
        if (config('statamic.graphql.enabled') && config('advanced-seo.graphql')) {
            GraphQL::addQuery(SeoDefaultsQuery::class);
            GraphQL::addQuery(SeoMetaQuery::class);
            GraphQL::addQuery(SeoSitemapsQuery::class);

            GraphQL::addType(AnalyticsDefaultsType::class);
            GraphQL::addType(ComputedMetaDataType::class);
            GraphQL::addType(ContentDefaultsType::class);
            GraphQL::addType(FaviconsDefaultsType::class);
            GraphQL::addType(GeneralDefaultsType::class);
            GraphQL::addType(HreflangType::class);
            GraphQL::addType(IndexingDefaultsType::class);
            GraphQL::addType(RawMetaDataType::class);
            GraphQL::addType(RenderedViewsType::class);
            GraphQL::addType(SeoDefaultsType::class);
            GraphQL::addType(SeoMetaType::class);
            GraphQL::addType(SeoSitemapsType::class);
            GraphQL::addType(SeoSitemapType::class);
            GraphQL::addType(SiteDefaultsType::class);
            GraphQL::addType(SitemapAlternatesType::class);
            GraphQL::addType(SocialImagePresetType::class);
            GraphQL::addType(SocialMediaDefaultsType::class);

            GraphQL::addField(EntryInterface::NAME, 'seo', fn () => (new SeoField)->toArray());
            GraphQL::addField(TermInterface::NAME, 'seo', fn () => (new SeoField)->toArray());
        }

        return $this;
    }

    protected function bootMigrations(): self
    {
        $this->publishes([
            __DIR__.'/../database/migrations/2025_02_05_100000_create_advanced_seo_defaults_table.php' => database_path('migrations/2025_02_05_100000_create_advanced_seo_defaults_table.php'),
        ], 'statamic-eloquent-advanced-seo-migrations');

        return $this;
    }

    protected function autoPublishConfig(): self
    {
        Statamic::afterInstalled(function ($command) {
            $command->call('vendor:publish', [
                '--tag' => 'advanced-seo-config',
            ]);
        });

        return $this;
    }
}
