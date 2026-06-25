/* global jest, describe, test, expect */
/**
 * Jest tests for Feature 037 — collectTabGroups().
 *
 * Asserts the pure helper that derives the sorted unique set of tab_group
 * identifiers from grouped items. PATTERN-NAMED-EXPORT-JEST.
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
	collectTabGroups,
} = require('../../../src/js/ability-library/components/LibraryPage');

function itemWithSlugs(slugs) {
	return {
		id: 'cat',
		category: 'cat',
		categoryLabel: 'Cat',
		slugs,
	};
}

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

describe('collectTabGroups', () => {
	test('returns an empty array when no slug declares tabGroup', () => {
		const result = collectTabGroups([
			itemWithSlugs([slug(), slug({ slug: 'b' })]),
		]);
		expect(result).toEqual([]);
	});

	test('dedupes when multiple slugs share the same tabGroup', () => {
		const items = [
			itemWithSlugs([
				slug({ slug: 'a', tabGroup: 'sales' }),
				slug({ slug: 'b', tabGroup: 'sales' }),
				slug({ slug: 'c', tabGroup: 'support' }),
			]),
		];
		expect(collectTabGroups(items)).toEqual(['sales', 'support']);
	});

	test('sorts alphabetically case-insensitively (FR-013)', () => {
		const items = [
			itemWithSlugs([
				slug({ slug: 'a', tabGroup: 'support' }),
				slug({ slug: 'b', tabGroup: 'crm' }),
				slug({ slug: 'c', tabGroup: 'analytics' }),
			]),
		];
		expect(collectTabGroups(items)).toEqual([
			'analytics',
			'crm',
			'support',
		]);
	});

	test('aggregates tabGroup across multiple items / categories', () => {
		const items = [
			itemWithSlugs([slug({ slug: 'a', tabGroup: 'sales' })]),
			{
				id: 'cat2',
				category: 'cat2',
				categoryLabel: 'Cat 2',
				slugs: [slug({ slug: 'b', tabGroup: 'support' })],
			},
		];
		expect(collectTabGroups(items)).toEqual(['sales', 'support']);
	});

	test('ignores empty-string and falsy tabGroup values', () => {
		const items = [
			itemWithSlugs([
				slug({ slug: 'a', tabGroup: '' }),
				slug({ slug: 'b', tabGroup: 'sales' }),
			]),
		];
		expect(collectTabGroups(items)).toEqual(['sales']);
	});
});
