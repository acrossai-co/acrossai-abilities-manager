import { useEffect, useRef, useState } from '@wordpress/element';
import { Notice, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { fetchConfig, saveConfig } from '../api';
import LibraryCard from './LibraryCard';

/**
 * Group flat definitions array by main_key into card items.
 *
 * @param {Array} definitions Raw definitions from window.acrossaiAbilityLibraryData.
 * @return {Array} Grouped items, one per main_key.
 */
function groupDefinitions(definitions) {
	const map = new Map();
	for (const def of definitions) {
		const {
			main_key: mainKey,
			main_key_label: mainKeyLabel,
			sub_key: subKey,
			sub_key_label: subKeyLabel,
		} = def;
		if (!map.has(mainKey)) {
			map.set(mainKey, { id: mainKey, mainKey, mainKeyLabel, subKeys: [] });
		}
		const group = map.get(mainKey);
		if (!group.subKeys.some((s) => s.subKey === subKey)) {
			group.subKeys.push({ subKey, subKeyLabel });
		}
	}
	return Array.from(map.values());
}

/**
 * Library admin page — renders one LibraryCard per registered ability group.
 */
export default function LibraryPage() {
	const data = window.acrossaiAbilityLibraryData || {};
	const items = groupDefinitions(data.definitions || []);

	const [config, setConfig] = useState({});
	const [isSaving, setIsSaving] = useState(false);
	const [error, setError] = useState(null);

	const initialLoadComplete = useRef(false);
	const debounceTimer = useRef(null);

	useEffect(() => {
		fetchConfig()
			.then((saved) => {
				setConfig(saved);
				initialLoadComplete.current = true;
			})
			.catch(() => {
				setError(
					__(
						'Failed to load configuration.',
						'acrossai-abilities-manager'
					)
				);
				initialLoadComplete.current = true;
			});
	}, []);

	function handleChange(mainKey, updatedEntry) {
		const next = { ...config, [mainKey]: updatedEntry };
		setConfig(next);

		if (!initialLoadComplete.current) {
			return;
		}

		clearTimeout(debounceTimer.current);
		debounceTimer.current = setTimeout(() => {
			setIsSaving(true);
			setError(null);
			saveConfig(next)
				.catch(() =>
					setError(
						__(
							'Failed to save configuration.',
							'acrossai-abilities-manager'
						)
					)
				)
				.finally(() => setIsSaving(false));
		}, 1000);
	}

	return (
		<div className="acrossai-library-page">
			{isSaving && (
				<div className="acrossai-library-page__saving">
					<Spinner />
					<span>{__('Saving…', 'acrossai-abilities-manager')}</span>
				</div>
			)}

			{error && (
				<Notice
					status="error"
					isDismissible
					onRemove={() => setError(null)}
				>
					{error}
				</Notice>
			)}

			{items.length === 0 && (
				<p className="acrossai-library-page__empty">
					{__(
						'No abilities registered yet. Activate an add-on that provides abilities.',
						'acrossai-abilities-manager'
					)}
				</p>
			)}

			{items.map((item) => (
				<LibraryCard
					key={item.mainKey}
					item={item}
					config={config}
					onChange={handleChange}
				/>
			))}
		</div>
	);
}
