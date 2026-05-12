/**
 * Slide-in edit panel for a single ability override.
 *
 * Uses createPortal from @wordpress/element to render outside the main app tree.
 *
 * @since 0.1.0
 */
import { useState, useEffect } from '@wordpress/element';
import { createPortal } from '@wordpress/element';
import { useDispatch } from '@wordpress/data';
import { Button, SelectControl, ToggleControl, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { STORE_NAME } from '../store/index';
import McpVisibilityControl from './McpVisibilityControl';

/**
 * Tri-state SelectControl helper.
 *
 * @param {Object}   props
 * @param {string}   props.label     Control label.
 * @param {*}        props.value     null | true | false.
 * @param {Function} props.onChange  Callback.
 * @return {JSX.Element}
 */
function TriStateControl( { label, value, onChange } ) {
	return (
		<SelectControl
			label={ label }
			value={ null === value ? '' : String( value ) }
			options={ [
				{ value: '',      label: __( '— (Inherit)', 'acrossai-abilities-manager' ) },
				{ value: 'true',  label: __( 'Yes', 'acrossai-abilities-manager' ) },
				{ value: 'false', label: __( 'No', 'acrossai-abilities-manager' ) },
			] }
			onChange={ ( v ) => {
				let parsed = null;
				if ( 'true' === v ) parsed = true;
				else if ( 'false' === v ) parsed = false;
				onChange( parsed );
			} }
		/>
	);
}

/**
 * AbilityEditPanel — slide-in drawer component.
 *
 * @param {Object}   props
 * @param {string}   props.slug     Ability slug.
 * @param {Object}   props.ability  Merged ability data (may be null).
 * @param {Function} props.onClose  Close handler.
 * @return {JSX.Element|null}
 */
export default function AbilityEditPanel( { slug, ability, onClose } ) {
	const dispatch = useDispatch( STORE_NAME );

	const [ isSaving, setIsSaving ] = useState( false );
	const [ saveError, setSaveError ] = useState( null );

	// Local form state — seed from ability on mount.
	const [ formData, setFormData ] = useState( () => ( {
		site_allowed:  ability?.site_allowed ?? null,
		readonly:      ability?.readonly ?? null,
		destructive:   ability?.destructive ?? null,
		idempotent:    ability?.idempotent ?? null,
		show_in_rest:  ability?.show_in_rest ?? null,
		show_in_mcp:   ability?.show_in_mcp ?? null,
		mcp_type:      ability?.mcp_type ?? null,
		mcp_servers:   ability?.mcp_servers ?? null,
	} ) );

	// Re-seed when ability prop changes (e.g., after toggle).
	useEffect( () => {
		if ( ability ) {
			setFormData( {
				site_allowed:  ability.site_allowed ?? null,
				readonly:      ability.readonly ?? null,
				destructive:   ability.destructive ?? null,
				idempotent:    ability.idempotent ?? null,
				show_in_rest:  ability.show_in_rest ?? null,
				show_in_mcp:   ability.show_in_mcp ?? null,
				mcp_type:      ability.mcp_type ?? null,
				mcp_servers:   ability.mcp_servers ?? null,
			} );
		}
	}, [ ability ] );

	function setField( key, value ) {
		setFormData( ( prev ) => ( { ...prev, [ key ]: value } ) );
	}

	async function handleSave() {
		setIsSaving( true );
		setSaveError( null );
		try {
			const result = await dispatch.saveOverride( slug, formData );
			if ( ! result?.unchanged ) {
				onClose();
			}
		} catch ( err ) {
			setSaveError( err.message || String( err ) );
		} finally {
			setIsSaving( false );
		}
	}

	const panel = (
		<div
			className="acrossai-ability-edit-panel"
			role="dialog"
			aria-modal="true"
			aria-label={ __( 'Edit Ability Override', 'acrossai-abilities-manager' ) }
		>
			<div className="acrossai-ability-edit-panel__header">
				<h2>{ __( 'Edit Ability Override', 'acrossai-abilities-manager' ) }</h2>
				<Button
					icon="no-alt"
					label={ __( 'Close', 'acrossai-abilities-manager' ) }
					onClick={ onClose }
				/>
			</div>

			<p><strong>{ slug }</strong></p>

			{ saveError && (
				<p className="components-notice is-error">{ saveError }</p>
			) }

			<TriStateControl
				label={ __( 'Site Allowed', 'acrossai-abilities-manager' ) }
				value={ formData.site_allowed }
				onChange={ ( v ) => setField( 'site_allowed', v ) }
			/>

			<TriStateControl
				label={ __( 'Read Only', 'acrossai-abilities-manager' ) }
				value={ formData.readonly }
				onChange={ ( v ) => setField( 'readonly', v ) }
			/>

			<TriStateControl
				label={ __( 'Destructive', 'acrossai-abilities-manager' ) }
				value={ formData.destructive }
				onChange={ ( v ) => setField( 'destructive', v ) }
			/>

			<TriStateControl
				label={ __( 'Idempotent', 'acrossai-abilities-manager' ) }
				value={ formData.idempotent }
				onChange={ ( v ) => setField( 'idempotent', v ) }
			/>

			<TriStateControl
				label={ __( 'Show in REST', 'acrossai-abilities-manager' ) }
				value={ formData.show_in_rest }
				onChange={ ( v ) => setField( 'show_in_rest', v ) }
			/>

			<McpVisibilityControl
				showInMcp={ formData.show_in_mcp }
				mcpType={ formData.mcp_type }
				mcpServers={ formData.mcp_servers }
				onChange={ ( partial ) => setFormData( ( prev ) => ( { ...prev, ...partial } ) ) }
			/>

			<div className="acrossai-ability-edit-panel__footer">
				<Button
					variant="primary"
					onClick={ handleSave }
					disabled={ isSaving }
					isBusy={ isSaving }
				>
					{ isSaving
						? __( 'Saving…', 'acrossai-abilities-manager' )
						: __( 'Save Override', 'acrossai-abilities-manager' ) }
				</Button>
				<Button variant="secondary" onClick={ onClose } disabled={ isSaving }>
					{ __( 'Cancel', 'acrossai-abilities-manager' ) }
				</Button>
			</div>
		</div>
	);

	return createPortal( panel, document.body );
}
