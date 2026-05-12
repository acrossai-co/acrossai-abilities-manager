/**
 * DataViews-powered table of registered abilities.
 *
 * @since 0.1.0
 */
import { DataViews } from '@wordpress/dataviews';
import { useDispatch } from '@wordpress/data';
import { useDebounce } from '@wordpress/compose';
import { __ } from '@wordpress/i18n';
import { Button, Spinner } from '@wordpress/components';
import { STORE_NAME } from '../store/index';

/**
 * Status cell renderer — shows site_allowed with default indicator.
 *
 * @param {Object} ability Ability data.
 * @return {JSX.Element}
 */
function StatusCell( { item } ) {
	const allowed      = item.site_allowed;
	const hasOverride  = item.has_override;
	const registryVal  = item._registry ? item._registry.site_allowed : null;

	let label;
	let className;

	if ( false === allowed ) {
		label     = hasOverride ? __( 'Disallowed', 'acrossai-abilities-manager' ) : __( 'Disallowed (Default)', 'acrossai-abilities-manager' );
		className = 'acrossai-status-badge acrossai-status-badge--disallowed' + ( ! hasOverride ? ' acrossai-status-badge--default' : '' );
	} else {
		label     = hasOverride ? __( 'Allowed', 'acrossai-abilities-manager' ) : __( 'Allowed (Default)', 'acrossai-abilities-manager' );
		className = 'acrossai-status-badge acrossai-status-badge--allowed' + ( ! hasOverride ? ' acrossai-status-badge--default' : '' );
	}

	return <span className={ className }>{ label }</span>;
}

/**
 * AbilityTable component.
 *
 * @param {Object}   props
 * @param {Array}    props.abilities        List of merged ability objects.
 * @param {number}   props.total            Total count.
 * @param {number}   props.pages            Total pages.
 * @param {boolean}  props.isLoading        Loading state.
 * @param {Object}   props.view             Current DataViews view state.
 * @param {Function} props.onViewChange     Called when view changes.
 * @param {Array}    props.selectedSlugs    Currently selected slugs.
 * @param {Function} props.onSelectionChange Called when selection changes.
 * @return {JSX.Element}
 */
export default function AbilityTable( {
	abilities,
	total,
	pages,
	isLoading,
	view,
	onViewChange,
	selectedSlugs,
	onSelectionChange,
} ) {
	const dispatch = useDispatch( STORE_NAME );

	// Debounce search input (500 ms).
	const debouncedSearch = useDebounce( view.search, 500 );

	// Track per-row loading for toggle operations.
	const fields = [
		{
			id:         'slug',
			label:      __( 'Slug', 'acrossai-abilities-manager' ),
			getValue:   ( { item } ) => item.slug,
			enableSorting: true,
			enableSearch:  true,
		},
		{
			id:       'provider',
			label:    __( 'Provider', 'acrossai-abilities-manager' ),
			getValue: ( { item } ) => item.provider || '—',
			enableSorting:   true,
			enableHiding:    true,
		},
		{
			id:       'source',
			label:    __( 'Source', 'acrossai-abilities-manager' ),
			getValue: ( { item } ) => item.source || '—',
			enableSorting: true,
			elements:      [
				{ value: 'plugin', label: __( 'Plugin', 'acrossai-abilities-manager' ) },
				{ value: 'theme',  label: __( 'Theme', 'acrossai-abilities-manager' ) },
				{ value: 'core',   label: __( 'Core', 'acrossai-abilities-manager' ) },
				{ value: 'db',     label: __( 'DB', 'acrossai-abilities-manager' ) },
			],
			enableHiding: true,
		},
		{
			id:       'status',
			label:    __( 'Status', 'acrossai-abilities-manager' ),
			render:   ( { item } ) => <StatusCell item={ item } />,
			enableSorting: true,
			enableHiding:  true,
		},
		{
			id:       'updated_at',
			label:    __( 'Last Updated', 'acrossai-abilities-manager' ),
			getValue: ( { item } ) => item.updated_at || '—',
			enableSorting: true,
			enableHiding:  true,
		},
	];

	const actions = [
		{
			id:    'toggle',
			label: ( item ) => item.site_allowed !== false
				? __( 'Disallow', 'acrossai-abilities-manager' )
				: __( 'Allow', 'acrossai-abilities-manager' ),
			callback: ( items ) => {
				const item = Array.isArray( items ) ? items[ 0 ] : items;
				dispatch.toggleAllowed( item.slug, item.site_allowed === false );
			},
			icon:      'toggle-off',
			isPrimary: true,
		},
		{
			id:    'edit',
			label: __( 'Edit', 'acrossai-abilities-manager' ),
			callback: ( items ) => {
				const item = Array.isArray( items ) ? items[ 0 ] : items;
				dispatch.openEditPanel( item.slug );
				dispatch.fetchMcpServers();
			},
		},
		{
			id:       'reset',
			label:    __( 'Reset Override', 'acrossai-abilities-manager' ),
			callback: ( items ) => {
				const item = Array.isArray( items ) ? items[ 0 ] : items;
				dispatch.deleteOverride( item.slug );
			},
			isDestructive: true,
			isEligible: ( item ) => !! item.has_override,
		},
	];

	const paginationInfo = {
		totalItems: total,
		totalPages: pages,
	};

	// Empty state.
	if ( ! isLoading && abilities.length === 0 ) {
		return (
			<div className="acrossai-abilities-empty">
				<p>{ __( 'No abilities registered yet.', 'acrossai-abilities-manager' ) }</p>
			</div>
		);
	}

	return (
		<div className="acrossai-abilities-table-wrap">
			{ isLoading && <Spinner /> }
			<DataViews
				data={ abilities }
				fields={ fields }
				view={ view }
				onChangeView={ onViewChange }
				actions={ actions }
				paginationInfo={ paginationInfo }
				selection={ selectedSlugs }
				onChangeSelection={ onSelectionChange }
				getItemId={ ( item ) => item.slug }
				defaultLayouts={ { table: {} } }
			/>
		</div>
	);
}
