/**
 * Keeps `window.location.search` in lockstep with the Library page's
 * `activeTab` state so tabs are deep-linkable, bookmarkable, and navigable
 * via browser back/forward.
 *
 * URL scheme:
 *   - default view                → ?page=acrossai-abilities-library                  (no `tab` arg)
 *   - specific tab (e.g. core)    → ?page=acrossai-abilities-library&tab=core
 *
 * Mirrors the three-effect structure of src/js/abilities/hooks/useUrlViewSync.js.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.1.0
 */
import { useEffect } from '@wordpress/element';
import { addQueryArgs, getQueryArg, removeQueryArgs } from '@wordpress/url';
import { ALL_TABS_KEY } from '../components/LibraryPage';

/**
 * Parse a URL (or bare query string) into an active-tab identifier.
 *
 * Returns `allTabsKey` when the `tab` query arg is absent OR when the value
 * is not present in `validSlugs`. This is the SEC-052-I-003 sentinel-fallback
 * contract: never emit the raw URL value downstream if it doesn't match a
 * known tab.
 *
 * Named export per PATTERN-NAMED-EXPORT-JEST.
 *
 * @param {string}   url        Any URL or `location.search`-style string.
 * @param {string[]} validSlugs Runtime list of registered tab identifiers.
 * @param {string}   allTabsKey The sentinel for the "All" tab.
 * @return {string} A tab identifier ready for `setActiveTab(…)`.
 */
export function parseTabFromUrl(url, validSlugs, allTabsKey) {
	const raw = getQueryArg(url || '', 'tab');
	if (typeof raw !== 'string' || raw === '') {
		return allTabsKey;
	}
	if (!Array.isArray(validSlugs) || !validSlugs.includes(raw)) {
		return allTabsKey;
	}
	return raw;
}

/**
 * Build the URL that represents the given `activeTab`, preserving every
 * other query arg already on `currentUrl`.
 *
 * When `activeTab === allTabsKey` the `tab` query arg is stripped so the
 * canonical default URL stays clean. Otherwise `?tab=<slug>` is added or
 * updated. Other query args (`page`, any future filters) pass through
 * verbatim.
 *
 * Named export per PATTERN-NAMED-EXPORT-JEST.
 *
 * @param {string} activeTab  Current active tab identifier or the sentinel.
 * @param {string} currentUrl Current URL (typically `location.href`).
 * @param {string} allTabsKey The sentinel for the "All" tab.
 * @return {string} URL string with `tab` set to match the active tab.
 */
export function buildUrlFromTab(activeTab, currentUrl, allTabsKey) {
	const base = currentUrl || '';
	const stripped = removeQueryArgs(base, 'tab');
	if (activeTab === allTabsKey) {
		return stripped;
	}
	return addQueryArgs(stripped, { tab: activeTab });
}

/**
 * React hook — call once inside LibraryPage after tabGroups is memoized.
 *
 * Three flows:
 *   1. On mount, read the URL and dispatch the matching `setActiveTab(…)`
 *      so a deep-link (e.g. shared/bookmarked URL) opens the correct tab.
 *   2. On every `activeTab` change, push a matching URL onto history (skip
 *      when the URL is already correct — avoids duplicate history entries).
 *   3. On browser back/forward (`popstate`), re-parse the URL and dispatch
 *      the corresponding `setActiveTab(…)`.
 *
 * @param {string}                 activeTab    Current active tab.
 * @param {(next: string) => void} setActiveTab Setter that accepts a tab slug.
 * @param {string[]}               validSlugs   Runtime list of tab identifiers.
 * @return {void}
 */
export default function useLibraryTabSync(activeTab, setActiveTab, validSlugs) {
	// Flow 1: mount-time deep-link parse.
	useEffect(() => {
		const initial = parseTabFromUrl(
			window.location.href,
			validSlugs,
			ALL_TABS_KEY
		);
		if (initial !== activeTab) {
			setActiveTab(initial);
		}
		// Intentionally run once on mount — hook wiring, not reactive on validSlugs.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, []);

	// Flow 2: mirror activeTab → URL.
	useEffect(() => {
		const nextUrl = buildUrlFromTab(
			activeTab,
			window.location.href,
			ALL_TABS_KEY
		);
		if (nextUrl === window.location.href) {
			return;
		}
		window.history.pushState({}, '', nextUrl);
	}, [activeTab]);

	// Flow 3: browser back/forward — re-derive activeTab from URL.
	useEffect(() => {
		const handler = () => {
			const next = parseTabFromUrl(
				window.location.href,
				validSlugs,
				ALL_TABS_KEY
			);
			setActiveTab(next);
		};
		window.addEventListener('popstate', handler);
		return () => window.removeEventListener('popstate', handler);
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [validSlugs]);
}
