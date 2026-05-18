/**
 * Logs Table Component
 *
 * Renders a sortable, filterable, searchable logs table using @wordpress/dataviews.
 * Connects to REST endpoint: /wp-json/acrossai-abilities/v1/logger/logs
 *
 * @since 0.1.0
 */

import { DataViews } from '@wordpress/dataviews';
import { useEffect, useState, useCallback } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { sprintf } from '@wordpress/i18n';

const LogsTable = ( { restEndpoint = '/wp-json/acrossai-abilities/v1/logger/logs' } ) => {
	const [ logs, setLogs ] = useState( [] );
	const [ isLoading, setIsLoading ] = useState( false );
	const [ error, setError ] = useState( null );
	const [ totalRecords, setTotalRecords ] = useState( 0 );
	const [ totalPages, setTotalPages ] = useState( 0 );

	// Define columns for DataViews
	const columns = [
		{
			id: 'ability_slug',
			label: 'Ability',
			type: 'text',
			render: ( { item } ) => item.ability_slug || '—',
			sortingValue: ( { item } ) => item.ability_slug,
			isVisible: true,
		},
		{
			id: 'source',
			label: 'Source',
			type: 'enumeration',
			elements: [
				{ value: 'mcp', label: 'MCP' },
				{ value: 'rest', label: 'REST' },
				{ value: 'cli', label: 'CLI' },
				{ value: 'cron', label: 'Cron' },
				{ value: 'ajax', label: 'AJAX' },
				{ value: 'direct', label: 'Direct' },
			],
			render: ( { item } ) => item.source || '—',
			sortingValue: ( { item } ) => item.source,
			isVisible: true,
		},
		{
			id: 'user_id',
			label: 'User',
			type: 'integer',
			render: ( { item } ) => item.user_id > 0 ? `User #${ item.user_id }` : 'System',
			sortingValue: ( { item } ) => item.user_id,
			isVisible: true,
		},
		{
			id: 'status',
			label: 'Status',
			type: 'enumeration',
			elements: [
				{ value: 'success', label: 'Success' },
				{ value: 'error', label: 'Error' },
				{ value: 'permission_denied', label: 'Permission Denied' },
			],
			render: ( { item } ) => {
				const statusColors = {
					success: '#28a745',
					error: '#dc3545',
					permission_denied: '#ffc107',
				};
				return (
					<span
						style={ {
							backgroundColor: statusColors[ item.status ] || '#6c757d',
							color: item.status === 'permission_denied' ? '#000' : '#fff',
							padding: '4px 8px',
							borderRadius: '4px',
							fontSize: '12px',
							fontWeight: '500',
						} }
					>
						{ item.status === 'permission_denied' ? 'Denied' : item.status }
					</span>
				);
			},
			sortingValue: ( { item } ) => item.status,
			isVisible: true,
		},
		{
			id: 'duration_ms',
			label: 'Duration',
			type: 'integer',
			render: ( { item } ) => `${ item.duration_ms } ms`,
			sortingValue: ( { item } ) => item.duration_ms,
			isVisible: true,
		},
		{
			id: 'created_at',
			label: 'Executed At',
			type: 'date',
			render: ( { item } ) => {
				try {
					const date = new Date( item.created_at );
					return date.toLocaleString();
				} catch ( e ) {
					return item.created_at || '—';
				}
			},
			sortingValue: ( { item } ) => new Date( item.created_at ).getTime(),
			isVisible: true,
		},
	];

	// Fetch logs from REST endpoint
	const fetchLogs = useCallback(
		( {
			search = '',
			orderby = 'created_at',
			order = 'DESC',
			source = '',
			status = '',
			ability_slug = '',
			user_id = '',
			page = 1,
			per_page = 20,
		} = {} ) => {
			setIsLoading( true );
			setError( null );

			const params = new URLSearchParams();
			if ( search ) params.append( 'search', search );
			if ( orderby ) params.append( 'orderby', orderby );
			if ( order ) params.append( 'order', order );
			if ( source ) params.append( 'source', source );
			if ( status ) params.append( 'status', status );
			if ( ability_slug ) params.append( 'ability_slug', ability_slug );
			if ( user_id ) params.append( 'user_id', user_id );
			params.append( 'page', page );
			params.append( 'per_page', Math.min( per_page, 100 ) ); // Cap at 100

			apiFetch( {
				path: `${ restEndpoint }?${ params.toString() }`,
			} )
				.then( ( response ) => {
					// Handle response (could be array or object with logs property)
					const logsArray = Array.isArray( response ) ? response : response.logs || [];
					const total = response.total || 0;
					const pages = response.pages || 0;

					setLogs( logsArray );
					setTotalRecords( total );
					setTotalPages( pages );
				} )
				.catch( ( err ) => {
					setError( err.message || 'Failed to load logs' );
					setLogs( [] );
				} )
				.finally( () => {
					setIsLoading( false );
				} );
		},
		[ restEndpoint ]
	);

	// Initial load
	useEffect( () => {
		fetchLogs();
	}, [ fetchLogs ] );

	// Handle view changes (search, sort, filter, pagination)
	const handleViewChange = useCallback(
		( newView ) => {
			const {
				search = '',
				sort = { field: 'created_at', direction: 'DESC' },
				filters = {},
				page = 1,
				perPage = 20,
			} = newView;

			const filters_obj = filters || {};

			fetchLogs( {
				search,
				orderby: sort.field || 'created_at',
				order: sort.direction || 'DESC',
				source: filters_obj.source || '',
				status: filters_obj.status || '',
				ability_slug: filters_obj.ability_slug || '',
				user_id: filters_obj.user_id || '',
				page,
				per_page: perPage,
			} );
		},
		[ fetchLogs ]
	);

	// Render error state
	if ( error ) {
		return (
			<div style={ { color: '#dc3545', padding: '16px', backgroundColor: '#f8d7da', borderRadius: '4px' } }>
				<strong>Error loading logs:</strong> { error }
			</div>
		);
	}

	// Render empty state
	if ( ! isLoading && logs.length === 0 ) {
		return (
			<div style={ { color: '#6c757d', padding: '32px', textAlign: 'center' } }>
				<p>No logs found. Executions will appear here.</p>
			</div>
		);
	}

	return (
		<div id="acrossai-logs-container">
			{ isLoading ? (
				<div style={ { padding: '32px', textAlign: 'center', color: '#6c757d' } }>
					Loading logs...
				</div>
			) : (
				<DataViews
					columns={ columns }
					data={ logs }
					isLoading={ isLoading }
					paginationInfo={ {
						totalItems: totalRecords,
						totalPages,
					} }
					onChangeView={ handleViewChange }
					defaultLayouts={ {
						table: {},
					} }
					view={ {
						type: 'table',
						perPage: 20,
						page: 1,
						sort: { field: 'created_at', direction: 'DESC' },
						search: '',
						filters: {},
					} }
				/>
			) }
		</div>
	);
};

export default LogsTable;
