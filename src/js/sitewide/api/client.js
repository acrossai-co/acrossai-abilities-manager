/**
 * REST API client for the Sitewide Ability Manager.
 *
 * All requests use @wordpress/api-fetch with the WP REST nonce set at app init.
 *
 * @since 0.1.0
 */
import apiFetch from '@wordpress/api-fetch';

/**
 * Build the base REST URL from the global config.
 *
 * @return {string} Base URL ending with a slash.
 */
function getBaseUrl() {
	const config = window.acrossaiAbilitiesSitewide || {};
	const restUrl = config.rest_url || '/wp-json/';
	// Ensure trailing slash.
	return restUrl.replace( /\/?$/, '/' );
}

/**
 * Fetch the paginated, filtered abilities list.
 *
 * @param {Object} params Query params (page, per_page, search, orderby, order, source, has_override).
 * @return {Promise<{abilities: Array, total: number, pages: number}>}
 */
export async function fetchAbilities( params = {} ) {
	const qs = new URLSearchParams();
	Object.entries( params ).forEach( ( [ key, value ] ) => {
		if ( null !== value && undefined !== value && '' !== value ) {
			qs.set( key, String( value ) );
		}
	} );

	const queryString = qs.toString();
	const path = `acrossai-abilities-manager/v1/sitewide/abilities${ queryString ? '?' + queryString : '' }`;

	const response = await apiFetch( { path, parse: false } );
	const data     = await response.json();

	return {
		abilities: Array.isArray( data ) ? data : [],
		total:     parseInt( response.headers.get( 'X-WP-Total' ) || '0', 10 ),
		pages:     parseInt( response.headers.get( 'X-WP-TotalPages' ) || '0', 10 ),
	};
}

/**
 * Fetch a single ability's effective data.
 *
 * @param {string} slug Ability slug.
 * @return {Promise<Object>}
 */
export async function fetchAbility( slug ) {
	return apiFetch( {
		path: `acrossai-abilities-manager/v1/sitewide/abilities/${ encodeURIComponent( slug ) }`,
	} );
}

/**
 * Save an override for a specific ability.
 *
 * @param {string} slug Ability slug.
 * @param {Object} data Fields to save.
 * @return {Promise<Object>}
 */
export async function saveOverride( slug, data ) {
	return apiFetch( {
		path:   `acrossai-abilities-manager/v1/sitewide/abilities/${ encodeURIComponent( slug ) }`,
		method: 'POST',
		data,
	} );
}

/**
 * Delete the override for a specific ability.
 *
 * @param {string} slug Ability slug.
 * @return {Promise<Object>}
 */
export async function deleteOverride( slug ) {
	return apiFetch( {
		path:   `acrossai-abilities-manager/v1/sitewide/abilities/${ encodeURIComponent( slug ) }`,
		method: 'DELETE',
	} );
}

/**
 * Toggle the site_allowed flag for a specific ability.
 *
 * @param {string}  slug        Ability slug.
 * @param {boolean} siteAllowed New value.
 * @return {Promise<Object>}
 */
export async function toggleAbility( slug, siteAllowed ) {
	return apiFetch( {
		path:   `acrossai-abilities-manager/v1/sitewide/abilities/${ encodeURIComponent( slug ) }/toggle`,
		method: 'POST',
		data:   { site_allowed: siteAllowed },
	} );
}

/**
 * Apply a bulk action to multiple abilities.
 *
 * @param {string[]} slugs  Ability slugs.
 * @param {string}   action 'allow' | 'disallow' | 'reset'.
 * @return {Promise<Object>}
 */
export async function bulkAction( slugs, action ) {
	return apiFetch( {
		path:   'acrossai-abilities-manager/v1/sitewide/abilities/bulk',
		method: 'POST',
		data:   { slugs, action },
	} );
}

/**
 * Fetch the list of available MCP servers.
 *
 * @return {Promise<Array>}
 */
export async function fetchMcpServers() {
	return apiFetch( {
		path: 'acrossai-abilities-manager/v1/sitewide/mcp-servers',
	} );
}
