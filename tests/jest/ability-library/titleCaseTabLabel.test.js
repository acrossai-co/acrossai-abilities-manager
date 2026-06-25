/* global jest, describe, test, expect */
/**
 * Jest tests for Feature 037 — titleCaseTabLabel().
 *
 * Asserts that the JS label-derivation rule mirrors the PHP
 * `ucwords( str_replace( '-', ' ', $value ) )` rule used for category_label
 * (FR-007). PATTERN-NAMED-EXPORT-JEST.
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
	titleCaseTabLabel,
} = require('../../../src/js/ability-library/components/LibraryPage');

describe('titleCaseTabLabel', () => {
	test('capitalizes a single-word identifier', () => {
		expect(titleCaseTabLabel('support')).toBe('Support');
	});

	test('converts hyphens to spaces and capitalizes each word', () => {
		expect(titleCaseTabLabel('sales-ops')).toBe('Sales Ops');
		expect(titleCaseTabLabel('customer-relationship-mgmt')).toBe(
			'Customer Relationship Mgmt'
		);
	});

	test('preserves underscores as-is (only hyphens become spaces)', () => {
		// FR-007 mirrors PHP str_replace('-', ' ', ...) — underscores are not converted.
		expect(titleCaseTabLabel('sales_ops')).toBe('Sales_ops');
	});

	test('handles identifiers starting with digits', () => {
		expect(titleCaseTabLabel('123-sales')).toBe('123 Sales');
	});

	test('empty string returns empty string', () => {
		expect(titleCaseTabLabel('')).toBe('');
	});

	test('non-string input returns empty string', () => {
		expect(titleCaseTabLabel(null)).toBe('');
		expect(titleCaseTabLabel(undefined)).toBe('');
		expect(titleCaseTabLabel(42)).toBe('');
		expect(titleCaseTabLabel({})).toBe('');
	});
});
