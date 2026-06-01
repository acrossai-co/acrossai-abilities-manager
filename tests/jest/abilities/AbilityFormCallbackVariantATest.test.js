/**
 * Jest tests for CHANGE-4 (Feature 024): Callback section read-only
 * display for isNonDb abilities and editable Variant A display.
 *
 * Tests the pure display-decision logic for the Callback section in
 * AbilityForm.jsx (Section 6). Non-DB abilities must show a read-only
 * badge for the registered callback type and a read-only pre block for
 * the callback config. Variant A (DB-backed) abilities must have
 * editable CallbackTypeChips and CallbackConfigField.
 *
 * @since 0.1.0
 */

// ---------------------------------------------------------------------------
// Pure-logic helpers derived from AbilityForm.jsx Section 6 render logic
// ---------------------------------------------------------------------------

/**
 * Replicate the isNonDb callback type display decision.
 *
 * When isNonDb is true, show "tbadge" if a registered type exists,
 * otherwise "desc" with "Not defined" text.
 *
 * @param {boolean}     isNonDb
 * @param {object|null} savedAbility
 * @return {'tbadge'|'desc'|'chips'} Display variant
 */
function callbackTypeDisplayVariant(isNonDb, savedAbility) {
	if (!isNonDb) {
		return 'chips';
	}
	const registeredType = savedAbility?._registry?.callback_type;
	return registeredType ? 'tbadge' : 'desc';
}

/**
 * Replicate the isNonDb config row visibility decision.
 *
 * The config row only renders when isNonDb is true AND the registered
 * callback_type is present.
 *
 * @param {boolean}     isNonDb
 * @param {object|null} savedAbility
 * @return {boolean}
 */
function shouldShowConfigRow(isNonDb, savedAbility) {
	return isNonDb && !!savedAbility?._registry?.callback_type;
}

/**
 * Replicate the config value display decision (pre vs desc).
 *
 * @param {object|null} savedAbility
 * @return {'pre'|'desc'}
 */
function configValueDisplayVariant(savedAbility) {
	return savedAbility?._registry?.callback_config ? 'pre' : 'desc';
}

// ---------------------------------------------------------------------------
// callbackTypeDisplayVariant — isNonDb = true
// ---------------------------------------------------------------------------

describe('callbackTypeDisplayVariant — isNonDb=true', () => {
	test('returns tbadge when _registry.callback_type is present', () => {
		const saved = { _registry: { callback_type: 'rest-api' } };
		expect(callbackTypeDisplayVariant(true, saved)).toBe('tbadge');
	});

	test('returns desc when _registry.callback_type is empty string', () => {
		const saved = { _registry: { callback_type: '' } };
		expect(callbackTypeDisplayVariant(true, saved)).toBe('desc');
	});

	test('returns desc when _registry.callback_type is null', () => {
		const saved = { _registry: { callback_type: null } };
		expect(callbackTypeDisplayVariant(true, saved)).toBe('desc');
	});

	test('returns desc when _registry is absent', () => {
		expect(callbackTypeDisplayVariant(true, {})).toBe('desc');
	});

	test('returns desc when savedAbility is null', () => {
		expect(callbackTypeDisplayVariant(true, null)).toBe('desc');
	});
});

// ---------------------------------------------------------------------------
// callbackTypeDisplayVariant — isNonDb = false (Variant A)
// ---------------------------------------------------------------------------

describe('callbackTypeDisplayVariant — isNonDb=false (Variant A)', () => {
	test('returns chips regardless of _registry content', () => {
		expect(
			callbackTypeDisplayVariant(false, {
				_registry: { callback_type: 'rest-api' },
			})
		).toBe('chips');
	});

	test('returns chips when savedAbility is null', () => {
		expect(callbackTypeDisplayVariant(false, null)).toBe('chips');
	});
});

// ---------------------------------------------------------------------------
// shouldShowConfigRow
// ---------------------------------------------------------------------------

describe('shouldShowConfigRow', () => {
	test('returns true when isNonDb and callback_type present', () => {
		const saved = { _registry: { callback_type: 'rest-api' } };
		expect(shouldShowConfigRow(true, saved)).toBe(true);
	});

	test('returns false when isNonDb but no callback_type', () => {
		const saved = { _registry: {} };
		expect(shouldShowConfigRow(true, saved)).toBe(false);
	});

	test('returns false when isNonDb but savedAbility is null', () => {
		expect(shouldShowConfigRow(true, null)).toBe(false);
	});

	test('returns false when not isNonDb even if callback_type present', () => {
		const saved = { _registry: { callback_type: 'rest-api' } };
		expect(shouldShowConfigRow(false, saved)).toBe(false);
	});
});

// ---------------------------------------------------------------------------
// configValueDisplayVariant
// ---------------------------------------------------------------------------

describe('configValueDisplayVariant', () => {
	test('returns pre when callback_config is a non-empty object', () => {
		const saved = {
			_registry: { callback_config: { url: 'https://example.com/api' } },
		};
		expect(configValueDisplayVariant(saved)).toBe('pre');
	});

	test('returns desc when callback_config is null', () => {
		const saved = { _registry: { callback_config: null } };
		expect(configValueDisplayVariant(saved)).toBe('desc');
	});

	test('returns desc when callback_config is absent', () => {
		const saved = { _registry: {} };
		expect(configValueDisplayVariant(saved)).toBe('desc');
	});

	test('returns desc when savedAbility is null', () => {
		expect(configValueDisplayVariant(null)).toBe('desc');
	});
});
