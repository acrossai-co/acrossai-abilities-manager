/**
 * React Hook for Custom Abilities REST API Operations
 *
 * Provides async methods for CRUD operations via REST API.
 */

import apiFetch from '@wordpress/api-fetch';
import { useCallback, useState } from '@wordpress/element';

/**
 * Hook for managing custom abilities via REST API
 *
 * @param {string} restNamespace REST namespace URL (e.g., '/wp-json/acrossai-abilities-manager/v1')
 * @return {Object} Object with methods and state for ability management
 */
export function useCustomAbilities( restNamespace ) {
	const [ error, setError ] = useState( null );
	const [ isLoading, setIsLoading ] = useState( false );

	/**
	 * Fetch all custom abilities with optional filtering
	 *
	 * @param {Object} params Query parameters (search, page, per_page, etc.)
	 * @return {Promise} Promise resolving to array of abilities
	 */
	const fetchAbilities = useCallback(
		async ( params = {} ) => {
			setIsLoading( true );
			setError( null );

			try {
				const response = await apiFetch( {
					path: `${ restNamespace }/custom-abilities${ buildQueryString( params ) }`,
					method: 'GET',
				} );

				return response;
			} catch ( err ) {
				setError( err.message || 'Failed to fetch abilities' );
				throw err;
			} finally {
				setIsLoading( false );
			}
		},
		[ restNamespace ]
	);

	/**
	 * Fetch single ability by ID
	 *
	 * @param {number} id Ability ID
	 * @return {Promise} Promise resolving to ability object
	 */
	const fetchAbility = useCallback(
		async ( id ) => {
			setIsLoading( true );
			setError( null );

			try {
				return await apiFetch( {
					path: `${ restNamespace }/custom-abilities/${ id }`,
					method: 'GET',
				} );
			} catch ( err ) {
				setError( err.message || 'Failed to fetch ability' );
				throw err;
			} finally {
				setIsLoading( false );
			}
		},
		[ restNamespace ]
	);

	/**
	 * Create new custom ability
	 *
	 * @param {Object} ability Ability data object
	 * @return {Promise} Promise resolving to created ability object
	 */
	const createAbility = useCallback(
		async ( ability ) => {
			setIsLoading( true );
			setError( null );

			try {
				return await apiFetch( {
					path: `${ restNamespace }/custom-abilities`,
					method: 'POST',
					data: ability,
				} );
			} catch ( err ) {
				setError( err.message || 'Failed to create ability' );
				throw err;
			} finally {
				setIsLoading( false );
			}
		},
		[ restNamespace ]
	);

	/**
	 * Update existing custom ability
	 *
	 * @param {number} id Ability ID
	 * @param {Object} ability Updated ability data object
	 * @return {Promise} Promise resolving to updated ability object
	 */
	const updateAbility = useCallback(
		async ( id, ability ) => {
			setIsLoading( true );
			setError( null );

			try {
				return await apiFetch( {
					path: `${ restNamespace }/custom-abilities/${ id }`,
					method: 'POST',
					data: ability,
				} );
			} catch ( err ) {
				setError( err.message || 'Failed to update ability' );
				throw err;
			} finally {
				setIsLoading( false );
			}
		},
		[ restNamespace ]
	);

	/**
	 * Delete custom ability
	 *
	 * @param {number} id Ability ID
	 * @return {Promise} Promise resolving to deletion result
	 */
	const deleteAbility = useCallback(
		async ( id ) => {
			setIsLoading( true );
			setError( null );

			try {
				return await apiFetch( {
					path: `${ restNamespace }/custom-abilities/${ id }`,
					method: 'DELETE',
				} );
			} catch ( err ) {
				setError( err.message || 'Failed to delete ability' );
				throw err;
			} finally {
				setIsLoading( false );
			}
		},
		[ restNamespace ]
	);

	/**
	 * Check if ability slug already exists (for real-time validation)
	 *
	 * @param {string} slug Ability slug to check
	 * @return {Promise<boolean>} Promise resolving to true if slug exists, false otherwise
	 */
	const checkSlugExists = useCallback(
		async ( slug ) => {
			try {
				// Try to fetch ability with this slug
				// If successful, slug exists. If 404, slug is available.
				const result = await apiFetch( {
					path: `${ restNamespace }/custom-abilities?search=${ encodeURIComponent(
						slug
					) }`,
					method: 'GET',
				} );

				// Check if any result has exact slug match
				return result.some( ( item ) => item.ability_slug === slug );
			} catch ( err ) {
				// Default to false if check fails
				return false;
			}
		},
		[ restNamespace ]
	);

	return {
		fetchAbilities,
		fetchAbility,
		createAbility,
		updateAbility,
		deleteAbility,
		checkSlugExists,
		error,
		isLoading,
		clearError: () => setError( null ),
	};
}

/**
 * Helper to build query string from parameters object
 *
 * @param {Object} params Parameters object
 * @return {string} Query string (empty string if no params)
 */
function buildQueryString( params ) {
	const pairs = Object.entries( params )
		.filter( ( [ , value ] ) => value !== undefined && value !== null )
		.map(
			( [ key, value ] ) =>
				`${ encodeURIComponent( key ) }=${ encodeURIComponent( value ) }`
		);

	return pairs.length > 0 ? `?${ pairs.join( '&' ) }` : '';
}
