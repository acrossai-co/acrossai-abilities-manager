import { useEffect, useMemo, useRef, useState } from '@wordpress/element';
import { Notice, TabPanel } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { Icon, plugins } from '@wordpress/icons';
import { fetchConfig, saveConfig } from '../api';
import LibraryCard from './LibraryCard';

/**
 * Sentinel name for the always-present default tab. Underscores chosen to
 * avoid collision with any sanitize_key() output (which strips underscores
 * inside identifiers but allows them as full names).
 */
export const ALL_TABS_KEY = '__all__';

/**
 * Group flat definitions array by category into card items.
 *
 * Named export so the helper can be unit-tested without rendering React
 * (per PATTERN-NAMED-EXPORT-JEST).
 *
 * @param {Array} definitions Raw definitions from window.acrossaiAbilityLibraryData.
 * @return {Array} Grouped items, one per category.
 */
export function groupDefinitions(definitions) {
	const map = new Map();
	for (const def of definitions) {
		const {
			category,
			category_label: categoryLabel,
			slug,
			slug_label: slugLabel,
			name,
			sub_group: subGroup,
			sub_group_label: subGroupLabel,
			tab_group: tabGroup,
			args,
		} = def;
		const description =
			typeof args?.description === 'string'
				? args.description.trim()
				: '';
		if (!map.has(category)) {
			map.set(category, {
				id: category,
				category,
				categoryLabel,
				slugs: [],
			});
		}
		const group = map.get(category);
		if (!group.slugs.some((s) => s.slug === slug)) {
			group.slugs.push({
				slug,
				slugLabel,
				name,
				subGroup: subGroup || '',
				subGroupLabel: subGroupLabel || '',
				tabGroup: tabGroup || '',
				description,
			});
		}
	}
	return Array.from(map.values());
}

/**
 * Reserved tab identifier pinned to the first position in the tab list
 * (i.e. immediately after the `All` tab).
 *
 * Feature 046: the absorbed acrossai-core-abilities plugin's default
 * `tab_group` is `core`. Site admins expect the Core tab to appear as
 * the second option (right after `All`) even after other categories
 * introduce their own tab_groups. When `core` is absent from the
 * definitions this pin is a no-op.
 */
const PINNED_FIRST_TAB_GROUP = 'core';

/**
 * Collect the unique non-empty tab_group identifiers across all slug records.
 *
 * Sort: `core` is pinned first (Feature 046); remaining identifiers sort
 * case-insensitive alphabetical by sanitized identifier (FR-013).
 * Named export per PATTERN-NAMED-EXPORT-JEST.
 *
 * @param {Array} items Output of groupDefinitions().
 * @return {string[]} Sorted unique tab_group identifiers.
 */
export function collectTabGroups(items) {
	const set = new Set();
	for (const item of items) {
		for (const slug of item.slugs) {
			if (slug.tabGroup) {
				set.add(slug.tabGroup);
			}
		}
	}
	const sorted = Array.from(set).sort((a, b) =>
		a.toLowerCase().localeCompare(b.toLowerCase())
	);
	const coreIndex = sorted.indexOf(PINNED_FIRST_TAB_GROUP);
	if (coreIndex > 0) {
		sorted.splice(coreIndex, 1);
		sorted.unshift(PINNED_FIRST_TAB_GROUP);
	}
	return sorted;
}

/**
 * Filter the grouped items by the active tab.
 *
 * When activeTab equals ALL_TABS_KEY, returns the input array reference
 * unchanged (so React useMemo identity is stable). Otherwise, returns new
 * item objects whose slugs array is filtered to entries matching the
 * active tab; items with no surviving slug are dropped.
 *
 * Named export per PATTERN-NAMED-EXPORT-JEST.
 *
 * @param {Array}  items     Output of groupDefinitions().
 * @param {string} activeTab Active tab identifier or ALL_TABS_KEY.
 * @return {Array} Filtered items.
 */
export function filterItemsByTabGroup(items, activeTab) {
	if (activeTab === ALL_TABS_KEY) {
		return items;
	}
	const out = [];
	for (const item of items) {
		const matching = item.slugs.filter((s) => s.tabGroup === activeTab);
		if (matching.length > 0) {
			out.push({ ...item, slugs: matching });
		}
	}
	return out;
}

/**
 * Convert a sanitized tab_group identifier into a display label.
 *
 * Mirrors the PHP `ucwords( str_replace( '-', ' ', $value ) )` rule used
 * by category_label so PHP-side and JS-side labels look identical for
 * the same identifier.
 *
 * Named export per PATTERN-NAMED-EXPORT-JEST.
 *
 * @param {string} value Sanitized tab_group identifier.
 * @return {string} Display label.
 */
export function titleCaseTabLabel(value) {
	if (typeof value !== 'string' || value === '') {
		return '';
	}
	return value
		.replace(/-/g, ' ')
		.split(' ')
		.map((word) =>
			word.length > 0 ? word[0].toUpperCase() + word.slice(1) : word
		)
		.join(' ');
}

/**
 * Library admin page — renders one LibraryCard per registered ability group.
 */
export default function LibraryPage() {
	const data = window.acrossaiAbilityLibraryData || {};
	const items = groupDefinitions(data.definitions || []);

	const [config, setConfig] = useState({});
	const [error, setError] = useState(null);

	const initialLoadComplete = useRef(false);

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

	function handleChange(category, updatedEntry) {
		const next = { ...config, [category]: updatedEntry };
		setConfig(next);

		if (!initialLoadComplete.current) {
			return;
		}

		setError(null);
		saveConfig(next).catch(() =>
			setError(
				__(
					'Failed to save configuration.',
					'acrossai-abilities-manager'
				)
			)
		);
	}

	const tabGroups = useMemo(() => collectTabGroups(items), [items]);

	function renderCards(visibleItems) {
		return visibleItems.map((item) => (
			<LibraryCard
				key={item.category}
				item={item}
				config={config}
				onChange={handleChange}
			/>
		));
	}

	const tabs = useMemo(
		() => [
			{
				name: ALL_TABS_KEY,
				title: __('All', 'acrossai-abilities-manager'),
			},
			...tabGroups.map((g) => ({ name: g, title: titleCaseTabLabel(g) })),
		],
		[tabGroups]
	);

	return (
		<div className="acrossai-library-page">
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
				<div
					className="acrossai-library-page__empty"
					role="region"
					aria-labelledby="acrossai-library-empty-title"
				>
					<div className="acrossai-library-page__empty-icon">
						<Icon icon={plugins} size={40} />
					</div>
					<h2
						id="acrossai-library-empty-title"
						className="acrossai-library-page__empty-title"
					>
						{__(
							'Ability library is empty',
							'acrossai-abilities-manager'
						)}
					</h2>
					<p className="acrossai-library-page__empty-description">
						{__(
							'The bundled abilities did not load this request. Try deactivating and reactivating the plugin, then reload this page. If the problem persists, check the WordPress debug log for filter or class-loading errors.',
							'acrossai-abilities-manager'
						)}
					</p>
				</div>
			)}

			{items.length > 0 && tabGroups.length === 0 && renderCards(items)}

			{items.length > 0 && tabGroups.length > 0 && (
				<TabPanel
					className="acrossai-library-page__tabs"
					tabs={tabs}
					initialTabName={ALL_TABS_KEY}
				>
					{(tab) =>
						renderCards(filterItemsByTabGroup(items, tab.name))
					}
				</TabPanel>
			)}
		</div>
	);
}
