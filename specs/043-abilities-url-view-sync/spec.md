# Feature Specification: Deep-linkable Edit URLs for Custom Abilities

**Feature Branch**: `043-abilities-url-view-sync`
**Created**: 2026-07-07
**Status**: Implemented
**Input**: User description: "when I click on edit button it does not pass anything into the URL which it should and if user directly come on that page it should open that abilities edit pages"

## User Scenarios & Testing *(mandatory)*

### User Story 1 — Admin bookmarks / shares the Edit page for a specific ability (Priority: P1)

An admin managing Custom Abilities clicks **Edit** on a row (e.g. `ai/comment-analysis`). The browser URL updates to `admin.php?page=acrossai-abilities-manager&action=edit&slug=ai/comment-analysis`. They can now bookmark that URL, right-click → "Copy Link", share it with a colleague, or open Edit in a new tab. On any of those flows, landing on the URL directly opens the ability's edit form pre-populated with server data — not the list.

**Why this priority**: Edit is the primary action on this screen. Every other admin page in wp-admin (Posts, Users, Taxonomies, Custom Post Types) exposes an editable state through the URL — the Abilities screen was the only one that didn't. This is the top-friction gap for daily users.

**Independent Test**: On a fresh install with at least one Custom Ability registered, visit `admin.php?page=acrossai-abilities-manager`. Click Edit on any row. Verify:
1. The URL updates to include `&action=edit&slug=<slug>`.
2. The form renders that ability's data.
3. Copy the URL, paste in a new browser tab → the form opens directly on the same ability (not the list).
4. Browser Back returns to the list and drops the two params. Browser Forward returns to the edit form and restores the URL.

**Acceptance Scenarios**:

1. **Given** the Custom Abilities list is showing, **When** the admin clicks **Edit** on the row for `ai/comment-analysis`, **Then** `window.location.href` gains `&action=edit&slug=ai%2Fcomment-analysis` (URL-encoded slug) and the form renders with that ability loaded.
2. **Given** the admin is on the edit form for `ai/comment-analysis`, **When** they click browser **Back**, **Then** the URL loses `&action` and `&slug`, and the list re-renders in place.
3. **Given** the admin is on the list (post-Back), **When** they click browser **Forward**, **Then** the URL restores `&action=edit&slug=ai%2Fcomment-analysis` and the edit form re-renders with the same ability.
4. **Given** the admin opens a fresh browser tab and pastes `admin.php?page=acrossai-abilities-manager&action=edit&slug=ai/comment-analysis` into the address bar, **When** the page loads, **Then** the edit form is rendered directly — the list never flashes.
5. **Given** the admin visits `admin.php?page=acrossai-abilities-manager&action=edit&slug=does-not-exist`, **When** the page loads, **Then** the edit form mounts, the REST `GET /acrossai-abilities-manager/v1/abilities/{slug}` returns 404, and the store's existing error state surfaces via the form's existing error banner.
6. **Given** the admin visits `admin.php?page=acrossai-abilities-manager&action=edit` (missing `slug`), **When** the page loads, **Then** the app falls back to the list view silently — no error notice.

---

### User Story 2 — Contributor writes documentation that deep-links into an ability's edit form (Priority: P2)

A contributor writing internal docs or a support macro pastes a link like `wp-admin/admin.php?page=acrossai-abilities-manager&action=edit&slug=ai/summarization` into a Notion/Confluence page or a Slack DM. Clicking the link jumps a support engineer straight to the correct edit form on the customer's site.

**Why this priority**: Once the URL is stable, other systems (internal wikis, chat clients, browser history) can hold references to it. Without stable URLs, every reference has to be worded as "open the Abilities page, then click Edit next to X."

**Independent Test**: Given a docs page with the above URL, click it from a logged-in admin browser session → the edit form loads directly on the correct ability.

---

### Edge Cases

- **Slug with slash (`ai/comment-analysis`)**: `@wordpress/url` handles URL-encoding automatically — `addQueryArgs(url, { slug: 'ai/foo' })` writes `slug=ai%2Ffoo`; `getQueryArg(url, 'slug')` decodes it back to `ai/foo`. No manual `encodeURIComponent` needed. No breakage from the `/` inside slugs.
- **Store `view` mutations that do NOT come from user click** (e.g. post-create-success `setView({ mode: 'edit', slug: newSlug })` at `AbilityForm.jsx:508`): the URL sync effect catches these transparently — no special-casing needed. Redirect after create still lands on a bookmarkable URL.
- **`?action=edit` with missing `slug`**: falls back to `'list'` view. WordPress-admin convention: missing keys degrade silently.
- **`?action=<unknown>`** (e.g. `?action=frobnicate`): ignored. Falls back to list.
- **`?action=new`**: intentionally not implemented this round. The `create` view exists in the router (`AbilitiesManager.jsx:74-76`) but no user-visible "Add New" button ships today. Reserved for a future follow-up.
- **`beforeunload` prompt on dirty forms**: `history.pushState` is a same-document navigation and does NOT fire `beforeunload`. The existing dirty-form guard at `AbilitiesManager.jsx:31-44` therefore continues to only fire on real page unloads (reload, close tab, external navigation) — not on Back/Forward-triggered intra-app view switches. This preserves the pre-043 UX and matches how Gutenberg's own admin router behaves.
- **Scroll-position save/restore**: the existing logic at `AbilitiesManager.jsx:47-68` is keyed on `view` transitions, not URL. It keeps working unchanged.
- **Non-manager wp-admin params** (e.g. `_wpnonce`, notice flags, add-on tracking params): the sync layer preserves every param that isn't `action` or `slug`. Only those two keys are owned by this feature.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: When the store's `view` state equals `{ mode: 'edit', slug: '<X>' }`, `window.location.href` MUST include `action=edit` and `slug=<X>` (URL-encoded). Any other query args on the URL MUST be preserved unchanged.
- **FR-002**: When the store's `view` state equals `'list'`, `window.location.href` MUST NOT contain `action` or `slug` query args. Any other query args MUST be preserved.
- **FR-003**: On mount of the root `AbilitiesManager` component, the URL MUST be parsed. If `action === 'edit'` AND `slug` is a non-empty string, the store MUST dispatch `setView({ mode: 'edit', slug })` before the router evaluates. This makes deep-links open the edit form on first render — no list flash.
- **FR-004**: A `popstate` event listener MUST be registered on `window` for the lifetime of the mounted app. When it fires, the URL MUST be re-parsed and `setView(...)` dispatched to match. This makes browser Back/Forward round-trip the view.
- **FR-005**: The URL sync effect MUST skip `history.pushState` calls when `buildUrlFromView(view, location.href) === location.href`. This prevents duplicate history entries on first render (where the mount-time deep-link dispatch AND the initial view-effect both fire).
- **FR-006**: URL manipulation MUST use the `@wordpress/url` package (`addQueryArgs`, `getQueryArg`, `removeQueryArgs`) — not raw `URLSearchParams`. Rationale: consistency with the rest of wp-admin; auto-registration of `wp-url` as a script dep by `@wordpress/scripts`; no manual encode/decode.
- **FR-007**: The Edit `<button>` at `AbilitiesList.jsx:808-823` (custom source) and `AbilitiesList.jsx:884-899` (inherited source) MUST remain a `<button>` element. It MUST NOT be converted to an `<a href>`. Rationale: prevents middle-click / cmd-click "open in new tab" from bypassing the store-driven form load (deep-link support via mount-time URL parse handles that case correctly); preserves button semantics for assistive tech.
- **FR-008**: No REST endpoint, PHP module, database schema, or `wp_localize_script` payload MUST be added or modified. Feature 043 is a pure client-side URL-sync layer.

### Key Entities *(include if feature involves data)*

- **`view` state** (Redux) — the existing `acrossai/abilities` store slice at `src/js/abilities/store/index.js:61`. Values: `'list'`, `{ mode: 'create' }`, or `{ mode: 'edit', slug, ability? }`. Feature 043 does NOT change its shape or reducer; it only synchronizes reads/writes of the `edit`-variant subset with the URL.
- **URL query args `action` and `slug`** — owned exclusively by Feature 043. `page=acrossai-abilities-manager` is owned by WordPress (menu routing) and untouched. All other params are preserved verbatim.
- **`useUrlViewSync` hook** — new file `src/js/abilities/hooks/useUrlViewSync.js`. Exports the default hook + two pure named helpers (`parseViewFromUrl`, `buildUrlFromView`) per the plugin's `PATTERN-NAMED-EXPORT-JEST` convention.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: On a fresh install with ≥1 Custom Ability, clicking Edit on any row causes `window.location.href` to gain both `&action=edit` and `&slug=<slug>`. Verified in browser DevTools during the T009 walk-through.
- **SC-002**: Pasting the resulting edit URL into a fresh browser tab loads the edit form directly on the correct ability. The list view MUST NOT be visible at any point (no visual flash).
- **SC-003**: Browser Back → list; Browser Forward → edit. Both round-trips update the URL AND the rendered view. Verified in the T009 walk-through.
- **SC-004**: `build/js/abilities.asset.php` declares `wp-url` in its dependency array (auto-injected by `@wordpress/scripts`' DependencyExtractionWebpackPlugin as a result of the `@wordpress/url` imports in the new hook).
- **SC-005**: `URLSearchParams` count in `build/js/abilities.js` remains ≤1 (the one pre-existing occurrence lives inside `src/js/abilities/api/client.js`; the new hook does NOT contribute a second occurrence — it goes through `@wordpress/url`).
- **SC-006**: On the edit form, making a change and clicking browser Back does NOT trigger the `beforeunload` prompt (regression guard for the same-document `pushState` behavior). Reloading the tab while dirty DOES still fire the prompt (unchanged).
- **SC-007**: `npm run build` succeeds with zero errors; bundle-size increase is under 1 KB minified.

## Assumptions

- **The `AbilityForm` component's existing on-mount `fetchAbility(slug)` at line 277 handles all data loading**. Passing `initialAbility` on click is an optimization to prevent a blank flash while REST is in-flight, not a requirement. Deep-linked mounts have no `initialAbility` and rely on `fetchAbility` — this path already existed and is exercised by the same code that handles a page reload while editing.
- **`?action=edit&slug=<X>` is the canonical URL scheme** for the lifetime of this plugin. Matches WP-core conventions (`?action=edit&user_id=…`, `?action=edit&post=…`). Any future param additions (e.g. `&tab=schema` to open the form on a specific tab) MUST live alongside `action`/`slug`, not replace them.
- **`create` mode is out of scope** for Feature 043. No visible "Add New" button exists; adding URL sync for a code path that no user can reach today is premature. When Add-New ships, extending the same hook to handle `?action=new` is a ~5-LoC follow-up.
- **The Edit action stays a `<button>`, not an `<a>`**. Rationale documented in FR-007 and in the plan's "What we deliberately do NOT change" section. Middle-click / cmd-click behavior is covered by deep-link support (fresh tabs get the edit form via mount-time URL parse), not by native anchor semantics.
- **No new memory pattern is warranted**. The change reuses `@wordpress/url`, `@wordpress/data`, `@wordpress/element`, and the existing store slice. If a second admin surface later gains its own URL-sync layer, the recurring convention could be captured then — capturing at n=1 violates the "capture on recurrence" memory-hygiene rule.
