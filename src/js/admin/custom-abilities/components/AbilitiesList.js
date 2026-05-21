/**
 * DataViews Component for Listing/Managing Custom Abilities
 *
 * Displays custom abilities with search, filtering, pagination,
 * bulk actions, and contextual actions.
 */

import { useEffect, useState, useCallback } from '@wordpress/element';
import { useCustomAbilities } from '../api/useCustomAbilities';
import { Button, Notice, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * AbilitiesList component for viewing and managing custom abilities
 *
 * @param {Object} props Component props
 * @param {string} props.restNamespace REST namespace URL
 * @param {Function} props.onEditAbility Callback when editing an ability
 * @param {Function} props.onCreateAbility Callback when creating new ability
 * @return {JSX.Element} List component
 */
export function AbilitiesList( {
	restNamespace,
	onEditAbility,
	onCreateAbility,
} ) {
	const [ abilities, setAbilities ] = useState( [] );
	const [ filteredAbilities, setFilteredAbilities ] = useState( [] );
	const [ searchTerm, setSearchTerm ] = useState( '' );
	const [ sortField, setSortField ] = useState( 'created_at' );
	const [ sortOrder, setSortOrder ] = useState( 'desc' );
	const [ currentPage, setCurrentPage ] = useState( 1 );
	const [ perPage, setPerPage ] = useState( 20 );
	const [ selectedAbilities, setSelectedAbilities ] = useState( new Set() );
	const [ filterEnabled, setFilterEnabled ] = useState( '' );
	const [ deleteConfirm, setDeleteConfirm ] = useState( null );

	const {
		fetchAbilities,
		deleteAbility,
		updateAbility,
		isLoading,
		error: apiError,
	} = useCustomAbilities( restNamespace );

	// Load abilities on component mount
	useEffect( () => {
		loadAbilities();
	}, [] );

	/**
	 * Fetch abilities from REST API
	 */
	const loadAbilities = useCallback( async () => {
		try {
			const data = await fetchAbilities( {
				per_page: 100, // Load more for client-side filtering
				order: 'desc',
				orderby: 'created_at',
			} );

			setAbilities( data );
			applyFilters( data );
		} catch ( err ) {
			console.error( 'Failed to load abilities:', err );
		}
	}, [ fetchAbilities ] );

	/**
	 * Apply all filters and sorting to abilities list
	 */
	const applyFilters = useCallback(
		( abilityList ) => {
			let filtered = [ ...abilityList ];

			// Search filter
			if ( searchTerm ) {
				const term = searchTerm.toLowerCase();
				filtered = filtered.filter( ( ability ) =>
					ability.ability_slug.toLowerCase().includes( term ) ||
					ability.label.toLowerCase().includes( term ) ||
					( ability.description && ability.description.toLowerCase().includes( term ) )
				);
			}

			// Enabled filter
			if ( filterEnabled !== '' ) {
				const enabled = filterEnabled === '1';
				filtered = filtered.filter( ( ability ) => ability.enabled === enabled );
			}

			// Sort
			filtered.sort( ( a, b ) => {
				let aVal = a[ sortField ];
				let bVal = b[ sortField ];

				if ( typeof aVal === 'string' ) {
					aVal = aVal.toLowerCase();
					bVal = bVal.toLowerCase();
				}

				if ( aVal < bVal ) return sortOrder === 'asc' ? -1 : 1;
				if ( aVal > bVal ) return sortOrder === 'asc' ? 1 : -1;
				return 0;
			} );

			setFilteredAbilities( filtered );
			setCurrentPage( 1 );
		},
		[ searchTerm, sortField, sortOrder, filterEnabled ]
	);

	// Apply filters when search or filters change
	useEffect( () => {
		applyFilters( abilities );
	}, [ searchTerm, sortField, sortOrder, filterEnabled, applyFilters, abilities ] );

	/**
	 * Handle delete confirmation
	 */
	const handleDeleteConfirm = useCallback( async ( id ) => {
		try {
			await deleteAbility( id );
			// Reload abilities after delete
			await loadAbilities();
			setDeleteConfirm( null );
		} catch ( err ) {
			console.error( 'Failed to delete ability:', err );
		}
	}, [ deleteAbility, loadAbilities ] );

	/**
	 * Handle toggle enable/disable
	 */
	const handleToggleEnabled = useCallback(
		async ( ability ) => {
			try {
				await updateAbility( ability.id, {
					...ability,
					enabled: ! ability.enabled,
				} );
				await loadAbilities();
			} catch ( err ) {
				console.error( 'Failed to update ability:', err );
			}
		},
		[ updateAbility, loadAbilities ]
	);

	/**
	 * Handle bulk delete
	 */
	const handleBulkDelete = useCallback( async () => {
		if ( ! window.confirm(
			__( 'Are you sure you want to delete the selected abilities?', 'acrossai-abilities-manager' )
		) ) {
			return;
		}

		try {
			for ( const id of selectedAbilities ) {
				await deleteAbility( id );
			}
			setSelectedAbilities( new Set() );
			await loadAbilities();
		} catch ( err ) {
			console.error( 'Failed to delete abilities:', err );
		}
	}, [ selectedAbilities, deleteAbility, loadAbilities ] );

	/**
	 * Handle bulk enable
	 */
	const handleBulkEnable = useCallback( async () => {
		try {
			for ( const id of selectedAbilities ) {
				const ability = abilities.find( ( a ) => a.id === id );
				if ( ability ) {
					await updateAbility( id, { ...ability, enabled: true } );
				}
			}
			setSelectedAbilities( new Set() );
			await loadAbilities();
		} catch ( err ) {
			console.error( 'Failed to enable abilities:', err );
		}
	}, [ selectedAbilities, abilities, updateAbility, loadAbilities ] );

	/**
	 * Handle bulk disable
	 */
	const handleBulkDisable = useCallback( async () => {
		try {
			for ( const id of selectedAbilities ) {
				const ability = abilities.find( ( a ) => a.id === id );
				if ( ability ) {
					await updateAbility( id, { ...ability, enabled: false } );
				}
			}
			setSelectedAbilities( new Set() );
			await loadAbilities();
		} catch ( err ) {
			console.error( 'Failed to disable abilities:', err );
		}
	}, [ selectedAbilities, abilities, updateAbility, loadAbilities ] );

	// Pagination
	const totalPages = Math.ceil( filteredAbilities.length / perPage );
	const startIndex = ( currentPage - 1 ) * perPage;
	const paginatedAbilities = filteredAbilities.slice( startIndex, startIndex + perPage );

	if ( isLoading ) {
		return <Spinner />;
	}

	const hasSelected = selectedAbilities.size > 0;

	return (
		<div className="acrossai-abilities-list">
			{ apiError && (
				<Notice status="error" onRemove={ () => {} }>
					{ apiError }
				</Notice>
			) }

			{/* Toolbar */}
			<div className="acrossai-list-toolbar">
				<div className="acrossai-toolbar-left">
					<input
						type="text"
						placeholder={ __( 'Search abilities...', 'acrossai-abilities-manager' ) }
						value={ searchTerm }
						onChange={ ( e ) => setSearchTerm( e.target.value ) }
						className="acrossai-search-input"
					/>

					<select
						value={ filterEnabled }
						onChange={ ( e ) => setFilterEnabled( e.target.value ) }
						className="acrossai-filter-select"
					>
						<option value="">{ __( 'All Status', 'acrossai-abilities-manager' ) }</option>
						<option value="1">{ __( 'Enabled', 'acrossai-abilities-manager' ) }</option>
						<option value="0">{ __( 'Disabled', 'acrossai-abilities-manager' ) }</option>
					</select>
				</div>

				<div className="acrossai-toolbar-right">
					<Button
						onClick={ () => onCreateAbility?.() }
						variant="primary"
					>
						{ __( 'Add New Ability', 'acrossai-abilities-manager' ) }
					</Button>
				</div>
			</div>

			{/* Bulk Actions */}
			{ hasSelected && (
				<div className="acrossai-bulk-actions">
					<span>
						{ __( '%d selected', 'acrossai-abilities-manager' ).replace( '%d', selectedAbilities.size ) }
					</span>

					<Button
						onClick={ handleBulkEnable }
						variant="secondary"
						isSmall
					>
						{ __( 'Enable', 'acrossai-abilities-manager' ) }
					</Button>

					<Button
						onClick={ handleBulkDisable }
						variant="secondary"
						isSmall
					>
						{ __( 'Disable', 'acrossai-abilities-manager' ) }
					</Button>

					<Button
						onClick={ handleBulkDelete }
						variant="tertiary"
						isSmall
						isDestructive
					>
						{ __( 'Delete', 'acrossai-abilities-manager' ) }
					</Button>

					<Button
						onClick={ () => setSelectedAbilities( new Set() ) }
						variant="tertiary"
						isSmall
					>
						{ __( 'Clear', 'acrossai-abilities-manager' ) }
					</Button>
				</div>
			) }

			{/* Table */}
			{ paginatedAbilities.length === 0 ? (
				<div className="acrossai-empty-state">
					<p>{ __( 'No custom abilities yet. Create your first ability.', 'acrossai-abilities-manager' ) }</p>
					<Button onClick={ () => onCreateAbility?.() } variant="primary">
						{ __( 'Add New Ability', 'acrossai-abilities-manager' ) }
					</Button>
				</div>
			) : (
				<table className="acrossai-abilities-table">
					<thead>
						<tr>
							<th style={ { width: '30px' } }>
								<input
									type="checkbox"
									checked={ selectedAbilities.size === paginatedAbilities.length && paginatedAbilities.length > 0 }
									onChange={ ( e ) => {
										if ( e.target.checked ) {
											setSelectedAbilities(
												new Set( paginatedAbilities.map( ( a ) => a.id ) )
											);
										} else {
											setSelectedAbilities( new Set() );
										}
									} }
								/>
							</th>
							<SortableHeader
								field="ability_slug"
								label={ __( 'Slug', 'acrossai-abilities-manager' ) }
								sortField={ sortField }
								sortOrder={ sortOrder }
								onSort={ ( field ) => {
									if ( field === sortField ) {
										setSortOrder( sortOrder === 'asc' ? 'desc' : 'asc' );
									} else {
										setSortField( field );
										setSortOrder( 'asc' );
									}
								} }
								style={ { width: '200px' } }
							/>
							<SortableHeader
								field="label"
								label={ __( 'Label', 'acrossai-abilities-manager' ) }
								sortField={ sortField }
								sortOrder={ sortOrder }
								onSort={ ( field ) => {
									if ( field === sortField ) {
										setSortOrder( sortOrder === 'asc' ? 'desc' : 'asc' );
									} else {
										setSortField( field );
										setSortOrder( 'asc' );
									}
								} }
								style={ { width: '250px' } }
							/>
							<th>{ __( 'Status', 'acrossai-abilities-manager' ) }</th>
							<th>{ __( 'Type', 'acrossai-abilities-manager' ) }</th>
							<th style={ { width: '60px' } }>{ __( 'MCP', 'acrossai-abilities-manager' ) }</th>
							<th style={ { width: '100px' } }>{ __( 'Actions', 'acrossai-abilities-manager' ) }</th>
						</tr>
					</thead>
					<tbody>
						{ paginatedAbilities.map( ( ability ) => (
							<tr key={ ability.id }>
								<td>
									<input
										type="checkbox"
										checked={ selectedAbilities.has( ability.id ) }
										onChange={ ( e ) => {
											const newSelected = new Set( selectedAbilities );
											if ( e.target.checked ) {
												newSelected.add( ability.id );
											} else {
												newSelected.delete( ability.id );
											}
											setSelectedAbilities( newSelected );
										} }
									/>
								</td>
								<td className="acrossai-slug">{ ability.ability_slug.replace( 'acrossai-custom-abilities/', '' ) }</td>
								<td className="acrossai-label">{ ability.label }</td>
								<td>
									<span className={ `acrossai-status ${ ability.enabled ? 'enabled' : 'disabled' }` }>
										{ ability.enabled ? __( '✓ Enabled', 'acrossai-abilities-manager' ) : __( '○ Disabled', 'acrossai-abilities-manager' ) }
									</span>
								</td>
								<td>{ ability.callback_type }</td>
								<td>
									{ ability.show_in_mcp ? __( '✓ Yes', 'acrossai-abilities-manager' ) : __( '○ No', 'acrossai-abilities-manager' ) }
								</td>
								<td className="acrossai-actions">
									<Button
										onClick={ () => onEditAbility?.( ability.id ) }
										variant="tertiary"
										isSmall
									>
										{ __( 'Edit', 'acrossai-abilities-manager' ) }
									</Button>

									<Button
										onClick={ () => handleToggleEnabled( ability ) }
										variant="tertiary"
										isSmall
									>
										{ ability.enabled ? __( 'Disable', 'acrossai-abilities-manager' ) : __( 'Enable', 'acrossai-abilities-manager' ) }
									</Button>

									<Button
										onClick={ () => setDeleteConfirm( ability.id ) }
										variant="tertiary"
										isSmall
										isDestructive
									>
										{ __( 'Delete', 'acrossai-abilities-manager' ) }
									</Button>
								</td>
							</tr>
						) ) }
					</tbody>
				</table>
			) }

			{/* Pagination */}
			{ totalPages > 1 && (
				<div className="acrossai-pagination">
					<Button
						onClick={ () => setCurrentPage( Math.max( 1, currentPage - 1 ) ) }
						disabled={ currentPage === 1 }
						variant="tertiary"
					>
						{ __( 'Previous', 'acrossai-abilities-manager' ) }
					</Button>

					<span className="acrossai-page-info">
						{ __( 'Page %d of %d', 'acrossai-abilities-manager' ).replace( '%d', currentPage ).replace( '%d', totalPages ) }
					</span>

					<Button
						onClick={ () => setCurrentPage( Math.min( totalPages, currentPage + 1 ) ) }
						disabled={ currentPage === totalPages }
						variant="tertiary"
					>
						{ __( 'Next', 'acrossai-abilities-manager' ) }
					</Button>

					<select
						value={ perPage }
						onChange={ ( e ) => {
							setPerPage( parseInt( e.target.value ) );
							setCurrentPage( 1 );
						} }
						className="acrossai-per-page"
					>
						<option value="20">20</option>
						<option value="50">50</option>
						<option value="100">100</option>
					</select>
				</div>
			) }

			{/* Delete Confirmation Modal */}
			{ deleteConfirm !== null && (
				<div className="acrossai-modal-overlay">
					<div className="acrossai-modal">
						<h3>{ __( 'Delete Ability?', 'acrossai-abilities-manager' ) }</h3>
						<p>{ __( 'This action cannot be undone.', 'acrossai-abilities-manager' ) }</p>
						<div className="acrossai-modal-actions">
							<Button
								onClick={ () => handleDeleteConfirm( deleteConfirm ) }
								variant="primary"
								isDestructive
							>
								{ __( 'Delete', 'acrossai-abilities-manager' ) }
							</Button>
							<Button
								onClick={ () => setDeleteConfirm( null ) }
								variant="secondary"
							>
								{ __( 'Cancel', 'acrossai-abilities-manager' ) }
							</Button>
						</div>
					</div>
				</div>
			) }
		</div>
	);
}

/**
 * Sortable table header component
 */
function SortableHeader( { field, label, sortField, sortOrder, onSort, style } ) {
	return (
		<th onClick={ () => onSort( field ) } style={ style } className="acrossai-sortable-header">
			{ label }
			{ sortField === field && (
				<span className={ `acrossai-sort-icon ${ sortOrder }` }>
					{ sortOrder === 'asc' ? ' ↑' : ' ↓' }
				</span>
			) }
		</th>
	);
}

export default AbilitiesList;
