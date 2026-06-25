/* global jest, describe, test, expect */
/**
 * Jest tests for Feature 037 — filterItemsByTabGroup().
 *
 * Asserts the pure helper that filters grouped items by active tab.
 * Covers the FR-005 (filter cards), FR-006 (no-op for '__all__'), and the
 * SC-002/US2 reference-equality contract that lets React useMemo stay stable.
 *
 * @since 0.1.0
 */

jest.mock('@wordpress/components', () => ({
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
jest.mock('../../../src/js/ability-library/api', () => ({
	fetchConfig: jest.fn(() => Promise.resolve({})),
	saveConfig: jest.fn(() => Promise.resolve()),
}));
jest.mock('../../../src/js/ability-library/components/LibraryCard', () => ({
	__esModule: true,
	default: () => null,
}));

const {
	filterItemsByTabGroup,
	ALL_TABS_KEY,
} = require('../../../src/js/ability-library/components/LibraryPage');

function slug(overrides = {}) {
	return {
		slug: 'plugin/a',
		slugLabel: 'A',
		name: 'plugin/a',
		subGroup: '',
		subGroupLabel: '',
		tabGroup: '',
		description: '',
		...overrides,
	};
}

function item(category, slugs) {
	return {
		id: category,
		category,
		categoryLabel: category.charAt(0).toUpperCase() + category.slice(1),
		slugs,
	};
}

describe('filterItemsByTabGroup', () => {
	test("'__all__' returns the input array reference unchanged", () => {
		// SC-002 / US2 stability: same reference so useMemo identity is stable.
		const items = [
			item('crm', [slug({ slug: 'a', tabGroup: 'sales' })]),
			item('helpdesk', [slug({ slug: 'b', tabGroup: 'support' })]),
		];
		const result = filterItemsByTabGroup(items, ALL_TABS_KEY);
		expect(result).toBe(items);
	});

	test('matching tab returns items with slugs filtered to that tab_group', () => {
		const items = [
			item('crm', [
				slug({ slug: 'a', tabGroup: 'sales' }),
				slug({ slug: 'b', tabGroup: 'support' }),
			]),
			item('helpdesk', [slug({ slug: 'c', tabGroup: 'support' })]),
		];
		const result = filterItemsByTabGroup(items, 'support');

		expect(result).toHaveLength(2);
		expect(result[0].category).toBe('crm');
		expect(result[0].slugs).toHaveLength(1);
		expect(result[0].slugs[0].slug).toBe('b');
		expect(result[1].category).toBe('helpdesk');
		expect(result[1].slugs[0].slug).toBe('c');
	});

	test('items with no matching slug are dropped (FR-005)', () => {
		const items = [
			item('crm', [slug({ slug: 'a', tabGroup: 'sales' })]),
			item('helpdesk', [slug({ slug: 'b', tabGroup: 'support' })]),
		];
		const result = filterItemsByTabGroup(items, 'sales');
		expect(result).toHaveLength(1);
		expect(result[0].category).toBe('crm');
	});

	test('non-matching tab + ungrouped slugs returns empty array', () => {
		const items = [
			item('crm', [slug({ slug: 'a' })]),
			item('helpdesk', [slug({ slug: 'b' })]),
		];
		expect(filterItemsByTabGroup(items, 'sales')).toEqual([]);
	});

	test('preserves original item objects untouched (immutability)', () => {
		const items = [
			item('crm', [
				slug({ slug: 'a', tabGroup: 'sales' }),
				slug({ slug: 'b', tabGroup: 'support' }),
			]),
		];
		const original = JSON.parse(JSON.stringify(items));
		filterItemsByTabGroup(items, 'sales');
		expect(items).toEqual(original);
	});
});
