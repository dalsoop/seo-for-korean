# SEO for Korean

> Korean WordPress SEO. Naver-aware, AI-assisted, GPL.

[![License: GPL v2+](https://img.shields.io/badge/License-GPL%20v2%2B-blue.svg)](https://www.gnu.org/licenses/gpl-2.0)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.0-8892BF.svg)](https://www.php.net/)
[![WordPress](https://img.shields.io/badge/wordpress-%3E%3D6.0-21759b.svg)](https://wordpress.org/)

## Architecture

```
seo-for-korean/
├── seo-for-korean.php          Main plugin file (header, constants, bootstrap)
├── uninstall.php                 Cleanup on uninstall
├── includes/
│   ├── class-plugin.php          Singleton, hook registration
│   ├── class-modules.php         Module manager (enable/disable per feature)
│   ├── class-helper.php          Settings/capability/asset helpers
│   └── modules/
│       └── example/              Reference module — copy to scaffold new ones
├── assets/
│   └── admin/js/
│       └── editor-sidebar.jsx    Gutenberg sidebar entry
├── languages/                    Translations (.pot, .po, .mo)
└── vendor/                       Composer dependencies (if any)
```

## Adding a Module

1. `cp -r includes/modules/example includes/modules/your-slug`
2. Rename namespace `Modules\Example` → `Modules\YourSlug` and class `Example_Module` → `Your_Module`
3. Register in `includes/class-plugin.php → register_modules()`:

   ```php
   $modules = apply_filters( 'sfk/modules', [
       'example'   => Modules\Example\Example_Module::class,
       'your-slug' => Modules\YourSlug\Your_Module::class,
   ] );
   ```

4. Activate it in plugin settings, or via:
   ```php
   \SEOForKorean\Plugin::instance()->modules()->activate( 'your-slug' );
   ```

## Build

```bash
composer install
npm install
npm run build           # outputs assets/admin/js/editor-sidebar.js
```

## Testing

```bash
composer test           # PHPUnit
composer lint           # PHP_CodeSniffer (WPCS)
```

## License

GPL-2.0-or-later. See [LICENSE](./LICENSE).
