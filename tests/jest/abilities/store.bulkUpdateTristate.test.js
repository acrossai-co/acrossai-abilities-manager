/**
 * Jest tests for the bulkUpdateTristate store thunk — Feature 056.
 *
 * Guards:
 * - BUG-MERGER-BOOL-STRING-CAST: payload must send raw JSON true/false/null,
 *   never string aliases (SEC-005 finding).
 * - SEC-001: partial per-slug failure must re-throw so AbilitiesList.jsx
 *   handleBulkApply keeps the selection intact and surfaces the error.
 *
 * @since 0.0.15
 */

jest.mock('@wordpress/data', () => ({
	createReduxStore: jest.fn((name, config) => config),
	register: jest.fn(),
	dispatch: jest.fn(),
	select: jest.fn(),
}));
jest.mock('@wordpress/i18n', () => ({ __: (v) => v }));
jest.mock('../../../src/js/abilities/api/client.js', () => ({
	getAbilities: jest.fn(),
	getAbility: jest.fn(),
	createAbility: jest.fn(),
	updateAbility: jest.fn(),
	deleteAbility: jest.fn(),
	getCategories: jest.fn(),
	deleteOverride: jest.fn(),
}));

const api = require('../../../src/js/abilities/api/client.js');
const storeConfig = require('../../../src/js/abilities/store/index.js');
const actions = storeConfig.store.actions;

describe('bulkUpdateTristate — success path', () => {
	beforeEach(() => {
		api.updateAbility.mockReset();
		api.getAbilities.mockReset();
		api.updateAbility.mockResolvedValue({ ability_slug: 'x' });
		api.getAbilities.mockResolvedValue({
			abilities: [],
			total: 0,
			pages: 1,
		});
	});

	test('fires one updateAbility call per slug', async () => {
		const thunk = actions.bulkUpdateTristate(
			['a', 'b', 'c'],
			'site_allowed',
			true
		);
		const dispatch = jest.fn(() => Promise.resolve());
		await thunk({ dispatch });
		expect(api.updateAbility).toHaveBeenCalledTimes(3);
		expect(api.updateAbility).toHaveBeenNthCalledWith(1, 'a', {
			site_allowed: true,
		});
		expect(api.updateAbility).toHaveBeenNthCalledWith(2, 'b', {
			site_allowed: true,
		});
		expect(api.updateAbility).toHaveBeenNthCalledWith(3, 'c', {
			site_allowed: true,
		});
	});

	test('dispatches fetchAbilities after all resolve (list refetch)', async () => {
		const thunk = actions.bulkUpdateTristate(['x'], 'site_allowed', null);
		const dispatch = jest.fn(() => Promise.resolve());
		await thunk({ dispatch });
		// Two setSaving + one fetchAbilities thunk call.
		const savingCalls = dispatch.mock.calls.filter(
			(c) => c[0]?.type === 'SET_SAVING'
		);
		expect(savingCalls).toHaveLength(2);
		expect(savingCalls[0][0]).toEqual({
			type: 'SET_SAVING',
			isSaving: true,
		});
		expect(savingCalls[1][0]).toEqual({
			type: 'SET_SAVING',
			isSaving: false,
		});
	});

	test('works with the show_in_mcp field too (covers US2)', async () => {
		const thunk = actions.bulkUpdateTristate(
			['a', 'b'],
			'show_in_mcp',
			false
		);
		const dispatch = jest.fn(() => Promise.resolve());
		await thunk({ dispatch });
		expect(api.updateAbility).toHaveBeenNthCalledWith(1, 'a', {
			show_in_mcp: false,
		});
		expect(api.updateAbility).toHaveBeenNthCalledWith(2, 'b', {
			show_in_mcp: false,
		});
	});
});

describe('bulkUpdateTristate — payload discipline (BUG-MERGER-BOOL-STRING-CAST)', () => {
	beforeEach(() => {
		api.updateAbility.mockReset();
		api.updateAbility.mockResolvedValue({ ability_slug: 'x' });
	});

	test('sends raw boolean true, not "true" or 1', async () => {
		const thunk = actions.bulkUpdateTristate(['a'], 'site_allowed', true);
		await thunk({ dispatch: jest.fn(() => Promise.resolve()) });
		const [, payload] = api.updateAbility.mock.calls[0];
		expect(payload.site_allowed).toBe(true);
		expect(typeof payload.site_allowed).toBe('boolean');
	});

	test('sends raw boolean false, not "false" or 0', async () => {
		const thunk = actions.bulkUpdateTristate(['a'], 'site_allowed', false);
		await thunk({ dispatch: jest.fn(() => Promise.resolve()) });
		const [, payload] = api.updateAbility.mock.calls[0];
		expect(payload.site_allowed).toBe(false);
		expect(typeof payload.site_allowed).toBe('boolean');
	});

	test('sends raw null, not "null" or "inherit"', async () => {
		const thunk = actions.bulkUpdateTristate(['a'], 'site_allowed', null);
		await thunk({ dispatch: jest.fn(() => Promise.resolve()) });
		const [, payload] = api.updateAbility.mock.calls[0];
		expect(payload.site_allowed).toBeNull();
	});
});

describe('bulkUpdateTristate — SEC-001 partial-failure re-throw', () => {
	test('re-throws when any per-slug call rejects', async () => {
		api.updateAbility.mockReset();
		api.updateAbility
			.mockResolvedValueOnce({ ability_slug: 'a' })
			.mockRejectedValueOnce(new Error('boom'))
			.mockResolvedValueOnce({ ability_slug: 'c' });
		const dispatch = jest.fn(() => Promise.resolve());
		const thunk = actions.bulkUpdateTristate(
			['a', 'b', 'c'],
			'site_allowed',
			true
		);
		await expect(thunk({ dispatch })).rejects.toThrow('boom');
	});

	test('dispatches SET_SAVE_ERROR on failure so top-of-page notice renders', async () => {
		api.updateAbility.mockReset();
		api.updateAbility.mockRejectedValue(new Error('nope'));
		const dispatch = jest.fn(() => Promise.resolve());
		const thunk = actions.bulkUpdateTristate(['a'], 'site_allowed', true);
		await expect(thunk({ dispatch })).rejects.toThrow('nope');
		const saveErrorCall = dispatch.mock.calls.find(
			(c) => c[0]?.type === 'SET_SAVE_ERROR'
		);
		expect(saveErrorCall).toBeDefined();
		expect(saveErrorCall[0].error).toBe('nope');
	});

	test('clears the isSaving flag even when the thunk rejects', async () => {
		api.updateAbility.mockReset();
		api.updateAbility.mockRejectedValue(new Error('boom'));
		const dispatch = jest.fn(() => Promise.resolve());
		const thunk = actions.bulkUpdateTristate(['a'], 'site_allowed', true);
		await expect(thunk({ dispatch })).rejects.toThrow('boom');
		const savingFalse = dispatch.mock.calls.find(
			(c) =>
				c[0]?.type === 'SET_SAVING' && false === c[0]?.isSaving
		);
		expect(savingFalse).toBeDefined();
	});
});
