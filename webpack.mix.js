const mix = require('laravel-mix')

mix.setPublicPath('resources/dist')
    .js('resources/js/cp.js', 'js')
    .vue()
// .postCss('resources/css/cp.css', 'css', [require('tailwindcss')])

if (mix.inProduction()) {
    mix.version()
}
