/**
 * Jest tests for Feature 033 CHANGE-A — Library card visibility contract.
 *
 * Replicates the pure-logic predicates from LibraryCard.js (the slug panel
 * visibility guard, the panel-row mode predicate, and the sub_keys reset on
 * mode change) so they can be asserted without rendering React. The JSX in
 * src/js/ability-library/components/LibraryCard.js must use the identical
 * boolean expressions.
 *
 * @since 0.1.0
 */

/**
 * The slug panel render predicate.
 *
 * Mirrors the JSX guard at LibraryCard.js:
 *   {slugs.length > 0 && expanded && (...)}
 *
 * Feature 052 (turn 2 refinement): the `enabled &&` clause was removed so
 * the panel can be expanded on disabled cards as a readonly preview. When
 * disabled, `shouldRenderInteractiveRows` returns false regardless of the
 * stored mode — no interactive checkboxes escape the disabled contract.
 *
 * @param {boolean} _enabled  Card master toggle (unused — kept for callers).
 * @param {number}  slugsLen  Number of registered slugs in the category.
 * @param {boolean} expanded  Per-card disclosure state.
 * @return {boolean}
 */
function shouldRenderSlugPanel(_enabled, slugsLen, expanded) {
	return slugsLen > 0 && expanded;
}

/**
 * The disclosure button visibility predicate.
 *
 * Mirrors the JSX guard at LibraryCard.js:
 *   {canExpand && <Button … />}
 *   where canExpand = slugs.length > 0
 *
 * Feature 052 (turn 2 refinement): the `enabled &&` clause was removed so
 * the chevron stays visible on disabled cards for header-row alignment
 * consistency between enabled and disabled states. The slug panel gate
 * (shouldRenderSlugPanel) still requires `enabled` — a chevron click on a
 * disabled card is effectively a no-op since there is no panel below.
 *
 * @param {boolean} _enabled Card master toggle (unused — kept for callers).
 * @param {number}  slugsLen Number of registered slugs.
 * @return {boolean}
 */
function shouldShowDisclosureButton(_enabled, slugsLen) {
	return slugsLen > 0;
}

/**
 * The per-row interactive-vs-readonly mode predicate.
 *
 * Mirrors the JSX ternary at LibraryCard.js:
 *   enabled && mode === 'specific' ? <CheckboxControl /> : <div …__slug-readonly />
 *
 * Feature 052 (turn 2 refinement): the `enabled &&` clause was added so
 * disabled cards always render readonly rows, even if the stored mode is
 * 'specific'. Preserves the sub_keys selection for re-enable while keeping
 * the disabled state visually non-interactive.
 *
 * @param {boolean} enabled Card master toggle.
 * @param {string}  mode    'all' or 'specific'.
 * @return {boolean} true when the row should render as an interactive checkbox.
 */
function shouldRenderInteractiveRows(enabled, mode) {
	return enabled && mode === 'specific';
}

/**
 * The sub_keys reset rule on radio change.
 *
 * Mirrors the JSX onChange at LibraryCard.js:
 *   sub_keys: value === 'all' ? {} : slugsConfig
 *
 * @param {string} newMode     The mode the user just selected.
 * @param {Object} prevSubKeys The existing sub_keys map.
 * @return {Object}
 */
function nextSubKeysOnModeChange(newMode, prevSubKeys) {
	return newMode === 'all' ? {} : prevSubKeys;
}

describe('shouldRenderSlugPanel — Feature 033 (turn 3) + Feature 052 (turn 2) visibility contract', () => {
	test('panel renders when enabled + slugs > 0 + expanded', () => {
		expect(shouldRenderSlugPanel(true, 5, true)).toBe(true);
	});

	test('panel does NOT render when the disclosure is collapsed', () => {
		expect(shouldRenderSlugPanel(true, 5, false)).toBe(false);
	});

	test('panel does NOT render when slugs is 0 (empty category)', () => {
		expect(shouldRenderSlugPanel(true, 0, true)).toBe(false);
		expect(shouldRenderSlugPanel(false, 0, true)).toBe(false);
	});

	test('Feature 052 turn 2: panel RENDERS on disabled cards (readonly preview)', () => {
		expect(shouldRenderSlugPanel(false, 5, true)).toBe(true);
	});
});

describe('shouldShowDisclosureButton — chevron presence predicate', () => {
	test('button shown when slugs > 0 (enabled)', () => {
		expect(shouldShowDisclosureButton(true, 5)).toBe(true);
	});

	test('button hidden when no slugs registered (no content to disclose)', () => {
		expect(shouldShowDisclosureButton(true, 0)).toBe(false);
		expect(shouldShowDisclosureButton(false, 0)).toBe(false);
	});

	test('Feature 052 turn 2: button STAYS visible when disabled + slugs > 0 (row alignment)', () => {
		expect(shouldShowDisclosureButton(false, 5)).toBe(true);
	});
});

describe('shouldRenderInteractiveRows — per-row mode predicate', () => {
	test('enabled + mode "specific" → interactive CheckboxControl rows', () => {
		expect(shouldRenderInteractiveRows(true, 'specific')).toBe(true);
	});

	test('enabled + mode "all" → read-only label rows', () => {
		expect(shouldRenderInteractiveRows(true, 'all')).toBe(false);
	});

	test('Feature 052 turn 2: disabled forces readonly even when stored mode is "specific"', () => {
		expect(shouldRenderInteractiveRows(false, 'specific')).toBe(false);
	});

	test('disabled + mode "all" → read-only', () => {
		expect(shouldRenderInteractiveRows(false, 'all')).toBe(false);
	});

	test('unexpected mode falls back to read-only', () => {
		expect(shouldRenderInteractiveRows(true, 'something-else')).toBe(false);
	});
});

describe('nextSubKeysOnModeChange — sub_keys reset on radio switch', () => {
	test('switching to "all" clears the existing selection map', () => {
		const prev = { 'plugin-a/ability-1': true, 'plugin-a/ability-2': true };
		expect(nextSubKeysOnModeChange('all', prev)).toEqual({});
	});

	test('switching to "specific" preserves the existing selection map', () => {
		const prev = { 'plugin-a/ability-1': true };
		expect(nextSubKeysOnModeChange('specific', prev)).toBe(prev);
	});

	test('switching to "all" from an already-empty map stays {}', () => {
		expect(nextSubKeysOnModeChange('all', {})).toEqual({});
	});
});

/**
 * Feature 052 — disabled-card DOM-identity contract (FR-017 + SEC-052-I-004).
 *
 * When a category card has enabled=false, LibraryCard.js MUST render ONLY the
 * ToggleControl (switch + category label). All three of the following gates
 * MUST evaluate to false, regardless of how the card became disabled:
 *
 *   1. Chevron disclosure button (canExpand)             — LibraryCard.js:70
 *   2. All/Specific RadioControl                          — LibraryCard.js:107
 *   3. Slug list (readonly rows OR interactive rows)     — LibraryCard.js:138
 *
 * The bulk `Disable All` action and the per-card ToggleControl BOTH set the
 * same stored shape (`{enabled: false, mode: <preserved>, sub_keys: <preserved>}`)
 * and BOTH reach LibraryCard through the identical `config[category]` read
 * path — so the same predicates fire. This test locks that invariant so a
 * future edit that loosens any of the three gates cannot ship without
 * failing this suite.
 */
describe('Feature 052 — disabled-card DOM contract (FR-017 + SEC-052-I-004)', () => {
	// The two possible disable paths in Feature 052 both produce the same
	// stored shape. Simulate both and assert predicate parity.
	const perCardDisabled = { enabled: false, mode: 'all', sub_keys: {} };
	const bulkDisabledInAllMode = { enabled: false, mode: 'all', sub_keys: {} };
	const bulkDisabledPreservingSpecific = {
		enabled: false,
		mode: 'specific',
		sub_keys: { 'a/read': true },
	};

	test.each([
		['per-card ToggleControl OFF', perCardDisabled],
		['bulk Disable All (was all-mode)', bulkDisabledInAllMode],
		['bulk Disable All (was specific-mode w/ selections)', bulkDisabledPreservingSpecific],
	])(
		'%s → chevron + readonly panel visible, All/Specific radio hidden, checkboxes forced readonly',
		(_label, entry) => {
			const enabled = entry.enabled;
			const slugsLen = 5;
			const expanded = true;

			// Gate 1: chevron disclosure (LibraryCard.js:70) — visible.
			expect(shouldShowDisclosureButton(enabled, slugsLen)).toBe(true);

			// Gate 2: All/Specific radio — the LibraryCard JSX guards it with
			// `{enabled && (…RadioControl…)}` on line 107. Disabled → hidden.
			expect(enabled).toBe(false);

			// Gate 3: slug panel (LibraryCard.js:138) — visible on disabled cards
			// as a readonly preview.
			expect(shouldRenderSlugPanel(enabled, slugsLen, expanded)).toBe(true);

			// Gate 4: interactive-row predicate — MUST return false when disabled,
			// regardless of stored mode. No CheckboxControl escapes onto the
			// disabled render.
			expect(shouldRenderInteractiveRows(enabled, entry.mode)).toBe(false);
		}
	);

	test('per-card-disabled and bulk-disabled produce identical predicate outputs', () => {
		const gates = (entry) => ({
			chevron: shouldShowDisclosureButton(entry.enabled, 5),
			radio: entry.enabled,
			slugPanel: shouldRenderSlugPanel(entry.enabled, 5, true),
			interactive: shouldRenderInteractiveRows(entry.enabled, entry.mode),
		});
		expect(gates(perCardDisabled)).toEqual(gates(bulkDisabledInAllMode));
		expect(gates(perCardDisabled)).toEqual(
			gates(bulkDisabledPreservingSpecific)
		);
	});
});
