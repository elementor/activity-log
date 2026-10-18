import { SelectControl } from '@wordpress/components';

export default function SettingRow( { id, schema, value, onChange } ) {
	if ( ! schema ) {
		return null;
	}

	const isSelect = !! schema.options;

	return (
		<tr>
			<th scope="row">
				<label htmlFor={ id }>
					{ schema.label }
				</label>
			</th>
			<td>
				{ isSelect ? (
					<SelectControl
						id={ id }
						value={ value || schema.options[ 0 ].value }
						options={ schema.options }
						onChange={ onChange }
						__nextHasNoMarginBottom
					/>
				) : (
					<>
						<input
							id={ id }
							type="number"
							min="1"
							step="1"
							className="small-text"
							value={ value || '' }
							onChange={ ( e ) => onChange( e.target.value ) }
						/>
						{ ' ' }
						{ schema.suffix && <span>{ schema.suffix }</span> }
					</>
				) }
				{ schema.description && (
					<p className="description">{ schema.description }</p>
				) }
			</td>
		</tr>
	);
}
