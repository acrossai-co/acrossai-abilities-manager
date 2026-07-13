/* global jest, describe, test, expect */
/**
 * Jest tests for Feature 052 — buildBulkPatch().
 *
 * Asserts tab-scoped bulk-toggle patch semantics: in-scope entries have only
 * their `enabled` boolean flipped (mode + sub_keys preserved), out-of-scope
 * entries pass through byte-for-byte. PATTERN-NAMED-EXPORT-JEST.
 *
 * Also covers US3 lossless round-trip (Disable All → Enable All restores
 * mode + sub_keys byte-for-byte).
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
	buildBulkPatch,
} = require('../../../src/js/ability-library/components/LibraryPage');

describe('buildBulkPatch', () => {
	test('fresh defaults when no prior entry — enable all', () => {
		const result = buildBulkPatch({}, ['a', 'b'], true);
		expect(result).toEqual({
			a: { enabled: true, mode: 'all', sub_keys: {} },
			b: { enabled: true, mode: 'all', sub_keys: {} },
		});
	});

	test('preserves prior mode + sub_keys when enable flips to false', () => {
		const prior = {
			a: {
				enabled: true,
				mode: 'specific',
				sub_keys: { 'a/read': true },
			},
		};
		const result = buildBulkPatch(prior, ['a'], false);
		expect(result.a).toEqual({
			enabled: false,
			mode: 'specific',
			sub_keys: { 'a/read': true },
		});
	});

	test('out-of-scope categories pass through byte-for-byte (tab-scoping)', () => {
		const prior = {
			a: { enabled: false, mode: 'all', sub_keys: {} },
			b: { enabled: false, mode: 'specific', sub_keys: { 'b/write': true } },
		};
		const result = buildBulkPatch(prior, ['a'], true);
		expect(result.a).toEqual({ enabled: true, mode: 'all', sub_keys: {} });
		// b is out of scope — must be the ORIGINAL reference, byte-for-byte.
		expect(result.b).toBe(prior.b);
	});

	test('empty inScopeCategories returns a shallow copy of currentConfig', () => {
		const prior = { a: { enabled: true, mode: 'all', sub_keys: {} } };
		const result = buildBulkPatch(prior, [], true);
		expect(result).toEqual(prior);
		expect(result).not.toBe(prior);
	});

	// US3 — lossless round-trip
	test('Disable All → Enable All round-trip preserves mode + sub_keys byte-for-byte', () => {
		const seed = {
			a: {
				enabled: true,
				mode: 'specific',
				sub_keys: { 'a/read': true, 'a/write': false },
			},
		};
		const disabled = buildBulkPatch(seed, ['a'], false);
		const reEnabled = buildBulkPatch(disabled, ['a'], true);
		expect(reEnabled.a.mode).toBe('specific');
		expect(reEnabled.a.sub_keys).toEqual({ 'a/read': true, 'a/write': false });
		expect(reEnabled.a.enabled).toBe(true);
	});
});
