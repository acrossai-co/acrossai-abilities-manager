/**
 * MCP Visibility Control — tri-state dropdown + optional server list.
 *
 * @since 0.1.0
 */
import { SelectControl, CheckboxControl } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { STORE_NAME } from '../store/index';

const MCP_TYPE_OPTIONS = [
	{ value: '',     label: __( '— (Inherit)', 'acrossai-abilities-manager' ) },
	{ value: 'tool', label: __( 'Tool', 'acrossai-abilities-manager' ) },
	{ value: 'resource', label: __( 'Resource', 'acrossai-abilities-manager' ) },
	{ value: 'prompt', label: __( 'Prompt', 'acrossai-abilities-manager' ) },
];

/**
 * McpVisibilityControl component.
 *
 * @param {Object}   props
 * @param {boolean|null} props.showInMcp   Tri-state value.
 * @param {string|null}  props.mcpType     MCP type value.
 * @param {Array|null}   props.mcpServers  Currently selected server IDs.
 * @param {Function}     props.onChange    Called with { show_in_mcp, mcp_type, mcp_servers }.
 * @return {JSX.Element}
 */
export default function McpVisibilityControl( { showInMcp, mcpType, mcpServers, onChange } ) {
	const availableServers = useSelect( ( select ) => select( STORE_NAME ).getMcpServers(), [] );

	const isEnabled = showInMcp !== false;

	return (
		<div className="acrossai-mcp-visibility">
			<SelectControl
				label={ __( 'Show in MCP', 'acrossai-abilities-manager' ) }
				value={ null === showInMcp ? '' : String( showInMcp ) }
				options={ [
					{ value: '', label: __( '— (Inherit)', 'acrossai-abilities-manager' ) },
					{ value: 'true', label: __( 'Yes', 'acrossai-abilities-manager' ) },
					{ value: 'false', label: __( 'No', 'acrossai-abilities-manager' ) },
				] }
				onChange={ ( value ) => {
					let parsed = null;
					if ( 'true' === value ) parsed = true;
					else if ( 'false' === value ) parsed = false;
					onChange( { show_in_mcp: parsed } );
				} }
			/>

			{ isEnabled && (
				<>
					<SelectControl
						label={ __( 'MCP Type', 'acrossai-abilities-manager' ) }
						value={ mcpType || '' }
						options={ MCP_TYPE_OPTIONS }
						onChange={ ( value ) => onChange( { mcp_type: value || null } ) }
					/>

					{ availableServers.length > 0 && (
						<fieldset>
							<legend>{ __( 'MCP Servers', 'acrossai-abilities-manager' ) }</legend>
							{ availableServers.map( ( server ) => {
								const selectedIds = Array.isArray( mcpServers ) ? mcpServers : [];
								return (
									<CheckboxControl
										key={ server.id }
										label={ server.name || server.id }
										checked={ selectedIds.includes( server.id ) }
										onChange={ ( checked ) => {
											const next = checked
												? [ ...selectedIds, server.id ]
												: selectedIds.filter( ( id ) => id !== server.id );
											onChange( { mcp_servers: next.length ? next : null } );
										} }
									/>
								);
							} ) }
						</fieldset>
					) }
				</>
			) }
		</div>
	);
}
