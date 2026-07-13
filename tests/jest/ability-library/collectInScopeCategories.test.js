/* global jest, describe, test, expect */
/**
 * Jest tests for Feature 052 — collectInScopeCategories().
 *
 * Asserts the pure helper that resolves the in-scope category set based on
 * the active tab. PATTERN-NAMED-EXPORT-JEST.
 *
 * @since 0.1.0
 */

jest.mock('@wordpress/components', () => ({
	Button: () => null,
	Notice: () => null,
	TabPanel: ({ children }) =>
		typeof children === 'function' ? children({ name: '__all__' }) : null,
}));
jest.mock('@wordpress/element', () => ({
	useEffect: () => {},
	useMemo: (fn) => fn(),
	useRef: () => ({ current: false }),
	useState: (init) => [init, () => {}],
}));
jest.mock('@wordpress/i18n', () => ({ __: (v) => v }));
jest.mock('@wordpress/icons', () => ({ Icon: () => null, plugins: null }));
jest.mock('../../../src/js/ability-library/api', () => ({
	fetchConfig: jest.fn(() => Promise.resolve({})),
	saveConfig: jest.fn(() => Promise.resolve()),
}));
jest.mock('../../../src/js/ability-library/components/LibraryCard', () => ({
	__esModule: true,
	default: () => null,
}));
jest.mock('../../../src/js/ability-library/hooks/useLibraryTabSync', () => ({
	__esModule: true,
	default: () => {},
}));

const {
	collectInScopeCategories,
	ALL_TABS_KEY,
} = require('../../../src/js/ability-library/components/LibraryPage');

function item(category, tabGroups) {
	return {
		id: category,
		category,
		categoryLabel: category,
		slugs: tabGroups.map((tg, i) => ({
			slug: `${category}/${i}`,
			slugLabel: `${category}-${i}`,
			name: `${category}/${i}`,
			subGroup: '',
			subGroupLabel: '',
			tabGroup: tg,
			description: '',
		})),
	};
}

describe('collectInScopeCategories', () => {
	const items = [
		item('a', ['core']),
		item('b', ['blocks']),
		item('c', ['core']),
	];

	test('returns every unique category when activeTab is ALL_TABS_KEY', () => {
		expect(collectInScopeCategories(items, ALL_TABS_KEY, ALL_TABS_KEY)).toEqual([
			'a',
			'b',
			'c',
		]);
	});

	test('returns only categories whose slug tabGroup matches "core"', () => {
		expect(collectInScopeCategories(items, 'core', ALL_TABS_KEY)).toEqual([
			'a',
			'c',
		]);
	});

	test('returns only categories whose slug tabGroup matches "blocks"', () => {
		expect(collectInScopeCategories(items, 'blocks', ALL_TABS_KEY)).toEqual([
			'b',
		]);
	});

	test('returns [] for a tab with no matching categories', () => {
		expect(
			collectInScopeCategories(items, 'nonexistent', ALL_TABS_KEY)
		).toEqual([]);
	});

	test('skips items with missing category field', () => {
		const withMissing = [{ slugs: [{ tabGroup: 'core' }] }, item('a', ['core'])];
		expect(
			collectInScopeCategories(withMissing, ALL_TABS_KEY, ALL_TABS_KEY)
		).toEqual(['a']);
	});
});
