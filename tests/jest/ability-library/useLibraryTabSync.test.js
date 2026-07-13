/* global jest, describe, test, expect */
/**
 * Jest tests for Feature 052 — useLibraryTabSync().
 *
 * Asserts the pure named exports parseTabFromUrl and buildUrlFromTab.
 * The default-export hook itself is not exercised here — its three effects
 * are covered indirectly by the LibraryPage integration cases plus manual
 * quickstart. PATTERN-NAMED-EXPORT-JEST.
 *
 * SEC-052-I-003: parseTabFromUrl MUST return the sentinel for unknown slugs.
 *
 * @since 0.1.0
 */

// Lightweight virtual mock of @wordpress/url — the real package isn't in
// node_modules; WordPress ships it as a runtime peer. These implementations
// match the documented behavior for the three helpers we use.
jest.mock(
	'@wordpress/url',
	() => {
		function toUrlObj(input) {
			try {
				return new URL(input, 'http://example.com');
			} catch (_) {
				return null;
			}
		}
		return {
			getQueryArg(url, key) {
				const u = toUrlObj(url);
				if (!u) {
					return undefined;
				}
				return u.searchParams.get(key) ?? undefined;
			},
			addQueryArgs(url, args) {
				const u = toUrlObj(url);
				if (!u) {
					return url;
				}
				for (const [k, v] of Object.entries(args || {})) {
					u.searchParams.set(k, v);
				}
				return u.toString();
			},
			removeQueryArgs(url, ...keys) {
				const u = toUrlObj(url);
				if (!u) {
					return url;
				}
				for (const k of keys) {
					u.searchParams.delete(k);
				}
				return u.toString();
			},
		};
	},
	{ virtual: true }
);

jest.mock('@wordpress/element', () => ({
	useEffect: () => {},
}));

// Prevent LibraryPage load — the hook imports ALL_TABS_KEY from it.
jest.mock(
	'../../../src/js/ability-library/components/LibraryPage',
	() => ({ ALL_TABS_KEY: '__all__' })
);

const {
	parseTabFromUrl,
	buildUrlFromTab,
} = require('../../../src/js/ability-library/hooks/useLibraryTabSync');

describe('parseTabFromUrl', () => {
	test('no tab query arg → returns sentinel', () => {
		expect(
			parseTabFromUrl(
				'http://example.com/wp-admin/admin.php?page=acrossai-abilities-library',
				['core', 'themes'],
				'__all__'
			)
		).toBe('__all__');
	});

	test('valid tab slug is returned as-is', () => {
		expect(
			parseTabFromUrl(
				'http://example.com/wp-admin/admin.php?page=acrossai-abilities-library&tab=core',
				['core', 'themes'],
				'__all__'
			)
		).toBe('core');
	});

	test('SEC-052-I-003: unknown slug falls back to sentinel — raw value never returned', () => {
		expect(
			parseTabFromUrl(
				'http://example.com/wp-admin/admin.php?page=acrossai-abilities-library&tab=nonexistent',
				['core'],
				'__all__'
			)
		).toBe('__all__');
	});

	test('empty validSlugs falls back to sentinel', () => {
		expect(
			parseTabFromUrl(
				'http://example.com/wp-admin/admin.php?tab=core',
				[],
				'__all__'
			)
		).toBe('__all__');
	});
});

describe('buildUrlFromTab', () => {
	test('sentinel strips the tab query arg (canonical default URL is clean)', () => {
		const result = buildUrlFromTab(
			'__all__',
			'http://example.com/wp-admin/admin.php?page=acrossai-abilities-library&tab=core',
			'__all__'
		);
		expect(result).not.toContain('tab=');
		expect(result).toContain('page=acrossai-abilities-library');
	});

	test('specific tab is added with page arg preserved', () => {
		const result = buildUrlFromTab(
			'themes',
			'http://example.com/wp-admin/admin.php?page=acrossai-abilities-library',
			'__all__'
		);
		expect(result).toContain('tab=themes');
		expect(result).toContain('page=acrossai-abilities-library');
	});

	test('other query args are preserved through both operations', () => {
		const result = buildUrlFromTab(
			'blocks',
			'http://example.com/wp-admin/admin.php?page=acrossai-abilities-library&other=x',
			'__all__'
		);
		expect(result).toContain('tab=blocks');
		expect(result).toContain('other=x');
	});
});
