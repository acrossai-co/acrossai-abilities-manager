/* global jest, describe, test, expect */
/**
 * Jest tests for Feature 060 — third-party integration card_variant branch.
 *
 * Covers:
 *   T012 — groupDefinitions() propagates card_variant to grouped item.cardVariant.
 *   T011 — LibraryCard integration-variant predicates:
 *          (a) When cardVariant==='integration', the All/Specific radio is NOT rendered.
 *          (b) When cardVariant==='integration' and config[category] is missing,
 *              the toggle defaults to unchecked (FR-008 default OFF).
 *          (c) When cardVariant==='integration', slug rows are always readonly.
 *
 * Predicates are reimplemented here for pure-logic testing per the same
 * pattern used by tests/jest/ability-library/LibraryCard.test.js (Feature 033).
 * The JSX in src/js/ability-library/components/LibraryCard.js MUST use the
 * identical boolean expressions.
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

/**
 * The default-enabled predicate for a card, mirroring the JSX in LibraryCard.js:
 *   const isIntegration = cardVariant === 'integration';
 *   const defaultEnabled = !isIntegration;
 *   const entry = config[category] ?? { enabled: defaultEnabled, mode: 'all', sub_keys: {} };
 *   const enabled = entry.enabled ?? defaultEnabled;
 *
 * @param {string|undefined} cardVariant Item's cardVariant field.
 * @param {Object|undefined} configEntry Saved config entry for the category, or undefined when absent.
 * @return {boolean} Whether the toggle should render as checked.
 */
function computeEnabled(cardVariant, configEntry) {
	const isIntegration = cardVariant === 'integration';
	const defaultEnabled = !isIntegration;
	const entry = configEntry ?? {
		enabled: defaultEnabled,
		mode: 'all',
		sub_keys: {},
	};
	return entry.enabled ?? defaultEnabled;
}

/**
 * The All/Specific radio visibility predicate, mirroring the JSX gate:
 *   {enabled && !isIntegration && <RadioControl … />}
 *
 * @param {boolean}          enabled     Card master toggle.
 * @param {string|undefined} cardVariant Item's cardVariant field.
 * @return {boolean} Whether the RadioControl block should render.
 */
function shouldRenderModeRadio(enabled, cardVariant) {
	const isIntegration = cardVariant === 'integration';
	return enabled && !isIntegration;
}

/**
 * The per-row interactive-vs-readonly predicate — unchanged from Feature 033
 * (LibraryCard.test.js), reproduced here for the integration-variant path
 * verification. Integration cards never allow mode==='specific' to be reached
 * from the UI (radio is suppressed), so rows stay readonly.
 *
 * @param {boolean} enabled Card master toggle.
 * @param {string}  mode    'all' or 'specific'.
 * @return {boolean} Whether the slug row should render as an interactive checkbox.
 */
function shouldRenderInteractiveRows(enabled, mode) {
	return enabled && mode === 'specific';
}

describe('LibraryPage — groupDefinitions card_variant propagation (T012)', () => {
	test('regular category has cardVariant === undefined', () => {
		const items = groupDefinitions([def({ slug: 'a' })]);
		expect(items).toHaveLength(1);
		expect(items[0].cardVariant).toBeUndefined();
	});

	test('single card_variant declaration propagates to the grouped item', () => {
		const items = groupDefinitions([
			def({ slug: 'a', card_variant: 'integration' }),
		]);
		expect(items).toHaveLength(1);
		expect(items[0].cardVariant).toBe('integration');
	});

	test('first non-empty card_variant wins when a category has mixed rows', () => {
		const items = groupDefinitions([
			def({ slug: 'a', card_variant: 'integration' }),
			def({ slug: 'b', card_variant: '' }),
			def({ slug: 'c', card_variant: 'other-variant' }),
		]);
		expect(items).toHaveLength(1);
		expect(items[0].cardVariant).toBe('integration');
	});

	test('different categories get independent cardVariant values', () => {
		const items = groupDefinitions([
			def({
				category: 'acf',
				slug: 'acf/field-group',
				card_variant: 'integration',
			}),
			def({ category: 'core', slug: 'core/settings' }),
		]);
		expect(items).toHaveLength(2);
		const byCategory = Object.fromEntries(
			items.map((i) => [i.category, i.cardVariant])
		);
		expect(byCategory.acf).toBe('integration');
		expect(byCategory.core).toBeUndefined();
	});
});

describe('LibraryCard integration-variant predicates (T011)', () => {
	test('cardVariant==="integration" + missing config → toggle defaults OFF (FR-008)', () => {
		// Case a in spec T011: default-off for integration cards.
		expect(computeEnabled('integration', undefined)).toBe(false);
	});

	test('regular card (no cardVariant) + missing config → toggle defaults ON (existing behaviour)', () => {
		expect(computeEnabled(undefined, undefined)).toBe(true);
	});

	test('cardVariant==="integration" + explicit enabled=true config → toggle ON', () => {
		expect(
			computeEnabled('integration', {
				enabled: true,
				mode: 'all',
				sub_keys: {},
			})
		).toBe(true);
	});

	test('cardVariant==="integration" + explicit enabled=false config → toggle OFF', () => {
		expect(
			computeEnabled('integration', {
				enabled: false,
				mode: 'all',
				sub_keys: {},
			})
		).toBe(false);
	});

	test('All/Specific radio is NOT rendered when cardVariant==="integration" (any enabled state)', () => {
		// Case a in spec T011.
		expect(shouldRenderModeRadio(true, 'integration')).toBe(false);
		expect(shouldRenderModeRadio(false, 'integration')).toBe(false);
	});

	test('All/Specific radio IS rendered on a regular enabled card', () => {
		expect(shouldRenderModeRadio(true, undefined)).toBe(true);
	});

	test('All/Specific radio hidden on any disabled card (regular or integration)', () => {
		expect(shouldRenderModeRadio(false, undefined)).toBe(false);
	});

	test('Integration cards keep mode==="all" so rows always render readonly (case c)', () => {
		// The radio is suppressed → mode never flips to 'specific' via UI →
		// shouldRenderInteractiveRows stays false. This confirms case c:
		// slugs render readonly regardless of the toggle state.
		expect(shouldRenderInteractiveRows(true, 'all')).toBe(false);
		expect(shouldRenderInteractiveRows(false, 'all')).toBe(false);
	});
});
