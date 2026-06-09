import { CheckboxControl, RadioControl, ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Card renderer for a single main_key ability group.
 *
 * Header row: toggle + label on left, All/Specific radio on right.
 * Sub-key checkboxes appear below when mode is "specific".
 *
 * @param {Object}   props
 * @param {Object}   props.item     { mainKey, mainKeyLabel, subKeys: [{subKey, subKeyLabel}] }
 * @param {Object}   props.config   Full config keyed by main_key.
 * @param {Function} props.onChange Called with (mainKey, updatedEntry) on any change.
 */
export default function LibraryCard({ item, config, onChange }) {
	const { mainKey, mainKeyLabel, subKeys } = item;
	const entry = config[mainKey] ?? {
		enabled: true,
		mode: 'all',
		sub_keys: {},
	};

	const enabled = entry.enabled ?? true;
	const mode = entry.mode ?? 'all';
	const subKeysConfig = entry.sub_keys ?? {};

	function update(patch) {
		onChange(mainKey, { ...entry, ...patch });
	}

	return (
		<div className="acrossai-library-card">
			<div className="acrossai-library-card__header">
				<ToggleControl
					__nextHasNoMarginBottom
					label={<strong>{mainKeyLabel}</strong>}
					checked={enabled}
					onChange={(value) => update({ enabled: value })}
				/>

				{enabled && (
					<RadioControl
						className="acrossai-library-card__mode"
						selected={mode}
						options={[
							{
								label: __('All', 'acrossai-abilities-manager'),
								value: 'all',
							},
							{
								label: __(
									'Specific',
									'acrossai-abilities-manager'
								),
								value: 'specific',
							},
						]}
						onChange={(value) =>
						update({ mode: value, sub_keys: value === 'all' ? {} : subKeysConfig })
					}
					/>
				)}
			</div>

			{enabled && mode === 'specific' && subKeys.length > 0 && (
				<div className="acrossai-library-card__sub-keys">
					{subKeys.map(({ subKey, subKeyLabel }) => (
						<CheckboxControl
							__nextHasNoMarginBottom
							key={subKey}
							label={subKeyLabel}
							checked={subKeysConfig[subKey] ?? false}
							onChange={(value) =>
								update({
									sub_keys: {
										...subKeysConfig,
										[subKey]: value,
									},
								})
							}
						/>
					))}
				</div>
			)}
		</div>
	);
}
