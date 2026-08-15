# CLAUDE.md — WordPress Plugin Review Checklist

This file tells Claude how to review WordPress plugin code in this repo. When asked to review, audit, or "check" this plugin, work through the sections below in order and report findings grouped by severity (Critical / High / Medium / Low), with file + line references. Don't just list problems — say what to change.

## How to run a review

1. Read `readme.txt` / the main plugin file header to understand what the plugin does and its declared PHP/WP version support.
2. Grep across the codebase for the patterns below rather than reading every file line by line for a large plugin.
3. Flag anything uncertain as "needs manual verification" rather than guessing intent.
4. Prioritize security issues first, then data-loss/breaking bugs, then performance, then style/standards.

---

## 1. Security (check first, always)

**Input handling**
- Every `$_GET`, `$_POST`, `$_REQUEST`, `$_COOKIE`, `$_SERVER` read must be sanitized on the way in (`sanitize_text_field`, `sanitize_email`, `absint`, `sanitize_key`, etc. — match the sanitizer to the expected data type).
- Flag any raw superglobal used directly in a DB query, `echo`, `eval`, `include`/`require`, or shell command.

**Output escaping**
- Every value echoed into HTML should be escaped at output time: `esc_html()`, `esc_attr()`, `esc_url()`, `esc_js()`, or `wp_kses_post()`. Sanitizing on input is not a substitute for escaping on output — check for both, don't assume one covers the other.
- Check `admin_url()`, `home_url()`, `get_permalink()` output that gets echoed — still needs `esc_url()`.

**SQL**
- Any raw `$wpdb->query()`, `get_results()`, `get_var()`, etc. must use `$wpdb->prepare()` with placeholders (`%s`, `%d`, `%f`) — never string concatenation or interpolation of user input into SQL.
- Watch for `%s` used where `%d` is correct (weaker protection) and for table/column names being interpolated (can't be parameterized — must be allowlisted).

**Nonces & CSRF**
- Every state-changing action (form submit, AJAX handler, admin-post handler) needs `wp_nonce_field()` on output and `check_admin_referer()` / `wp_verify_nonce()` / `check_ajax_referer()` on receipt.
- Confirm the nonce action name matches on both ends.

**Capability checks**
- Every admin action, AJAX handler, and REST route needs `current_user_can()` with an appropriate capability — not just a login check (`is_user_logged_in()` alone is not authorization).
- REST routes: check `permission_callback` isn't left as `__return_true` for anything that reads/writes non-public data.
- AJAX: distinguish `wp_ajax_*` (logged-in) vs `wp_ajax_nopriv_*` (public) — flag any sensitive action exposed via `nopriv`.

**File operations & uploads**
- File upload handlers must validate file type/extension (not just MIME from the client) and avoid executing uploaded content.
- Flag any dynamic `include`/`require` built from user input (LFI/RFI risk) and any use of `file_get_contents()`/`fopen()` on user-supplied paths or URLs without validation.

**Other common holes**
- `eval()`, `create_function()`, `unserialize()` on untrusted input, `extract()` on `$_REQUEST`.
- Hardcoded secrets, API keys, or credentials in the repo (check for accidental commits of keys — cross-reference against known keys in this project if any are on file).
- Direct file access: every PHP file should start with `if ( ! defined( 'ABSPATH' ) ) exit;` (or equivalent) to block direct URL access.
- Object injection via `unserialize()` — prefer `json_decode`/`json_encode` for stored data.

---

## 2. Data integrity & common bugs

- **Activation/deactivation/uninstall hooks**: check `register_activation_hook`, `register_deactivation_hook`, and `uninstall.php` exist and clean up appropriately (don't destroy user data on mere deactivation; only on explicit uninstall/delete).
- **Database schema changes**: version-gated with `dbDelta()` and an option-stored schema version, not run unconditionally on every load.
- **Autoloaded options**: large or frequently-changing options registered with `autoload => 'no'` where appropriate — flag big serialized blobs set to autoload.
- **Race conditions**: check-then-act patterns on options/postmeta without locking (e.g., incrementing a counter by read-then-write).
- **PHP notices/warnings**: undefined array keys, undefined variables, null property access — these often indicate real logic bugs, not just noise.
- **Error handling**: `wp_remote_get`/`wp_remote_post` calls should check `is_wp_error()` before using the response.
- **i18n**: user-facing strings wrapped in `__()`/`_e()`/`esc_html__()` with a consistent text domain matching the plugin slug.

---

## 3. Performance

- Queries inside loops (`foreach` calling `get_post_meta`/`WP_Query`/`$wpdb` per iteration) — flag for batching.
- `WP_Query`/`get_posts` calls without `no_found_rows => true` when pagination isn't needed, and without limiting `fields`/`posts_per_page` where a full post list isn't needed.
- Unbounded queries (`posts_per_page => -1`) on tables that could grow large.
- Scripts/styles enqueued on every admin page instead of scoped to the plugin's own screens (`enqueue` hook should check `$hook_suffix` or `get_current_screen()`).
- External HTTP calls (`wp_remote_get`, etc.) on the front-end request path without caching (transients) — flag anything hitting a third-party API on every page load.
- N+1 patterns in any custom REST endpoints or AJAX handlers.

---

## 4. WordPress coding standards & compatibility

- Hooks use namespaced/prefixed function and hook names — flag generic names (`init()`, `save()`, `settings`) at risk of collision; everything should share a unique plugin prefix.
- No direct `$wpdb` table names without `$wpdb->prefix`.
- Uses WP-provided functions instead of raw PHP equivalents where they exist (`wp_remote_get` vs `curl`/`file_get_contents`, `wp_mkdir_p` vs `mkdir`, `wp_generate_password` vs custom randomness).
- Deprecated function calls (check against the plugin's declared minimum WP version — e.g. `create_function`, `each()`, old jQuery migrate patterns).
- Declared "Tested up to" WP version and "Requires PHP" header realistic given functions actually used (e.g. flag arrow functions/typed properties if PHP 7.4 is claimed as minimum).
- Enqueued JS/CSS have proper dependency arrays and version strings (avoid `null`/no-cache-busting on plugin assets).
- Translation-ready: `Text Domain` header matches folder/slug; no string concatenation inside translation functions (`__( 'Hello ' . $name )` breaks translation).

---

## 5. Front-end / embedded widget specifics

(Relevant for standalone HTML/CSS/vanilla-JS tools embedded via Custom HTML blocks, which this plugin/site pattern uses.)

- Inline `<script>` blocks should not read/write `localStorage`/`sessionStorage` in ways that assume persistence across page structure changes without a fallback.
- Any third-party API calls (e.g. Supabase) from client-side JS: confirm only a public/anon/publishable key is exposed, never a service-role/secret key, and confirm row-level security is enabled on the backend if writes are exposed publicly.
- Forms in embedded widgets should still have basic honeypot/rate-limiting if they write to a public endpoint, since they bypass normal WP form-security plugins.
- Check accessibility basics didn't regress: labeled form fields, sufficient color contrast, keyboard operability, ARIA only where semantic HTML isn't sufficient.
- Check the widget degrades gracefully if the external API (Supabase, etc.) is unreachable — no unhandled promise rejections left visible to the user.

---

## 6. Output format for reviews

When Claude finishes a review, structure the response as:

1. **Summary** — one paragraph, overall risk level.
2. **Critical** — security holes or data-loss bugs; fix before anything else.
3. **High** — bugs likely to cause user-facing breakage.
4. **Medium** — performance and standards issues worth fixing soon.
5. **Low** — style/nitpicks, optional.

Each item: `file:line — what's wrong — why it matters — suggested fix`. Skip items you can't verify from the code alone rather than speculating.
