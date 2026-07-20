/**
 * Jest tests for the bulkClearOverrides store thunk — Feature 056 FR-018.
 *
 * Force Reset: loops the pre-existing per-slug DELETE /abilities/{slug}/override
 * endpoint under Promise.all so every selected ability returns to its
 * source-declared defaults (all override columns wiped).
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
	deleteOverride: jest.fn(),
	getCategories: jest.fn(),
	setAccessControlRule: jest.fn(),
}));

const api = require('../../../src/js/abilities/api/client.js');
const storeConfig = require('../../../src/js/abilities/store/index.js');
const actions = storeConfig.store.actions;

describe('bulkClearOverrides — success path', () => {
	beforeEach(() => {
		api.deleteOverride.mockReset();
		api.getAbilities.mockReset();
		api.deleteOverride.mockResolvedValue({ ability_slug: 'x' });
		api.getAbilities.mockResolvedValue({
			abilities: [],
			total: 0,
			pages: 1,
		});
	});

	test('fires one deleteOverride call per slug', async () => {
		const thunk = actions.bulkClearOverrides(['a', 'b', 'c']);
		const dispatch = jest.fn(() => Promise.resolve());
		await thunk({ dispatch });
		expect(api.deleteOverride).toHaveBeenCalledTimes(3);
		expect(api.deleteOverride).toHaveBeenNthCalledWith(1, 'a');
		expect(api.deleteOverride).toHaveBeenNthCalledWith(2, 'b');
		expect(api.deleteOverride).toHaveBeenNthCalledWith(3, 'c');
	});

	test('brackets the dispatch chain with SET_SAVING true/false', async () => {
		const thunk = actions.bulkClearOverrides(['x']);
		const dispatch = jest.fn(() => Promise.resolve());
		await thunk({ dispatch });
		const savingCalls = dispatch.mock.calls.filter(
			(c) => c[0]?.type === 'SET_SAVING'
		);
		expect(savingCalls).toHaveLength(2);
		expect(savingCalls[0][0].isSaving).toBe(true);
		expect(savingCalls[1][0].isSaving).toBe(false);
	});
});

describe('bulkClearOverrides — partial-failure re-throw (SEC-001 pattern)', () => {
	test('re-throws when any per-slug DELETE rejects', async () => {
		api.deleteOverride.mockReset();
		api.deleteOverride
			.mockResolvedValueOnce({ ability_slug: 'a' })
			.mockRejectedValueOnce(new Error('boom'))
			.mockResolvedValueOnce({ ability_slug: 'c' });
		const dispatch = jest.fn(() => Promise.resolve());
		const thunk = actions.bulkClearOverrides(['a', 'b', 'c']);
		await expect(thunk({ dispatch })).rejects.toThrow('boom');
	});

	test('dispatches SET_SAVE_ERROR on failure so the top-of-page notice renders', async () => {
		api.deleteOverride.mockReset();
		api.deleteOverride.mockRejectedValue(new Error('nope'));
		const dispatch = jest.fn(() => Promise.resolve());
		const thunk = actions.bulkClearOverrides(['a']);
		await expect(thunk({ dispatch })).rejects.toThrow('nope');
		const saveErrorCall = dispatch.mock.calls.find(
			(c) => c[0]?.type === 'SET_SAVE_ERROR'
		);
		expect(saveErrorCall).toBeDefined();
		expect(saveErrorCall[0].error).toBe('nope');
	});

	test('clears the isSaving flag even when the thunk rejects', async () => {
		api.deleteOverride.mockReset();
		api.deleteOverride.mockRejectedValue(new Error('boom'));
		const dispatch = jest.fn(() => Promise.resolve());
		const thunk = actions.bulkClearOverrides(['a']);
		await expect(thunk({ dispatch })).rejects.toThrow('boom');
		const savingFalse = dispatch.mock.calls.find(
			(c) =>
				c[0]?.type === 'SET_SAVING' && false === c[0]?.isSaving
		);
		expect(savingFalse).toBeDefined();
	});
});
