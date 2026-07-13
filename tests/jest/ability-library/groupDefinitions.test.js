/* global jest, describe, test, expect */
/**
 * Jest tests for Feature 036 — groupDefinitions().
 *
 * Asserts the pure grouping helper named-exported from LibraryPage.js.
 * No React rendering required (PATTERN-NAMED-EXPORT-JEST). Confirms that
 * args.description threads through to each slug entry as a trimmed string,
 * and that absent / non-string / whitespace-only descriptions degrade to ''.
 *
 * @since 0.1.0
 */

// Mock the WordPress packages and sibling modules that LibraryPage.js imports
// at module top-level, so the file is requireable under jsdom for the pure
// named export only.
jest.mock('@wordpress/components', () => ({
	Button: () => null,
	Notice: () => null,
	TabPanel: ({ children }) =>
		typeof children === 'function' ? children({ name: '__all__' }) : null,
}));
jest.mock('../../../src/js/ability-library/hooks/useLibraryTabSync', () => ({
	__esModule: true,
	default: () => {},
}));
jest.mock('@wordpress/icons', () => ({ Icon: () => null, plugins: null }));
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
		args: {},
		...overrides,
	};
}

describe('groupDefinitions — description passthrough', () => {
	test('passes a non-empty args.description through to the slug entry', () => {
		const result = groupDefinitions([
			def({ args: { description: 'Hello world.' } }),
		]);

		expect(result).toHaveLength(1);
		expect(result[0].slugs).toHaveLength(1);
		expect(result[0].slugs[0].description).toBe('Hello world.');
	});

	test('trims surrounding whitespace from args.description', () => {
		const result = groupDefinitions([
			def({ args: { description: '   Padded.   ' } }),
		]);

		expect(result[0].slugs[0].description).toBe('Padded.');
	});

	test('whitespace-only args.description becomes an empty string', () => {
		const result = groupDefinitions([
			def({ args: { description: '   \n\t  ' } }),
		]);

		expect(result[0].slugs[0].description).toBe('');
	});

	test('absent args.description becomes an empty string', () => {
		const result = groupDefinitions([def({ args: {} })]);

		expect(result[0].slugs[0].description).toBe('');
	});

	test('absent args object becomes an empty description string', () => {
		const result = groupDefinitions([def({ args: undefined })]);

		expect(result[0].slugs[0].description).toBe('');
	});

	test('non-string args.description (number, object, array) becomes an empty string', () => {
		const numeric = groupDefinitions([
			def({ slug: 'a', args: { description: 42 } }),
		]);
		const objectish = groupDefinitions([
			def({ slug: 'b', args: { description: { msg: 'no' } } }),
		]);
		const arrayish = groupDefinitions([
			def({ slug: 'c', args: { description: ['no'] } }),
		]);

		expect(numeric[0].slugs[0].description).toBe('');
		expect(objectish[0].slugs[0].description).toBe('');
		expect(arrayish[0].slugs[0].description).toBe('');
	});

	test('slug entries expose description but NOT the raw args object', () => {
		const result = groupDefinitions([
			def({ args: { description: 'visible', label: 'leaked?' } }),
		]);

		const slug = result[0].slugs[0];
		expect(slug.description).toBe('visible');
		expect(slug).not.toHaveProperty('args');
		expect(slug).not.toHaveProperty('label');
	});

	test('preserves existing fields (slug, slugLabel, name, subGroup, subGroupLabel, tabGroup)', () => {
		const result = groupDefinitions([
			def({
				slug: 'plugin/x',
				slug_label: 'X Label',
				name: 'plugin/x',
				sub_group: 'core',
				sub_group_label: 'Core',
				tab_group: 'sales',
				args: { description: 'A description.' },
			}),
		]);

		expect(result[0].slugs[0]).toEqual({
			slug: 'plugin/x',
			slugLabel: 'X Label',
			name: 'plugin/x',
			subGroup: 'core',
			subGroupLabel: 'Core',
			tabGroup: 'sales',
			description: 'A description.',
		});
	});
});

describe('groupDefinitions — tab_group passthrough (Feature 037)', () => {
	test('threads tab_group through to each slug entry as tabGroup', () => {
		const result = groupDefinitions([
			def({ tab_group: 'sales' }),
			def({ slug: 'b', tab_group: 'support' }),
		]);

		expect(result[0].slugs[0].tabGroup).toBe('sales');
		expect(result[0].slugs[1].tabGroup).toBe('support');
	});

	test('absent tab_group becomes an empty string', () => {
		const result = groupDefinitions([def({})]);
		expect(result[0].slugs[0].tabGroup).toBe('');
	});

	test('falsy tab_group (null, undefined, 0, false) becomes an empty string', () => {
		const cases = [null, undefined, 0, false];
		cases.forEach((value, i) => {
			const result = groupDefinitions([
				def({ slug: `slug-${i}`, tab_group: value }),
			]);
			expect(result[0].slugs[0].tabGroup).toBe('');
		});
	});
});
