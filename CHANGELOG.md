# Changelog

All notable changes to MCP Tools for Elementor are documented in this file.

## [Unreleased]

### v1.7.0 — Phase D: Verification / Accuracy System (2026-04-18)

Adds the self-correction loop: the importer now tells Claude HOW WELL the import went + WHAT to fix before the next attempt. Two new MCP tools, a composite fidelity_score in the existing `import-design` response, and a standalone DOM-diff CLI utility.

**1. New MCP tool `elementor-mcp/audit-imported-page`**
- Input: `{post_id}`
- Reads `_elementor_data` JSON, walks the widget tree, collects per-widget settings coverage
- Also reads active kit's `_elementor_page_settings` to detect EMCP palette + typography bindings
- Returns:
  - `fidelity_score` (0-100 composite): 30% native-pct + 20% padding-pct + 20% color-pct + 15% typography-pct + 5% class-pct + 5% palette-bound + 5% typography-bound
  - `widget_counts` per type, `widget_total`
  - `widget_native_pct`, `widgets_with_padding_pct`, `widgets_with_color_pct`, `widgets_with_typo_pct`, `widgets_with_class_pct`
  - `palette_bound`, `typography_bound` booleans
  - `hint` (excellent/good/fair/poor + action suggestion)
- Readonly + idempotent; `check_edit_permission`
- Use-case: run after `import-design` to verify the save landed with expected widget types + styling. If score drops between imports → regression.

**2. New MCP tool `elementor-mcp/lint-html`**
- Input: `{html}` — raw input before any import
- Pure analysis, zero WP state touched. Readonly, `__return_true` permission.
- Runs DOMDocument parse + scans for:
  - DOM parse errors/warnings (unclosed tags, etc.)
  - Images total / external / missing alt
  - `<style>` class rules + @-rule counts (predicts `css_rule_unresolved` and `at_rule_ignored` outcomes)
  - Inline style elements, script tags, iframes (+ video iframes)
  - Widget-map coverage estimate (% of elements in the covered-tag set)
- Returns `go_no_go` (`go`/`caution`/`no_go`), `estimated_coverage` (0-100), structured `warnings[]` with `{severity, code, message}`, detailed `stats`
- Warning codes: `dom_parse_failed`, `dom_parse_warning`, `missing_alt_text`, `external_images`, `at_rules_dropped`, `script_tags`, `no_styles`
- Use-case: Claude calls `lint-html` → if go_no_go=`no_go`, fix HTML first; if `caution`, proceed but expect warnings in unmapped_elements.

**3. Fidelity score composite on `import-design` response**
- Extended `compute_import_metrics()` to emit 7 new fields:
  - `widget_coverage_pct` (existing, weighted 30%)
  - `style_coverage_pct` (new) — penalty per unresolved `css_rule_unresolved` unmapped entry, weighted 25%
  - `token_binding_pct` (new) — 100 if palette bound, 0 if needed-but-skipped, weighted 20%
  - `image_resolution_pct` (new) — `sideloaded ÷ (sideloaded + skipped)`, weighted 15%
  - Unmapped penalty (10%) — scaled inverse of `no_rule_leaf` count
  - `fidelity_score` (0-100 composite)
  - `suggested_actions[]` — per-reason remediation strings ("Add data-emcp-widget to N elements", "Inline N class rules", etc.)
- Both dry-run + final save paths return the full set

**4. DOM structural diff — `tests/fidelity/dom-diff.php`**
- CLI utility: `php tests/fidelity/dom-diff.php --input=path.html --rendered=http://site.local/page/`
- Parses input HTML + fetched Elementor frontend output via DOMDocument
- Strips Elementor auto-classes (`elementor-*`, `e-*`) so only author classes compared
- Computes histogram overlaps: tags (weight 0.5), classes (weight 0.3), depth similarity (weight 0.2) → 0-100 score
- Exit code 0 when score ≥ 75, 2 otherwise (CI-friendly)
- Output JSON includes `missing_tags`, `missing_classes`, `extra_tags`, `extra_classes` so regressions point at exact divergence
- Self-test verified: identical fixtures = 100; `section + button + a` stripped out = 46, exit=2

**Feedback loop architecture now complete:**
1. **Pre-import**: `lint-html` → go/caution/no_go + warnings
2. **During import**: `import-design` → `fidelity_score` + `suggested_actions[]` + `unmapped_elements[]`
3. **Post-import**: `audit-imported-page` → independent score from saved `_elementor_data`
4. **Regression detection**: `dom-diff.php` → structural similarity between source HTML and frontend output
5. Claude can chain: lint → fix → import → audit → if score < threshold → re-annotate → re-import

**Tests:** 62/62 core smoke still green; dom-diff self-test 100/100 + 46/100 as expected. `php -l` clean on all edited files.

**Version bump:** `ELEMENTOR_MCP_VERSION` → `1.7.0`.

---

### v1.6.0 — Phase C: Fidelity (2026-04-18)

Closes the HTML→Elementor round-trip gap so real-world designs render ≈90% intact in Elementor. Five coordinated pieces:

**1. Widget emitter expansion** — 5 new emitters + rules in `widget-map.php`:
- `<iframe src="youtube|vimeo">` and `<video>` → **video** widget (YT/Vimeo ID extracted, autoplay/mute/loop honoured for hosted)
- `<progress>` and `.progress-bar` → **progress** widget (reads `value`/`max`, inline `width:N%`, or `data-progress`)
- `<nav>/<ul>/<div class="social-icons">` → **social-icons** widget (12-platform domain+class match: facebook/twitter/x/instagram/linkedin/youtube/tiktok/pinterest/github/whatsapp/telegram)
- `.swiper`/`.slick-slider`/`.carousel`/`.owl-carousel` → **image-carousel** widget (pulls all descendant `<img>` srcs as slides)
- `<div class="tabs">` with `<li>` titles + `.tab-content` panels → **tabs** widget
- New match condition: `attr_contains: [attr_name, 'regex|alts']` — attribute value regex matcher (used for iframe src URL matching)
- Gotcha: social-icons rule ordered BEFORE generic `<nav>→icon-list` (rule 5a vs 5b) so `<nav class="social-icons">` routes correctly

**2. Media sideloader** — new `Elementor_MCP_Design_Importer::sideload_external_images()`:
- Pre-pass runs right after DOM parse, before widget walk
- Every external `<img src="https://…">` (non-same-host, non-`data:`) downloaded via `download_url` + `media_handle_sideload`
- On success: `src` rewritten to local WP URL + `data-emcp-attachment-id` attribute stamped
- On failure: entry added to `unmapped_elements` with `reason: 'image_sideload_failed'`, image left external, non-fatal
- Image extractors (`image`, `figure`, `wrapped_image`) now pick up attachment ID → Elementor native image widget with media library reference
- New stats: `images_sideloaded`, `images_skipped`
- New MCP input param: `sideload_images` (boolean, default `true`); auto-disabled in `dry_run` mode
- Alt text copied to `_wp_attachment_image_alt` postmeta during sideload

**3. CSS class-rule resolver** — new helper `includes/design/helpers/css-rule-resolver.php`:
- `emcp_css_build_rule_map(string)` — parses `<style>` block into ordered `[{selector, decls}]` list
- `emcp_css_selector_matches(string, DOMElement)` — tests selector against element; supports `.class`, `.a.b` AND, `.parent .child` descendant, `div.class` tag+class compound
- `emcp_css_resolve_element_style(DOMElement, rule_map)` — walks rules in source order, merges matching declarations (later prop wins)
- `emcp_css_current_rule_map($set)` — ambient accessor (static): Design_Importer publishes rule map at import start, clears at end
- Integrated into `emcp_design_extractor_container()`: class-rule styles merged UNDER inline style → single effective-style string fed through `emcp_parse_inline_styles()` → Elementor settings
- Explicitly unsupported (silent miss): `@media`/`@supports`/`@keyframes` (stripped), element-only selectors (`div {…}`), `[attr=x]`, `:hover/:focus`, `> + ~` combinators
- Previously these dropped-styles lived only in `unmapped_elements` with `reason: 'css_rule_unresolved'` (Quick-Win QW4). Now resolved rules actually apply.

**4. CSS function support** in `inline-style-parser`:
- `emcp_inline_style_current_vars($set)` — ambient `--css-var → value` map, published by Design_Importer from css-var-extractor output
- New `emcp_style_resolve_functions(string)`:
  - `var(--name)` — lookup with 8-level depth cap (prevents cycles)
  - `var(--name, fallback)` — honors fallback
  - `rgba(var(--hex-var)/alpha)` and `rgba(var(--hex-var), alpha)` → resolve var, expand hex to `rgba(r,g,b,a)` numeric
  - `calc(24px)` single-term unwrap → `24px` (multi-term passes through unchanged)
- Wired into `emcp_style_parse_props` value normalization loop

**5. Typography kit binder** — new method `Elementor_MCP_Kit_Binder::bind_typography_array($name, $families)`:
- Writes to kit's `system_typography` (same postmeta pattern as `system_colors`)
- Maps 4 Elementor slots: `primary` (display/heading/headline/title), `secondary` (secondary/sub/caption), `text` (body/paragraph/text), `accent` (accent/mono/brand)
- Overflow families returned as `{overflow: {slot: cfg}}` → surfaced to `unmapped_elements` with `reason: 'typography_slot_overflow'`
- Called from `execute_import_design()` alongside existing `bind_palette_array()`
- New response field: `typography_bound_slots` (int)
- Heuristic mapping in `typography_families_from_tokens()` uses token-name substrings; unmatched families fill remaining slots left-to-right

**Test coverage:**
- 62/62 core smoke (`tests/smoke-design-load.php`)
- 17/17 Phase C widget extractor integration checks (video YT/Vimeo/hosted, progress, .progress-bar, social-icons + platform detection, carousel, tabs)
- 15/15 CSS resolver unit checks (rule-map build, selector matching incl. descendant/compound, resolve order, ambient accessor get/clear)
- 8/8 `var()`/`calc()`/`rgba(var())` checks (hex lookup, fallback, transitive chain, single-term calc unwrap, rgba expansion)
- `php -l` syntax clean on all 6 touched files

**Gotcha 26: Ambient static state across imports.** Both `emcp_css_current_rule_map` and `emcp_inline_style_current_vars` use `static` variables so free-function extractors can read without threading args. Design_Importer MUST call them with `null` at end of each `import()` to avoid second-import leakage. Both already wired.

**Gotcha 27: Operator precedence on sideload gate.** First draft wrote `if ( $sideload_images && fn_exists('a') || fn_exists('b') )` which due to `||` < `&&` precedence always entered the branch when fn_b existed even if user opted out. Fix: simplified to `if ( $sideload_images )` and moved the WP-function check inside `sideload_external_images()`.

**Version bump:** `ELEMENTOR_MCP_VERSION` → `1.6.0`.

---

### v1.5.1 — Quick Wins pre-Phase-C (2026-04-18)

Five surgical fixes raising importer fidelity + feedback before the bigger Phase C work lands.

- **QW1 — Conditional wrapper defaults.** Importer's outer wrapper no longer hardcodes `padding=0` + `flex_gap=0` when `<body>` carries an inline `style="…"`. Previously the parsed body-level spacing/background was clobbered by the reset. Now: no body style → reset (old behaviour, prevents theme bleed); body style present → merge parsed values, preserve `css_classes`. File: `class-design-importer.php`.

- **QW2 — `!important` stripped in `emcp_style_parse_props`.** Real-world HTML ships `color:#fff !important;` — Elementor controls have their own specificity so we drop the bang-keyword before value mapping. File: `helpers/inline-style-parser.php`.

- **QW3 — Two new widget-map rules + extractors**:
  - `<hr>` → **divider** widget (`emcp_design_extractor_divider`). Pulls color from inline border-color/color.
  - `<div class="spacer|gap|gutter">` or Tailwind-like `.space-y-4` with no element children → **spacer** widget (`emcp_design_extractor_spacer`). Height pulled from inline `height` / `min-height`.
  - `widget-map.php` renumbered: old rules 15/16 → 17/18. New rule 15 = divider, rule 16 = spacer.

- **QW4 — `<style>` class rules surfaced to `unmapped_elements`.** Previously `.hero__title{font-size:80px}` in an input stylesheet was dropped silently (only `:root` vars were captured via css-var-extractor). Now every class rule in a `<style>` block becomes an `unmapped_elements` entry with `reason: 'css_rule_unresolved'` + a hint to inline the style on the matching element. Naive regex tokenizer (`Elementor_MCP_Design_Importer::extract_class_rules`) skips `:root`, `@media`, `@supports`. Phase C replaces it with a real resolver.

- **QW5 — Fidelity metrics in `import-design` response.** New fields:
  - `widget_coverage_pct` (int 0-100) — `native_widgets ÷ (native + html_fallback)`.
  - `fidelity_hint` (string) — `excellent|good|fair|poor` + actionable next step.
  - Added to both `dry_run` and final return. Output schema + helper `Elementor_MCP_Design_Abilities::compute_import_metrics()` added.

**Verification:**
- `php tests/smoke-design-load.php` → 62/62 pass
- Ad-hoc integration checks → 8/8 (!important strip, divider/spacer extraction, rule routing)
- `php -l` syntax clean on all 4 edited files

**Version bump:** `ELEMENTOR_MCP_VERSION` → `1.5.1`.

---

### Inline Style Parser + dry_run mode (2026-04-18)

Fixes the #1 fidelity gap in the HTML→Elementor importer: previously all `style="…"` attributes on imported containers were silently dropped, producing plain white boxes regardless of the design's intended colours, spacing, and layout.

**New file: `includes/design/helpers/inline-style-parser.php`**
Parses any CSS `style` attribute → Elementor settings array ready to `array_merge` into widget/container settings. Covered properties:
- `background-color` / `background` (solid colour) → `background_background: classic` + `background_color`
- `padding` / `margin` — full shorthand (1/2/3/4 values) + individual longhands → Elementor dimension array `{top,right,bottom,left,unit,isLinked}`
- `border-radius` — shorthand + per-corner longhands → `border_radius` dimension array
- `min-height` / `height` → `min_height` size array; `max-width` → `content_width: custom` + `custom_width`
- `color` → `color`; `font-size/weight/line-height/letter-spacing/text-align/text-transform` → typography controls
- `opacity` → `opacity` size array
- `border` shorthand → `border_width` + `border_color`
- `display:flex` + `flex-direction/align-items/justify-content/flex-wrap` → Elementor flex controls
- `gap` / `column-gap` / `row-gap` → `flex_gap`

**Integration:**
- `emcp_design_extractor_container()` in `widget-map.php` now merges `emcp_parse_inline_styles($style)` into container settings (overriding defaults where set, preserving `css_classes` and `_element_id`).
- `elementor-mcp.php`: `require_once` added for new helper.

**`dry_run` mode for `import-design` tool:**
New boolean input param `dry_run` (default `false`). When `true`, parses HTML + produces full stats/unmapped_elements WITHOUT writing to any page or creating any WordPress post. Enables inspect-before-commit workflow: run `dry_run: true` → read `unmapped_elements` → re-annotate HTML with `data-emcp-widget` → run final import.
Missing `page_id`/`title` validation is bypassed in `dry_run` mode.

**Smoke tests:** 29/29 inline-style-parser assertions pass. 62/62 overall smoke test still green.

---

### FAQ Page Visual Parity — Phase A of v1.5.0 fidelity upgrade (2026-04-18)

Immediate fix for the `/faq/` page losing design fidelity because the pattern file renamed every design CSS class to a scoped `.emcp-faqpf-*` namespace + dumped 900 lines of CSS inline via an HTML widget.

- **New file: `includes/design/css/faq-page.css`** — verbatim extract of the Claude FAQ design CSS (minus topnav/footer/body-resets). All rules scoped to `.emcp-faqpage` wrapper. Design BEM classes preserved (`.hero__*`, `.pop-card`, `.faq-q__toggle`, `.trust-badge`, `.final-cta__*`) so the pattern's `_css_classes` settings match the CSS out of the box.
- **New palette `emcp-classic-desert`** in `tokens/palettes.php` — maps design's `--sunset`/`--midnight`/`--gold`/`--sand-50`/`--ink-500`/`--line` vars to standard palette slot names.
- **Rewrote `patterns/faq-page-full.php`** — dropped inline `emcp_faq_pf_styles()` CSS dump function. Switched every widget's `_css_classes` + container's `css_classes` from `.emcp-faqpf-*` prefix to design's original BEM classes. Added `_element_id` on section containers so design's `#hero`/`#tabs`/`#faq-accordion`/`#trust`/`#final-cta` selectors match (see Gotcha 25).
- **Extended `class-article-enhancer.php`** — new `enqueue_faq_page_styles()` wp_enqueue_scripts hook detects the `.emcp-faqpage` wrapper via `_elementor_data` postmeta substring check + conditionally enqueues `faq-page.css`. Also loads inside the Elementor editor iframe via existing `elementor/preview/enqueue_styles` + `elementor/editor/after_enqueue_styles` hooks for editor-preview parity.
- **Updated `tests/create-faq-page.php`** palette name → `emcp-classic-desert`.

**Verification**: 42 native widgets + 5 HTML widgets on `/faq/` frontend. All 20 design BEM classes render (hero__crumbs × 1, hero__title × 1, hero__sub × 1, hero__search × 1, hero__hints × 1, hero__dunes × 1, tabs__btn × 1, tabs__count × 1, popular-head × 1, pop-grid × 1, pop-card × 12, faq-aside × 2, faq-cat × 14, faq-cat__head × 7, trust-grid × 1, trust-badge × 4, final-cta__title/sub/actions/note × 1 each). `faq-page.css` enqueued with cache-bust `?ver=1.5.0`.

**Gotcha 24:** **Linters/auto-formatters may preemptively modify a class being edited.** When `Edit` calls fail with "File has been modified since read", the linter likely added a field/method matching the planned change. Re-read before retrying — don't duplicate the logic. Example from this session: `class-design-abilities.php` constructor gained `?Elementor_MCP_Design_Importer $importer = null` parameter mid-edit; the retry just needed to accept that change and continue appending methods.

**Gotcha 25:** **Pattern `_element_id` setting becomes the rendered container's HTML `id=`.** Design CSS that uses `#hero` / `#tabs` / `#final-cta` selectors will NOT match unless each section container has its matching `_element_id` set. Design BEM classes on `css_classes` handle class-based selectors. Miss the `_element_id` and the ID-based CSS silently doesn't apply — no error, just wrong visuals.

---

### Design Importer — elementor-mcp/import-design (2026-04-18)

**New MCP tool:** converts any Claude-generated (or hand-written) HTML into a native Elementor page. Addresses the 500-page scalability gap: unique one-off designs no longer require a new PHP pattern file each.

**Architecture clarification (reaffirmed):**

| Layer | What it is | Lives where | Scale |
|-------|-----------|-------------|-------|
| **Pattern file** | Reusable LAYOUT TEMPLATE | Plugin `includes/design/patterns/*.php` | ~25 total, stable |
| **Design Importer** | One-off HTML → Elementor page | MCP call, zero new PHP files | Unlimited |

Pattern files = correct for REUSABLE page structures (FAQ layout, article hero, CTA banner). Design Importer = correct for ONE-OFF unique pages. For 500 unique pages: call `import-design` 500 times, 0 new PHP files.

**Infrastructure reused (already existed, now loaded and exposed):**
- `includes/design/tokens/css-var-extractor.php` — `emcp_tokens_css_var_extract(string $css)` parses `:root {}` CSS vars → palette/typography/spacing token map
- `includes/design/widget-map.php` — `emcp_design_find_rule(\DOMElement)` ordered rules + extractors

**New file:**
- `includes/design/class-design-importer.php` — DOMDocument walker, accordion-grouping, token extraction, body targeting

**Widget mapping rules (first-match-wins):**
- `data-emcp-widget` attr → forced widget type
- `<details>/<summary>` → accordion (consecutive siblings grouped into ONE widget, Gotcha 23)
- `<a|button>` with `.btn*` class → button
- `.card|.icon-box` + child `<hN>` → icon-box
- `<nav>` → icon-list (links as items)
- `<img>` → image
- `<h1-h6>` → heading
- `<p>` → text-editor
- `<form|svg|script>` → html widget (fallback)
- `<section|div|aside|main>` → container (children walked recursively)

**MCP tool input schema (`elementor-mcp/import-design`):**
```json
{
  "html": "string (optional) — inline HTML string",
  "url":  "string (optional) — fetch HTML from URL",
  "page_id": "integer (optional) — apply to existing page",
  "title": "string (optional) — create new page with this title",
  "post_type": "enum[page,post] default page",
  "post_status": "enum[draft,publish] default draft",
  "skip_header": "bool default true — skip <header>/topnav elements",
  "skip_footer": "bool default true — skip <footer> elements",
  "wrapper_class": "string default emcp-imported-page"
}
```

**Output:** `{post_id, edit_url, native_widgets, html_fallbacks, tokens_extracted}`

**Gotcha 22:** `DOMDocument::loadHTML()` always wraps input in `<html><body>` even when given a fragment. Always target `$doc->getElementsByTagName('body')->item(0)`, never the document root. Use `LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD` flags to suppress spurious `<html><body>` injection when input already has those tags. Use `libxml_use_internal_errors(true)` + `libxml_clear_errors()` to suppress HTML5 tag warnings in PHP error log.

**Gotcha 23:** Elementor `accordion` widget stores ALL Q/A pairs in a single `tabs[]` array `[{tab_title, tab_content, _id}]`. The widget-map extractor returns ONE item's data under `_tab_title`/`_tab_content`/`_tab_id`. The Importer's `collapse_accordion_siblings()` MUST group consecutive `<details>` siblings into ONE accordion widget. NEVER call `create_widget('accordion', ...)` per `<details>` element — that generates N separate one-tab accordions, which is wrong.

---

### Pattern portability — site-agnostic default cleanup (2026-04-18)

**Architecture principle (reaffirmed 2026-04-18):** Pattern files in `includes/design/patterns/*.php` are SITE-AGNOSTIC layout templates. Site-specific content (brand names, phone numbers, pricing, logos, social handles, tagline lines) lives in: (a) `tests/create-*.php` builder scripts, or (b) runtime MCP tool call arguments. Pattern `??` defaults must be **neutral placeholders**, never site branding. Enforced going forward by `tests/smoke-pattern-portability.php`.

**Cleanup applied:**
- `includes/design/patterns/content-author-bio.php` — removed 10 Safari hardcodes. `logo_url` now falls back to `get_site_icon_url()`, `logo_alt` to `get_bloginfo('name')`, `tagline` default `''`, `social` defaults empty strings per platform (`instagram`/`facebook`/`x`/`youtube`/`tiktok`/`pinterest`). Empty platform URL = no icon rendered. New `logo_alt` slot added to docblock.
- `includes/design/patterns/cta-banner-full-width.php` — removed `DTCM / Licensed operator` baked trust_stat default. `trust_stats` default now empty array. `eyebrow` default `'BOOK YOUR ADVENTURE'` dropped to `''`. Docblock retains example for devs.
- `tests/create-faq-page.php` — **new file**. Safari FAQ content (12 Q&A, DTCM trust, WhatsApp +971…, AED pricing) lives here as slot payload, mirroring `tests/create-blog-single-template.php` convention.
- `tests/create-landing-test.php` — now explicitly passes `eyebrow` + `trust_stats` slots (was relying on the defaults being removed).

**Pattern portability grep after cleanup:**
```bash
grep -rn "Safari\|Dubai\|DTCM\|971\|safaridesertdubai" includes/design/patterns/
# expect: 0 hits
```

**Rationale:** Plugin's original purpose (per `project_elementor_design_pipeline.md`) is a generic AI→Elementor page-builder for ANY WordPress site. Baking Safari defaults broke that contract — on a fresh install without slot overrides, callers would see Safari Desert Dubai branding leak through. Test scripts are the correct home for Safari content because they're Safari-site-specific by definition.

---

### FAQ page pattern — native-widget refactor (2026-04-18)

- **New pattern `faq.page-full`** — full FAQ landing page built from Claude design spec (hero with search + popular pills, sticky category tabs with filter JS, 6-up popular cards grid, 7-category accordion library with sticky sidebar index, dark 4-up trust band, twilight-bg final CTA). Scoped CSS via `.emcp-faqpf-*` namespace, design tokens from spec (sunset #E9680C, gold #D4A853, midnight #1A1A2E, Poppins + Playfair Display).
- **Native-widget-first policy** — after user pushback on HTML-dump approach, refactored pattern to use native Elementor widgets wherever Elementor provides an equivalent:
  - `heading` for all headlines + eyebrows + notes (10+ instances)
  - `text-editor` for all subtitle paragraphs (3 instances)
  - `icon-list` inline for breadcrumb + popular pills + aside jump list (3 instances)
  - `icon-box` for 6 popular Q cards + 4 trust badges + 7 category headers (17 instances)
  - `accordion` for 7 category Q/A sections (12 total Q/A tabs)
  - `button` × 2 for final CTA (WhatsApp green + Call orange)
- **HTML widgets retained only where Elementor has no equivalent:** scoped CSS `<style>` block, search `<form>` with `<input>`, decorative SVG dune silhouette, interactive tab-filter nav with custom `data-filter` JS. 4 justified HTML widgets total (vs 16 in prior dump approach).
- **Frontend verification on post 3007:** 61 native-widget rendered classes vs 6 HTML widgets → 10× native ratio. Every headline, subtitle, card, accordion Q/A, button, trust badge now editable individually in Elementor UI panel.

**Gotcha 19:** **Elementor `accordion` widget stores Q/A in `tabs[]` array with keys `tab_title` + `tab_content` + unique `_id` (7-char)**, NOT `q`/`a`. When mapping Q/A from external content source (JSON spec, etc.), transform keys. `tab_content` accepts full HTML (lists, links, strong tags).

**Gotcha 20:** **Elementor `icon-box` widget has 50+ control keys.** Core mapping: `selected_icon{value,library}`, `title_text`, `description_text` (accepts HTML), `link{url,is_external,nofollow}`, `title_size` (h1..h6/div), `position` (left/right/top), `primary_color` (icon + hover), `title_color`, `description_color`, `icon_size{unit,size,sizes}` (`sizes` array required even when empty), typography controls use full `title_typography_*` prefix (NOT `title_*_typography_*`). `description_text` accepts HTML so can embed tag pills + "Read more" links inside a single card.

**Gotcha 21:** **Elementor `icon-list` widget `view: 'inline'` gives horizontal layout with gap control.** Items array keys: `text`, `link{url,is_external,nofollow}`, `selected_icon{value,library}`, unique `_id` (3-7 chars). Empty icon (`{value:'',library:''}`) still reserves space — use for breadcrumb first item by leaving its icon empty and setting separator icons on items 1+.

### Design Pipeline (v1.5.0 work, 2026-04-17)

**New feature: AI-friendly Native-Design Pipeline** — extends the plugin with a token-aware pattern library + Design IR compiler so Claude (or any AI agent) can produce polished, native Elementor pages without hand-writing individual widget settings.

**Architecture layers (all additive, zero touch to existing 97 tools):**

- **Token Resolver** (`includes/design/class-token-resolver.php`) — singleton mapping semantic tokens (palette slots, typography scales, spacing, effects) to Elementor control settings. Emits `__globals__` kit references when palette bound; hex literal fallback otherwise.
- **Pattern Registry** (`includes/design/class-pattern-registry.php`) — glob-discovers `includes/design/patterns/*.php` files. Honors optional `meta['name']` override for compound-category names (e.g. `stats-bar.4-up`).
- **Kit Binder** (`includes/design/class-kit-binder.php`) — writes palette slots to the active Kit's `_elementor_page_settings` postmeta (NOT via `$kit->save()` which no-ops on settings-only input). Flushes files_manager cache.
- **Design Compiler** (`includes/design/class-design-compiler.php`) — converts high-level Design IR (JSON of sections + slots + brand tokens) to `build-page` payload. Auto-resolves `*_image_query` slots via stock image search.
- **Article Enhancer** (`includes/design/class-article-enhancer.php`) — filters `the_content` on EMCP-templated blog posts (detected via `_emcp_generated` postmeta). Injects reading-progress bar, meta strip, auto-TOC, anchor-linked headings. Registers shortcodes: `[emcp_info]`, `[emcp_warn]`, `[emcp_success]`, `[emcp_tip]`, `[emcp_stat]`, `[emcp_pull]`.
- **Article Styles** (`includes/design/css/article-styles.css`) — scoped `.emcp-article` CSS with palette custom properties, editorial typography, callouts, pull-quote, table v2 (rounded + gradient header + zebra + mobile-stack), marks, tag chips, 2-col grid layout, scroll-reveal, dropcap, reading progress.
- **Tokens** (`includes/design/tokens/*.php`) — 3 palettes (desert-warm, luxury-dark, modern-clean), 3 typography scales, semantic spacing, shadow/radius/overlay/divider, 5 button variants.

**18 patterns shipped (Phase 1 + Phase 2):**
- `hero.post-article`, `hero.minimal-center`, `hero.split-image-right`, `hero.gradient-mesh`, `hero.overlay-waves`
- `content.post-body` (2-col with sticky sidebar), `content.author-bio`, `content.related-posts`
- `features.icon-grid-3col`, `features.card-grid-4col`, `features.alternating-image-text`, `features.checklist-2col`
- `stats-bar.4-up`, `logo-cloud.grayscale`, `testimonial.carousel`, `pricing.3-tier`, `faq.accordion-centered`, `gallery.masonry`
- `cta.banner-full-width`

**5 new MCP tools (97 → 102):**
- `elementor-mcp/list-patterns` — catalog of registered patterns
- `elementor-mcp/preview-pattern` — resolve pattern → JSON without saving
- `elementor-mcp/design-page` — compile Design IR + create native Elementor page
- `elementor-mcp/apply-design-to-page` — replace existing page content
- `elementor-mcp/design-theme-template` — create Elementor Pro Theme Builder templates with display conditions

**Critical bugs learned (documented so future sessions skip the traps):**

1. **Elementor Document::save() silently no-ops on settings-only input.** Kit_Binder originally used `$kit->save(['settings' => ...])` and the kit colors never persisted. Fix: write `_elementor_page_settings` postmeta directly with `update_post_meta()`.
2. **Pro Theme Builder conditions expect `[['include','singular','post']]` (array of path-parts arrays), not flat `['include/singular/post']` strings.** Flat strings land in postmeta but Pro's `Conditions_Cache::regenerate()` only fires when `Conditions_Manager::save_conditions()` is called with the correct nested format. Wrong format = template silently ignored.
3. **Elementor container `_css_classes` ≠ `css_classes`.** Container element (Flex) registers its custom-class control as `css_classes` (no leading underscore). Using `_css_classes` on containers stores the value but never emits it on the rendered element. Widgets use `_css_classes` (underscored). Rule: Container → `css_classes`, Widget → `_css_classes`.
4. **`content_width: boxed` caps grid to kit's boxed width (~1140px).** For wider 2-col layouts, use `content_width: full` on the outer container and constrain via CSS `max-width: 1320px; margin: 0 auto`.
5. **Dropcap `::first-letter` doesn't reach into Elementor widget wrappers.** `theme-post-content` widget wraps content in `.elementor-widget-container > div > <real HTML>`. Selector must walk past those wrappers: `.emcp-article .elementor-widget-theme-post-content .elementor-widget-container p[data-first='true']::first-letter`.
6. **`the_content` filter runs BEFORE Elementor renders text-editor widgets.** Server-side H2 extraction sees nothing useful for Elementor-edited posts. Client-side TOC generation (DOM scan after render) is reliable.
7. **Shape-divider color must match adjacent section's background** or a visible gap strip appears. Token_Resolver auto-picks palette `surface` slot as default.

### v3 refinements (mid-session, 2026-04-17)

- **`content.post-body` 2-col layout** — article body LEFT (flex `1fr`, ~1153px wide), sticky sidebar RIGHT (280px). CSS grid via `.emcp-article-layout`. Outer container `content_width: full` (NOT boxed, which caps at kit width).
- **TOC hover + scroll-spy** — TOC links get bg fade, slide-right padding, left-border bar on hover. Hovering a TOC link adds `.emcp-hovered` to the matched H2 (bridge effect). IntersectionObserver tracks visible H2 → adds `.emcp-active` to corresponding TOC link.
- **Related + Recent sections opt-in** — removed from sidebar by default (default template shows TOC only). Re-enable via `show_related=true` / `show_recent=true` slots.
- **Table style v2** — 16px radius, gradient thead (primary→secondary), uppercase TH with 1.03px letter-spacing, zebra rows, hover accent gradient, mobile stack with data-label pseudo.
- **`content.author-bio` redesign** — modern 2-col card: site logo LEFT (96px circle with accent ring + halo), "WRITTEN BY" eyebrow + dynamic author-name + dynamic `author-info` description + site tagline on RIGHT. Top gradient bar (primary→accent) for visual punch. Hover elevate. Default logo: `safaridesertdubailogo-1-1.jpg`.
- **Social icons in author card** — 6-platform row (Instagram, Facebook, X, YouTube, TikTok, Pinterest) via Elementor core `social-icons` widget. Defaults to `safaridesertdubai` handles; override per-platform via `social` slot (empty string hides a platform). Circle icons, 36px, brand-colored idle (IG gradient, FB #1877F2, X black, YT red, TikTok tri-gradient, Pin #E60023) + translateY lift + brightness on hover.
- **Gradient social icons injected via JS, not CSS** — Chrome mysteriously drops `background-image: linear-gradient(...)` for Instagram + TikTok icons even with `!important` + max specificity from stylesheet. Same values applied as inline `element.style.setProperty(prop, val, 'important')` win immediately. Article enhancer's `fixSocialGradients()` runs on DOMContentLoaded + twice more with timeouts to survive lazy/late Elementor widget render. Solid-color platforms (FB/X/YT/Pin) work via CSS fine.
- **Author card full-width fix** — removed fixed `width: 860px` on card, switched to `width: 100%` + `_element_width: initial`. Card now fills article column (~1080px), bumping description width from ~280px → ~500px. Outer section `content_width: full` + inner content column capped at 1100px centered.

**Gotcha 10 (v3):** **Elementor `.elementor-social-icon` IS the anchor** — NOT a child. Selector `a[href*="..."] .elementor-social-icon` never matches. Use `.elementor-social-icon-{platform}` classes: `-instagram`, `-facebook-f`, `-x-twitter`, `-youtube`, `-tiktok`, `-pinterest-p`.

**Gotcha 11 (v3):** **CSS `background-image: linear-gradient` can silently fail in Chrome's cascade** even when `!important` + high-specificity rule is confirmed winning via `document.styleSheets` enumeration. Workaround: apply via JS `element.style.setProperty('background-image', gradient, 'important')`. Solid colors work fine via CSS.

- **`content.related-posts` redesign — "You may also like"** — eyebrow "KEEP READING" (accent uppercase) + heading + 3-card grid with modern hover effects. CSS restyles Elementor Pro `posts` widget classic-skin output:
  - Cards: white bg, 16px radius, 1px subtle border, 0.05 shadow
  - Hover: translateY -6px + 0.12 shadow, image zoom 1.06 over 600ms, subtle dark gradient overlay
  - Title: 19px brand text (NOT bright orange), hover → primary. Old size was 42px (inherited from Elementor widget typography default)
  - Meta: uppercase 11px muted, date only (📅 icon prefix)
  - Read more: "Read more →" primary-colored, dashed top-border separator, shifts right +3px on hover

**Gotcha 12 (v3):** **Elementor Pro `posts` widget classic skin emits `.elementor-post-avatar` class for the comments-count meta** (weird naming — it's not an avatar). Setting `classic_show_meta_data: ['date']` doesn't always suppress it. Hide via CSS: `.elementor-post-avatar { display: none !important }`.

**Gotcha 13 (v3):** **Elementor widget title `<h3>` gets font-size from widget's typography control, not your stylesheet** — even with class selectors and `!important`. Override with tag+class combo `h3.elementor-post__title` and explicit `font-size: NNpx !important`, NOT `font-size: inherit` (which cascades to body default). Don't mix color-only rules with `font-size: inherit` or title shrinks to 16px.

- **Related-posts image zoom crop fix** — `.elementor-post__thumbnail__link` wrapper now uses `aspect-ratio: 16/10` + `overflow: hidden`, nested `.elementor-post__thumbnail` absolute-positioned full-cover, `img` with `width/height: 100%` + `object-fit: cover` + `object-position: center`. Before: image showed partial crop on one side with blank gradient on other (Elementor's native thumbnail ratio slider + letterboxing + `transform: scale` combo looked broken on hover).

**Gotcha 14 (v3):** **Elementor Pro `posts` classic skin `classic_image_ratio` (0..2 slider)** produces unpredictable cropping when image native ratio ≠ slider ratio. Reliable path: force container `aspect-ratio` CSS + absolute-position thumbnail inside + `object-fit: cover` on img. Don't rely on Elementor's letterboxing.

- **Editor preview parity fix** — scoped frontend CSS (`article-styles.css`) now also loads inside Elementor editor iframe via `elementor/preview/enqueue_styles` + `elementor/editor/after_enqueue_styles` actions. Previously only `wp_enqueue_scripts` (frontend) was hooked, causing designers to see "old raw widget look" in editor while frontend showed polished design → "where did my changes go?" confusion.

**Gotcha 15 (v3):** **`is_singular()` returns false inside Elementor editor preview** (template is being edited, not rendered). Using it as an early-return condition on `wp_enqueue_scripts` hooks kills styles inside the editor. Fix: hook `elementor/preview/enqueue_styles` separately with unconditional enqueue so design parity survives from frontend → editor iframe.

- **`cta.banner-full-width` featured-image overlay** — CTA now binds post's featured image as background via `__dynamic__` tag (`post-featured-image`) + dark gradient overlay (rgba 0→black, 0.78 opacity). 60vh min-height, eyebrow pill, white headline, subhead, dual buttons (white primary + outline secondary), trust line. Radial glow pseudo + hover lift via scoped `.emcp-cta-*` CSS. Slots: `use_featured_image` (default true), `bg_image` override, `overlay_opacity`.

**Gotcha 16 (v3):** **Elementor flex container key is `flex_align_items` — NOT `align_items`.** Using plain `align_items` stores the value but Elementor never maps it to `--align-items` CSS var, so vertical centering silently fails. Same for `justify_content` → must be `flex_justify_content`. When dynamic background image looks "wrong-aligned", check this key before blaming the dynamic tag.

**Gotcha 17 (v3):** **Setting `background_color` alongside `__dynamic__[background_image]` silently kills the dynamic tag.** Elementor's render cascade prefers the solid color and skips dynamic-tag resolution. Rule: when binding `post-featured-image` (or any dynamic bg), OMIT `background_color` entirely — don't even set it to empty. Fallback for missing featured image: overlay renders over transparent/theme bg (acceptable) — avoid a color crutch.

**Gotcha 18 (v3):** **Elementor's per-template CSS file (`uploads/elementor/css/post-{id}.css`) can be silently purged by LiteSpeed/host cache** but the HTML still references it via `?ver=...`. Result: page renders un-styled until next edit triggers regen. When overlay or layout "disappears" after session gap, first action: trigger any dummy update (e.g. `update-element` with `_title` tweak) to force Elementor to rewrite the file. Don't chase phantom pattern bugs.

### CTA banner v2 — modern interactive redesign (2026-04-18)

- **`cta.banner-full-width` v2 rewrite** — replaced boring single-text "trust line" with 3-up glass stat chips row (value/label pairs, divider lines between). Added icon-prefixed pill buttons: primary gets WhatsApp brand icon (left, solid `#25D366`), secondary gets arrow-right icon (right, translates +4px on hover). Both buttons now pill-shaped (`border-radius: 999px`). Primary has shimmer sweep effect via `::after` + hover lift + 6px white glow ring. Secondary glass-outline with `backdrop-filter: blur(12px)`.
- **Eyebrow pill upgraded** — glass-blur pill with pulsing accent-color dot prefix (via `--accent` custom prop), `backdrop-filter: blur(10px)`, subtle white border. Replaces plain solid-bg pill.
- **Multi-layer atmospheric overlay** — dual radial gradients (primary-brand tint from top + dark vignette from bottom) instead of single linear. Animated shimmer sweep (`@keyframes emcpCtaShimmer`, 12s ease-in-out infinite, mix-blend-mode overlay) for subtle living-image feel.
- **Typography tighter** — headline letter-spacing `-0.02em` + text-shadow 4px/24px for depth, subhead constrained to 620px max-width for readability. 70vh min-height (was 60vh) for more presence.
- **New slots**: `primary_icon`, `secondary_icon`, `trust_stats` (array of `{value,label}`). Defaults: WhatsApp + arrow + 3 chips (4.9★ reviews / DTCM licensed / FREE hotel transfer). `overlay_opacity` default bumped 0.78 → 0.88 for stronger contrast with bright desert images.
- **Responsive stack** — mobile: buttons stack 100% width, stats row collapses column with horizontal dividers, shimmer disabled. Tablet: headline scales to 38px, chips tighten.

**Gotcha 8 (v3):** **Author-bio section lives OUTSIDE `.emcp-article` wrapper.** The article enhancer only wraps `the_content` output, which is the `theme-post-content` widget's rendered output. Other sections in the theme template (hero, author-bio, cta) are SIBLINGS to the article, not descendants. CSS selectors for these sections must NOT be prefixed with `.emcp-article`.

**Gotcha 9 (v3):** **CSS custom properties set on `.emcp-article` don't cascade to sibling sections.** Use fallback hex in `var(--emcp-*, #hex)` so sibling-section CSS still works when vars are undefined (e.g. `.emcp-author-card` uses `var(--emcp-accent, #F4A460)`).

**Developer rule (for future sessions):**
- ALWAYS update this CHANGELOG when changing plugin code.
- Design pipeline is *additive*: never modify files under `includes/abilities/` except `class-design-abilities.php` (new) and the single line added to `class-ability-registrar.php`.
- Pattern files auto-discovered — drop `patterns/*.php` with `emcp_pattern_{slug}()` function to register.
- Templates created via `design-theme-template` tagged with `_emcp_generated=1` postmeta — that's how Article Enhancer decides when to activate.
- Kit colors from `bind_palette()` deduped by title — safe to call repeatedly.

### Other v1.5.0 work

- Fix: `duplicate-page` now correctly copies the Elementor element tree. The previous implementation relied on `copy_post_meta()` alone, which routed `_elementor_data` through `add_post_meta()` and triggered WordPress's internal `wp_unslash()` pass — stripping backslashes from escaped quotes and `\uXXXX` sequences in the JSON payload and silently producing a duplicate with an unreadable element tree that fell back to raw HTML rendering. `_elementor_data` is now excluded from the generic meta copier and re-saved on the target post via the Elementor document `save()` API, which also regenerates CSS. A post-save JSON integrity check rolls the duplicate back with a clear `WP_Error` if the element tree fails verification, and the response now includes an `elementor_copy_status` field. All other meta keys are also now `wp_slash()`ed before `add_post_meta()` to survive the round-trip.
- New: 3 page lookup/query tools - `get-page`, `get-page-by-slug`, `get-page-id-by-slug`.
- New: 2 page metadata tools - `update-page-post`, `update-page-meta`.
- New: `duplicate-page` tool to clone Elementor-built content with copied taxonomies, post meta, and refreshed Elementor CSS.
- New: companion `@elementor-mcp/cli` package with endpoint proxy mode and WordPress REST fallback mode for page/file workflows.
- New: companion CLI REST fallback `duplicate_page` workflow for read/write environments that expose Elementor meta through `wp/v2`.
- Fix: companion CLI REST fallback no longer hangs on permalink auto-detection and now reports `rest_forbidden_context` as a clear edit-context capability error.
- Fix: companion CLI endpoint mode now uses JSON POST negotiation and surfaces MCP endpoint HTTP errors immediately instead of hanging on unsupported streaming responses.

- New: 5 Pro widget convenience tools — code-highlight, reviews, off-canvas, progress-tracker, search.
- Total MCP tools increased to 106.

## [1.4.2]

- Fix: Add missing `items` property to all `array` type JSON Schema definitions across 6 ability files (12 instances). VS Code and other strict MCP clients reject tools with invalid schemas, causing "tool parameters array type must have items" errors (#6).

## [1.4.1]

- Fix: Node.js proxy now supports `MCP_PROTOCOL_VERSION` env var to override the protocol version in initialize responses, working around upstream MCP Adapter hardcoding `2025-06-18` which some clients don't support (#4).
- Improved: Proxy now logs server info, protocol version, and discovered tools count for easier diagnostics.
- Improved: Proxy logs full response bodies to file (not stderr) when `MCP_LOG_FILE` is set.
- Improved: Expanded troubleshooting section in README with protocol version mismatch diagnosis, debug logging instructions, and session management guidance.
- Improved: Added Node.js proxy connection section to README with environment variable documentation.
- Improved: Added proxy config example with `MCP_PROTOCOL_VERSION` to `mcp-config-examples.json`.
- New: Connection tab now auto-generates Node.js proxy configs (recommended) with auto-detected filesystem path, alongside existing HTTP configs.

## [1.4.0]

- New: 22 Pro widget convenience tools — nav menu, loop grid, loop carousel, media carousel, nested tabs, nested accordion, and more.
- New: 5 WooCommerce widget tools — products, add-to-cart, cart, checkout, menu cart (conditional on WooCommerce).
- New: 4 layout tools — update-container, update-element, batch-update, reorder-elements.
- New: 6 template/theme builder tools — create-theme-template, set-template-conditions, list-dynamic-tags, set-dynamic-tag, create-popup, set-popup-settings.
- New: 2 query tools — get-container-schema, find-element.
- New: 4 extended core widget tools — menu-anchor, shortcode, rating, text-path.
- Total MCP tools increased from 70 to 92.
- Improved: Settings validator with stricter schema enforcement.
- Improved: Element factory with enhanced container support.

## [1.3.2]

- Renamed plugin to "MCP Tools for Elementor" to comply with WordPress.org trademark guidelines.
- Updated admin menu label to "EMCP Tools" for brevity.
- Fixed WPCS issues: prefixed all global variables in view templates, escaped integer output, added missing translators comments.
- Updated "Tested up to" to WordPress 6.9.
- Added languages/ directory for Domain Path header.

## [1.3.1]

- New: Prompts tab in admin dashboard — browse and one-click copy 5 sample landing page prompts.
- New: Contributing Prompts guide in CONTRIBUTING.md with structure, guidelines, and submission steps.
- Improved: Admin CSS for prompt card grid with hover effects and responsive breakpoints.

## [1.3.0]

- New: `add-custom-css` tool — add custom CSS to any element or page-level with `selector` keyword support (Pro only).
- New: `add-custom-js` tool — inject JavaScript via HTML widget with automatic `<script>` wrapping and optional DOMContentLoaded wrapper.
- New: `add-code-snippet` tool — create site-wide Custom Code snippets for head/body injection with priority and jQuery support (Pro only).
- New: `list-code-snippets` tool — list all Custom Code snippets with location, priority, and status filters (Pro only).
- Total tools increased from ~64 to ~68.

## [1.2.3]

- Fix: Factory now strips `flex_wrap` and `_flex_size` from container settings — prevents AI agents from setting these values that cause layout overflow.
- Fix: Tool descriptions now include background color instructions (`background_background=classic`, `background_color=#hex`) so AI agents apply colors correctly.
- Improved: Stronger "NEVER set flex_wrap" guidance in build-page and add-container tool descriptions.

## [1.2.2]

- Fix: Row container children now use `content_width: full` with percentage widths (e.g. 25% for 4 columns) matching Elementor's native column layout pattern.
- Fix: Removed all `flex_wrap` and `_flex_size` auto-overrides from factory and build-page — Elementor defaults handle layout correctly.
- Improved: Tool descriptions updated with correct multi-column layout guidance.

## [1.2.1]

- Fix: Row containers now use `flex_wrap: wrap` instead of `nowrap` to prevent children from overflowing.
- Fix: `build-page` auto-sets percentage widths on row children (e.g. 50% for 2 columns, 33.33% for 3) instead of using `_flex_size: grow` which caused layout overflow.
- Improved: Tool descriptions updated with correct layout guidance for multi-column layouts.

## [1.2.0]

- New: 14 free widget convenience tools — accordion, alert, counter, Google Maps, icon list, image box, image carousel, progress bar, social icons, star rating, tabs, testimonial, toggle, HTML.
- New: 10 Pro widget convenience tools — call to action, slides, testimonial carousel, price list, gallery, share buttons, table of contents, blockquote, Lottie animation, hotspot.
- Total widget tools increased from 17 to 41 (~64 MCP tools overall).

## [1.1.1]

- Fix: Container flex layout — row children auto-grow with `_flex_size: grow` for equal distribution.
- Fix: Column containers auto-center content horizontally (`align_items: center`).
- Fix: Row containers auto-set `flex_wrap: nowrap` to prevent wrapping.
- Fix: `_flex_size` now correctly uses string value (`grow`) instead of array — prevents fatal error in Elementor CSS generator.
- Fix: `get-global-settings` input schema uses `stdClass` for empty properties to serialize as JSON `{}` instead of `[]`.
- New: Connection tab configs for Cursor, Windsurf, and Antigravity IDE clients.
- New: 3 stock image tools — `search-images`, `sideload-image`, `add-stock-image` (Openverse API).
- New: SVG icon tool — `add-svg-icon` for custom SVG icons.
- Improved: `build-page` description with detailed layout rules for row/column containers.
- Improved: Admin connection tab streamlined — removed WP-CLI local section, unified HTTP config workflow.

## [1.0.0]

- Initial release.
- 7 read-only query/discovery tools.
- 5 page management tools (create, update settings, delete content, import, export).
- 4 layout tools (add container, move, remove, duplicate elements).
- 2 universal widget tools (add-widget, update-widget).
- 9 core widget convenience shortcuts.
- 6 Pro widget convenience shortcuts (conditional on Elementor Pro).
- 2 template tools (save as template, apply template).
- 2 global settings tools (colors, typography).
- 1 composite build-page tool.
- Admin settings page with tool toggles and connection info.
- Node.js HTTP proxy for remote connections.
