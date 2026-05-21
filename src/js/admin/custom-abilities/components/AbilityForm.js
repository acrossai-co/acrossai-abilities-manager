/**
 * DataForm Component for Creating/Editing Custom Abilities
 *
 * Implements all 20 form fields with conditional visibility,
 * real-time validation, error handling, and load states.
 */

import { useEffect, useState, useCallback } from '@wordpress/element';
import { useCustomAbilities } from '../api/useCustomAbilities';
import {
	Button,
	Notice,
	Spinner,
	TextControl,
	TextareaControl,
	SelectControl,
	CheckboxControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * AbilityForm component for creating/editing custom abilities
 *
 * @param {Object} props Component props
 * @param {string} props.restNamespace REST namespace URL
 * @param {number} props.abilityId Ability ID (undefined for create mode)
 * @param {Function} props.onSuccess Callback on successful save
 * @param {Function} props.onCancel Callback on cancel
 * @return {JSX.Element} Form component
 */
export function AbilityForm( {
	restNamespace,
	abilityId,
	onSuccess,
	onCancel,
} ) {
	const isEditMode = !! abilityId;

	// Form state
	const [ formData, setFormData ] = useState( {
		ability_slug: '',
		label: '',
		description: '',
		enabled: true,
		callback_type: 'noop',
		callback_config: {},
		input_schema: '{}',
		output_schema: '{}',
		show_in_rest: true,
		show_in_mcp: false,
		mcp_type: 'tool',
		readonly: null,
		destructive: null,
		idempotent: null,
	} );

	// Validation and error state
	const [ errors, setErrors ] = useState( {} );
	const [ slugExists, setSlugExists ] = useState( false );
	const [ formError, setFormError ] = useState( '' );

	// API hooks
	const {
		fetchAbility,
		createAbility,
		updateAbility,
		isLoading,
		error: apiError,
	} = useCustomAbilities( restNamespace );

	const SLUG_PREFIX = 'acrossai-custom-abilities/';

	// Load ability data on edit mode
	useEffect( () => {
		if ( isEditMode ) {
			fetchAbility( abilityId )
				.then( ( ability ) => {
					const data = { ...ability };
					if ( data.ability_slug && data.ability_slug.startsWith( SLUG_PREFIX ) ) {
						data.ability_slug = data.ability_slug.slice( SLUG_PREFIX.length );
					}
					setFormData( data );
				} )
				.catch( ( err ) => {
					setFormError(
						err.message || __( 'Failed to load ability', 'acrossai-abilities-manager' )
					);
				} );
		}
	}, [ isEditMode, abilityId, fetchAbility ] );

	/**
	 * Handle field change
	 */
	const handleFieldChange = useCallback( ( field, value ) => {
		setFormData( ( prev ) => ( {
			...prev,
			[ field ]: value,
		} ) );

		// Clear field error on change
		if ( errors[ field ] ) {
			setErrors( ( prev ) => {
				const updated = { ...prev };
				delete updated[ field ];
				return updated;
			} );
		}
	}, [ errors ] );

	/**
	 * Validate slug uniqueness (debounced)
	 */
	useEffect( () => {
		const timer = setTimeout( async () => {
			if ( formData.ability_slug && ! isEditMode ) {
				// Only check on create mode, or if slug changed on edit
				// TODO: Implement uniqueness check via REST API
				setSlugExists( false );
			}
		}, 500 );

		return () => clearTimeout( timer );
	}, [ formData.ability_slug, isEditMode ] );

	/**
	 * Validate form before submission
	 */
	const validateForm = useCallback( () => {
		const newErrors = {};

		// Required fields
		if ( ! formData.ability_slug ) {
			newErrors.ability_slug = __( 'Ability slug is required', 'acrossai-abilities-manager' );
		} else if ( ! /^[a-z0-9][a-z0-9-]*$/.test( formData.ability_slug ) ) {
			newErrors.ability_slug = __(
				'Use lowercase letters, numbers, and hyphens only (e.g. my-ability)',
				'acrossai-abilities-manager'
			);
		} else if ( formData.ability_slug.length > 230 ) {
			newErrors.ability_slug = __( 'Slug suffix must be 230 characters or less', 'acrossai-abilities-manager' );
		} else if ( slugExists && ! isEditMode ) {
			newErrors.ability_slug = __( 'This slug already exists', 'acrossai-abilities-manager' );
		}

		if ( ! formData.label ) {
			newErrors.label = __( 'Label is required', 'acrossai-abilities-manager' );
		} else if ( formData.label.length > 255 ) {
			newErrors.label = __( 'Label must be 255 characters or less', 'acrossai-abilities-manager' );
		}

		// Validate JSON schema fields
		[ 'input_schema', 'output_schema' ].forEach( ( field ) => {
			if ( formData[ field ] ) {
				try {
					JSON.parse( formData[ field ] );
				} catch ( e ) {
					newErrors[ field ] = __( 'Invalid JSON syntax', 'acrossai-abilities-manager' );
				}
			}
		} );

		setErrors( newErrors );
		return Object.keys( newErrors ).length === 0;
	}, [ formData, slugExists, isEditMode ] );

	/**
	 * Handle form submission
	 */
	const handleSubmit = useCallback(
		async ( e ) => {
			e.preventDefault();

			if ( ! validateForm() ) {
				setFormError( __( 'Please fix the errors below', 'acrossai-abilities-manager' ) );
				return;
			}

			try {
				let result;
				if ( isEditMode ) {
					result = await updateAbility( abilityId, formData );
				} else {
					result = await createAbility( formData );
				}

				onSuccess?.( result );
			} catch ( err ) {
				setFormError( err.message || __( 'An error occurred', 'acrossai-abilities-manager' ) );
			}
		},
		[ formData, validateForm, isEditMode, abilityId, createAbility, updateAbility, onSuccess ]
	);


	if ( isEditMode && isLoading ) {
		return <Spinner />;
	}

	return (
		<form onSubmit={ handleSubmit } className="acrossai-ability-form">
			{ formError && (
				<Notice status="error" onRemove={ () => setFormError( '' ) }>
					{ formError }
				</Notice>
			) }

			{ apiError && (
				<Notice status="error" onRemove={ () => {} }>
					{ apiError }
				</Notice>
			) }

			<div className="acrossai-form-section">
				<h3>{ __( 'Basic Information', 'acrossai-abilities-manager' ) }</h3>

				<div className="acrossai-slug-field components-base-control">
					<label className="components-base-control__label">
						{ __( 'Ability Slug', 'acrossai-abilities-manager' ) }
					</label>
					<div className={ `acrossai-slug-input-wrap${ errors.ability_slug ? ' is-invalid' : '' }` }>
						<span className="acrossai-slug-prefix">acrossai-custom-abilities/</span>
						<input
							type="text"
							className="components-text-control__input"
							value={ formData.ability_slug }
							onChange={ ( e ) => handleFieldChange( 'ability_slug', e.target.value ) }
							placeholder="my-ability"
							disabled={ isEditMode }
						/>
					</div>
					<p className="components-base-control__help">
						{ __( 'Suffix only — lowercase letters, numbers, and hyphens', 'acrossai-abilities-manager' ) }
					</p>
					{ errors.ability_slug && (
						<span className="acrossai-error">{ errors.ability_slug }</span>
					) }
				</div>

				<TextControl
					label={ __( 'Label', 'acrossai-abilities-manager' ) }
					help={ __( 'Display name for the ability', 'acrossai-abilities-manager' ) }
					value={ formData.label }
					onChange={ ( val ) => handleFieldChange( 'label', val ) }
					placeholder="My Custom Ability"
					required
					isInvalid={ !! errors.label }
				/>
				{ errors.label && (
					<span className="acrossai-error">{ errors.label }</span>
				) }

				<TextareaControl
					label={ __( 'Description', 'acrossai-abilities-manager' ) }
					help={ __( 'Full description of what the ability does', 'acrossai-abilities-manager' ) }
					value={ formData.description }
					onChange={ ( val ) => handleFieldChange( 'description', val ) }
					rows={ 4 }
				/>


				<CheckboxControl
					label={ __( 'Enabled', 'acrossai-abilities-manager' ) }
					help={ __( 'Auto-register this ability at WordPress init', 'acrossai-abilities-manager' ) }
					checked={ formData.enabled }
					onChange={ ( val ) => handleFieldChange( 'enabled', val ) }
				/>
			</div>

			<div className="acrossai-form-section">
				<h3>{ __( 'Callback Configuration', 'acrossai-abilities-manager' ) }</h3>

				<SelectControl
					label={ __( 'Callback Type', 'acrossai-abilities-manager' ) }
					help={ __( 'How the ability is executed', 'acrossai-abilities-manager' ) }
					value={ formData.callback_type }
					onChange={ ( val ) => {
						handleFieldChange( 'callback_type', val );
						// Reset callback_config on type change
						handleFieldChange( 'callback_config', {} );
					} }
					options={ [
						{ value: 'noop', label: __( 'Noop (Documentation)', 'acrossai-abilities-manager' ) },
						{ value: 'filter_hook', label: __( 'WordPress Filter Hook', 'acrossai-abilities-manager' ) },
						{ value: 'wp_remote_post', label: __( 'HTTP POST', 'acrossai-abilities-manager' ) },
					] }
					required
				/>

				{ formData.callback_type === 'filter_hook' && (
					<TextControl
						label={ __( 'Hook Name', 'acrossai-abilities-manager' ) }
						help={ __( 'WordPress filter hook name (e.g., my_filter)', 'acrossai-abilities-manager' ) }
						value={ formData.callback_config?.hook_name || '' }
						onChange={ ( val ) =>
							handleFieldChange( 'callback_config', { ...formData.callback_config, hook_name: val } )
						}
						placeholder="my_filter"
						required
					/>
				) }

				{ formData.callback_type === 'wp_remote_post' && (
					<>
						<TextControl
							label={ __( 'URL', 'acrossai-abilities-manager' ) }
							help={ __( 'External endpoint URL', 'acrossai-abilities-manager' ) }
							value={ formData.callback_config?.url || '' }
							onChange={ ( val ) =>
								handleFieldChange( 'callback_config', { ...formData.callback_config, url: val } )
							}
							placeholder="https://example.com/webhook"
							type="url"
							required
						/>

						<SelectControl
							label={ __( 'HTTP Method', 'acrossai-abilities-manager' ) }
							value={ formData.callback_config?.method || 'POST' }
							onChange={ ( val ) =>
								handleFieldChange( 'callback_config', { ...formData.callback_config, method: val } )
							}
							options={ [
								{ value: 'POST', label: 'POST' },
								{ value: 'PUT', label: 'PUT' },
							] }
						/>

						<TextControl
							label={ __( 'Timeout (seconds)', 'acrossai-abilities-manager' ) }
							help={ __( 'Max execution time (1-300)', 'acrossai-abilities-manager' ) }
							value={ formData.callback_config?.timeout || '30' }
							onChange={ ( val ) =>
								handleFieldChange( 'callback_config', { ...formData.callback_config, timeout: parseInt( val ) || 30 } )
							}
							type="number"
							min="1"
							max="300"
						/>
					</>
				) }
			</div>


			<div className="acrossai-form-section">
				<h3>{ __( 'Input/Output Schemas', 'acrossai-abilities-manager' ) }</h3>

				<TextareaControl
					label={ __( 'Input Schema (JSON)', 'acrossai-abilities-manager' ) }
					help={ __( 'JSON Schema Draft 7 for input validation', 'acrossai-abilities-manager' ) }
					value={ formData.input_schema }
					onChange={ ( val ) => handleFieldChange( 'input_schema', val ) }
					rows={ 6 }
					isInvalid={ !! errors.input_schema }
				/>
				{ errors.input_schema && (
					<span className="acrossai-error">{ errors.input_schema }</span>
				) }

				<TextareaControl
					label={ __( 'Output Schema (JSON)', 'acrossai-abilities-manager' ) }
					help={ __( 'JSON Schema Draft 7 for output documentation', 'acrossai-abilities-manager' ) }
					value={ formData.output_schema }
					onChange={ ( val ) => handleFieldChange( 'output_schema', val ) }
					rows={ 6 }
					isInvalid={ !! errors.output_schema }
				/>
				{ errors.output_schema && (
					<span className="acrossai-error">{ errors.output_schema }</span>
				) }
			</div>

			<div className="acrossai-form-section">
				<h3>{ __( 'REST & MCP Exposure', 'acrossai-abilities-manager' ) }</h3>

				<CheckboxControl
					label={ __( 'Show in REST API', 'acrossai-abilities-manager' ) }
					help={ __( 'Expose this ability via REST endpoints', 'acrossai-abilities-manager' ) }
					checked={ formData.show_in_rest }
					onChange={ ( val ) => handleFieldChange( 'show_in_rest', val ) }
				/>

				<CheckboxControl
					label={ __( 'Show in MCP', 'acrossai-abilities-manager' ) }
					help={ __( 'Expose this ability to MCP servers', 'acrossai-abilities-manager' ) }
					checked={ formData.show_in_mcp }
					onChange={ ( val ) => handleFieldChange( 'show_in_mcp', val ) }
				/>

				{ formData.show_in_mcp && (
					<>
						<SelectControl
							label={ __( 'MCP Type', 'acrossai-abilities-manager' ) }
							help={ __( 'How this ability is exposed in MCP', 'acrossai-abilities-manager' ) }
							value={ formData.mcp_type }
							onChange={ ( val ) => handleFieldChange( 'mcp_type', val ) }
							options={ [
								{ value: 'tool', label: __( 'Tool (Executable)', 'acrossai-abilities-manager' ) },
								{ value: 'resource', label: __( 'Resource (Data)', 'acrossai-abilities-manager' ) },
								{ value: 'prompt', label: __( 'Prompt (LLM Context)', 'acrossai-abilities-manager' ) },
							] }
							required
						/>

					</>
				) }
			</div>

			<div className="acrossai-form-section">
				<h3>{ __( 'Metadata Flags', 'acrossai-abilities-manager' ) }</h3>

				<SelectControl
					label={ __( 'Readonly (Metadata)', 'acrossai-abilities-manager' ) }
					help={ __( 'Metadata annotation; does not prevent mutations', 'acrossai-abilities-manager' ) }
					value={ formData.readonly === null ? 'null' : String( formData.readonly ) }
					onChange={ ( val ) => {
						const parsedVal = val === 'null' ? null : parseInt( val );
						handleFieldChange( 'readonly', parsedVal );
					} }
					options={ [
						{ value: 'null', label: __( 'Inherit (default)', 'acrossai-abilities-manager' ) },
						{ value: '0', label: __( 'False', 'acrossai-abilities-manager' ) },
						{ value: '1', label: __( 'True', 'acrossai-abilities-manager' ) },
					] }
				/>

				<SelectControl
					label={ __( 'Destructive (Metadata)', 'acrossai-abilities-manager' ) }
					help={ __( 'Metadata annotation for destructive operations', 'acrossai-abilities-manager' ) }
					value={ formData.destructive === null ? 'null' : String( formData.destructive ) }
					onChange={ ( val ) => {
						const parsedVal = val === 'null' ? null : parseInt( val );
						handleFieldChange( 'destructive', parsedVal );
					} }
					options={ [
						{ value: 'null', label: __( 'Inherit (default)', 'acrossai-abilities-manager' ) },
						{ value: '0', label: __( 'False', 'acrossai-abilities-manager' ) },
						{ value: '1', label: __( 'True', 'acrossai-abilities-manager' ) },
					] }
				/>

				<SelectControl
					label={ __( 'Idempotent (Metadata)', 'acrossai-abilities-manager' ) }
					help={ __( 'Metadata annotation for idempotent operations', 'acrossai-abilities-manager' ) }
					value={ formData.idempotent === null ? 'null' : String( formData.idempotent ) }
					onChange={ ( val ) => {
						const parsedVal = val === 'null' ? null : parseInt( val );
						handleFieldChange( 'idempotent', parsedVal );
					} }
					options={ [
						{ value: 'null', label: __( 'Inherit (default)', 'acrossai-abilities-manager' ) },
						{ value: '0', label: __( 'False', 'acrossai-abilities-manager' ) },
						{ value: '1', label: __( 'True', 'acrossai-abilities-manager' ) },
					] }
				/>
			</div>

			<div className="acrossai-form-actions">
				<Button
					type="submit"
					variant="primary"
					isBusy={ isLoading }
					disabled={ isLoading }
				>
					{ isEditMode
						? __( 'Update Ability', 'acrossai-abilities-manager' )
						: __( 'Create Ability', 'acrossai-abilities-manager' ) }
				</Button>

				{ onCancel && (
					<Button
						type="button"
						onClick={ onCancel }
						variant="secondary"
						disabled={ isLoading }
					>
						{ __( 'Cancel', 'acrossai-abilities-manager' ) }
					</Button>
				) }
			</div>
		</form>
	);
}

export default AbilityForm;
