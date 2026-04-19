=== SEO for Korean ===
Contributors: dalsoop
Tags: seo, korean, naver, schema, sitemap
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.0
Stable tag: 0.3.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Korean-first WordPress SEO. Google + Naver, with Korean-language readability checks no global plugin offers.

== Description ==

SEO for Korean is a complete SEO toolkit built for the Korean web — where Naver, not just Google, decides who gets traffic.

**The 35-check content analyzer** runs in the Gutenberg sidebar and updates as you type. Length, keyword distribution, headings, images, links, slug, schema-readiness — all the standard SEO signals, plus four Korean-specific checks no English-first plugin can do:

* **Ending consistency** — flags 해요체 / 합쇼체 mixing
* **Transition words** — counts Korean connectors (그러나, 따라서, 즉…)
* **Hanja ratio** — warns when 한자 use exceeds 5%
* **Informal text** — catches ㅋㅋ ㅠㅠ 헐 대박 chat-style markers
* **Passive voice** — 26 Korean passive markers

Korean particles are handled in keyword matching: search for "워드프레스" and the analyzer correctly counts "워드프레스를", "워드프레스의", "워드프레스가", and 22 other particle suffixes.

**11 schema.org types** auto-emit as a single JSON-LD `@graph` block:
WebSite, Organization, Person, BlogPosting / NewsArticle / Article, BreadcrumbList, FAQPage (auto-detect from Q&A patterns), HowTo (from numbered lists), Review (from ★ ratings), Recipe (with ingredient & instruction extraction from "재료:" and "만드는 법:"), Event (from "일시:" / "장소:"), VideoObject (YouTube / Vimeo embeds).

**Comprehensive sitemap suite** — `/sitemap.xml` index plus per-content-type sub-sitemaps (posts, pages, categories, tags). Replaces WP core's `/wp-sitemap.xml`. Submit one URL to both Google Search Console and Naver Search Advisor.

**Naver-specific extras** — `naver-site-verification` meta tag, `/sitemap-naver.xml` legacy URL, KakaoTalk sharing image hint when featured images fall below 300×300.

**Title & meta templates** — 11 variables (`%title%`, `%sitename%`, `%separator%`, `%excerpt%`, `%category%`, `%focuskw%`…) usable across single posts, pages, categories, tags, search results, 404 pages, and home.

**Image SEO** — auto-fills missing alt text from attachment title → cleaned filename → parent post title. Korean filename heuristics (스크린샷, 화면 캡처) skip default camera/screenshot names.

**Pattern-based redirections** — exact, prefix, and regex matching with 301/302/307/308/410 status codes. Paired with a 404 monitor that logs hit URLs (top 50, LRU eviction) for later promotion to redirect rules.

== Installation ==

1. Download the latest release zip from [GitHub releases](https://github.com/dalsoop/seo-for-korean/releases).
2. Upload via WordPress admin → Plugins → Add New → Upload Plugin.
3. Activate. Ten modules turn on by default — no further configuration required to start scoring posts.
4. Open the Gutenberg editor on any post; the SEO for Korean sidebar panel shows the live score.

For Naver Search Advisor verification, set the meta tag value via filter in your theme's `functions.php`:

`add_filter( 'sfk/naver_meta/site_verification', fn() => 'YOUR_NAVER_CODE' );`

== Frequently Asked Questions ==

= Does this replace Yoast / RankMath? =

For Korean sites, yes — it covers the same surface and adds the Korean-language checks they don't.

= Does it work with English content? =

The structural checks (length, headings, images, links, schema, sitemap) work for any language. The Korean-specific readability checks return "na" status for English content rather than producing meaningless results.

= Does it call any external services? =

No, by default. An optional morphology gateway can be configured for Korean keyword analysis (currently regex-based; lindera+ko-dic morphology planned). Without it, all analysis runs locally in PHP.

= Where is the settings UI? =

V1 has no admin settings page — power-user configuration is via filter hooks. A React-based admin page is on the roadmap for V2.

== Changelog ==

= 0.3.3 =
* Fix: main Sitemap module now registers one specific rewrite per provider instead of a catch-all regex. The legacy /sitemap-naver.xml URL no longer gets swallowed by the broader pattern.

= 0.3.2 =
* Fix: /sitemap-naver.xml no longer 404s — query-var name collided with the comprehensive sitemap module (both used 'sfk_sitemap'). Naver-sitemap now uses 'sfk_naver_sm'.

= 0.3.1 =
* Fix: /sitemap.xml and /sitemap-naver.xml no longer 301-redirect to a trailing-slash variant — short-circuits WP canonical redirect for sitemap URLs.

= 0.3.0 =
* Settings UI: new Redirections tab — add/remove/toggle redirect rules from the admin (no more code-only configuration).
* Settings UI: new 404 Log tab — see the top 50 requested-but-missing URLs sorted by hit count, promote any entry to a redirect rule with one click.
* New REST endpoints: GET / DELETE /seo-for-korean/v1/404-log.

= 0.2.0 =
* New: Settings UI under Settings → SEO for Korean (React-based, 3 tabs: Modules / Templates / Naver).
* Module on/off toggles surface in admin instead of code-only.

= 0.1.0 =
* Initial release.
* 10 modules: content-analyzer, head-meta, schema, sitemap, templates, images, redirections, monitor-404, naver-meta, naver-sitemap.
* 35 SEO checks across 8 domains.
* 11 schema.org types with auto-detection (Recipe, Event, VideoObject, Review, FAQPage, HowTo, Article/BlogPosting/NewsArticle, BreadcrumbList, Person, Organization, WebSite).
* Korean-specific: 해요체/합쇼체 consistency, transition words, 한자 ratio, informal text, passive voice, particle-aware keyword matching.

== Upgrade Notice ==

= 0.3.0 =
Adds Redirections + 404 Log admin tabs. No breaking changes.

= 0.2.0 =
Adds admin Settings UI. No breaking changes — existing configurations carry over.

= 0.1.0 =
Initial release.
