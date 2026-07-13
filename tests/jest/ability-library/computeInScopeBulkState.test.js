/* global jest, describe, test, expect */
/**
 * Jest tests for Feature 052 — computeInScopeBulkState().
 *
 * Asserts the tri-state ('all' | 'none' | 'mixed') derived from the current
 * config against an in-scope category set. Missing entries default to
 * enabled=true (matches server-side sparse-storage semantics).
 * PATTERN-NAMED-EXPORT-JEST.
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
	computeInScopeBulkState,
} = require('../../../src/js/ability-library/components/LibraryPage');

describe('computeInScopeBulkState', () => {
	test('empty inScopeCategories returns "all"', () => {
		expect(computeInScopeBulkState({}, [])).toBe('all');
	});

	test('all in-scope entries enabled → "all"', () => {
		const cfg = {
			a: { enabled: true, mode: 'all', sub_keys: {} },
			b: { enabled: true, mode: 'all', sub_keys: {} },
		};
		expect(computeInScopeBulkState(cfg, ['a', 'b'])).toBe('all');
	});

	test('all in-scope entries disabled → "none"', () => {
		const cfg = {
			a: { enabled: false, mode: 'all', sub_keys: {} },
			b: { enabled: false, mode: 'all', sub_keys: {} },
		};
		expect(computeInScopeBulkState(cfg, ['a', 'b'])).toBe('none');
	});

	test('mixed enable/disable in-scope → "mixed"', () => {
		const cfg = {
			a: { enabled: true, mode: 'all', sub_keys: {} },
			b: { enabled: false, mode: 'all', sub_keys: {} },
		};
		expect(computeInScopeBulkState(cfg, ['a', 'b'])).toBe('mixed');
	});

	test('missing entry defaults to enabled=true (sparse-storage semantics)', () => {
		expect(computeInScopeBulkState({}, ['a'])).toBe('all');
	});

	test('one missing + one disabled = mixed', () => {
		const cfg = { b: { enabled: false, mode: 'all', sub_keys: {} } };
		expect(computeInScopeBulkState(cfg, ['a', 'b'])).toBe('mixed');
	});
});
