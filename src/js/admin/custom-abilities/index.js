/**
 * Main entry point for Custom Abilities admin UI
 *
 * Renders both AbilityForm and AbilitiesList components in the admin page.
 */

import { render } from '@wordpress/element';
import { AbilityForm } from './components/AbilityForm';
import { AbilitiesList } from './components/AbilitiesList';
import '../../../scss/admin/custom-abilities/index.scss';

// Get REST namespace from window object (set via wp_localize_script)
const restNamespace = window.acrossaiAbilitiesManager?.restNamespace || '/wp-json/acrossai-abilities-manager/v1';

// Container elements
const formContainer = document.getElementById( 'acrossai-ability-form-container' );
const listContainer = document.getElementById( 'acrossai-abilities-list-container' );

/**
 * Application state and handlers
 */
let appState = {
	mode: 'list', // 'list' or 'form'
	editingId: null,
};

/**
 * Handle edit ability - switch to form mode
 */
const handleEditAbility = ( abilityId ) => {
	appState = { mode: 'form', editingId: abilityId };
	renderApp();
};

/**
 * Handle create ability - switch to form mode
 */
const handleCreateAbility = () => {
	appState = { mode: 'form', editingId: null };
	renderApp();
};

/**
 * Handle success - switch back to list mode
 */
const handleFormSuccess = ( ability ) => {
	appState = { mode: 'list', editingId: null };
	renderApp();

	// Show success message
	if ( window.wp?.data?.dispatch ) {
		window.wp.data.dispatch( 'core/notices' ).createNotice(
			'success',
			appState.editingId
				? `Ability "${ ability.label }" updated successfully.`
				: `Ability "${ ability.label }" created successfully.`,
			{ type: 'snackbar' }
		);
	}
};

/**
 * Handle cancel - switch back to list mode
 */
const handleFormCancel = () => {
	appState = { mode: 'list', editingId: null };
	renderApp();
};

/**
 * Render application
 */
function renderApp() {
	// Render form
	if ( formContainer ) {
		if ( appState.mode === 'form' ) {
			render(
				<AbilityForm
					restNamespace={ restNamespace }
					abilityId={ appState.editingId }
					onSuccess={ handleFormSuccess }
					onCancel={ handleFormCancel }
				/>,
				formContainer
			);
		} else {
			render( null, formContainer );
		}
	}

	// Render list
	if ( listContainer ) {
		if ( appState.mode === 'list' ) {
			render(
				<AbilitiesList
					restNamespace={ restNamespace }
					onEditAbility={ handleEditAbility }
					onCreateAbility={ handleCreateAbility }
				/>,
				listContainer
			);
		} else {
			render( null, listContainer );
		}
	}
}

// Initial render
document.addEventListener( 'DOMContentLoaded', () => {
	renderApp();
} );
