/* global jest, describe, test, expect */
/**
 * Jest tests for Feature 037 — LibraryPage tab-bar conditional render.
 *
 * Asserts the JSX boolean guards that decide between the no-tab-bar layout
 * (US3 no-regression) and the tab-bar layout (US1). Tests the same boolean
 * expressions used in LibraryPage.js JSX so the contract is locked.
 *
 * @since 0.1.0
 */

jest.mock('@wordpress/components', () => ({
	Button: () => null,
	Notice: () => null,
	TabPanel: ({ children }) =>
		typeof children === 'function' ? children({ name: '__all__' }) : null,
}));
jest.mock('@wordpress/icons', () => ({ Icon: () => null, plugins: null }));
jest.mock('../../../src/js/ability-library/hooks/useLibraryTabSync', () => ({
	__esModule: true,
	default: () => {},
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
	groupDefinitions,
	collectTabGroups,
} = require('../../../src/js/ability-library/components/LibraryPage');

function def(overrides = {}) {
	return {
		category: 'cat',
		category_label: 'Cat',
		slug: 'slug-a',
		slug_label: 'Slug A',
		name: 'plugin/slug-a',
		sub_group: '',
		sub_group_label: '',
		tab_group: '',
		args: {},
		...overrides,
	};
}

describe('LibraryPage — tab-bar conditional (FR-006 / US3)', () => {
	// JSX uses these guards inside LibraryPage:
	//   items.length > 0 && tabGroups.length === 0 && renderCards(items)   // flat layout
	//   items.length > 0 && tabGroups.length > 0  && <TabPanel … />        // tab-bar layout

	test('no tab_group declared anywhere → tab bar is NOT rendered (flat layout)', () => {
		const items = groupDefinitions([
			def({ slug: 'a' }),
			def({ slug: 'b' }),
		]);
		const tabGroups = collectTabGroups(items);

		// US3 contract — page renders identically to prior release.
		expect(items.length > 0 && tabGroups.length === 0).toBe(true);
		expect(items.length > 0 && tabGroups.length > 0).toBe(false);
	});

	test('at least one tab_group declared → tab bar IS rendered', () => {
		const items = groupDefinitions([
			def({ slug: 'a' }),
			def({ slug: 'b', tab_group: 'sales' }),
		]);
		const tabGroups = collectTabGroups(items);

		// US1 contract — tab bar appears as soon as any add-on opts in.
		expect(items.length > 0 && tabGroups.length > 0).toBe(true);
		expect(items.length > 0 && tabGroups.length === 0).toBe(false);
		expect(tabGroups).toEqual(['sales']);
	});

	test('empty definitions → neither layout renders (empty-state message instead)', () => {
		const items = groupDefinitions([]);
		const tabGroups = collectTabGroups(items);

		// items.length === 0 — both branches false; empty-state copy renders.
		expect(items.length > 0 && tabGroups.length === 0).toBe(false);
		expect(items.length > 0 && tabGroups.length > 0).toBe(false);
		expect(items).toHaveLength(0);
	});
});
