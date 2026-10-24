/**
 * Form controls with the current WordPress sizing defaults, and layout helpers.
 */
import {
	CheckboxControl,
	SelectControl,
	TextControl,
	TextareaControl,
	ToggleControl,
} from '@wordpress/components';

export function Section( { title, description, children } ) {
	return (
		<section className="shieldcraft-admin__section">
			{ title && <h2>{ title }</h2> }
			{ description && <p className="description">{ description }</p> }
			<div className="shieldcraft-admin__stack">{ children }</div>
		</section>
	);
}

export function NumberInput( { value, onChange, ...props } ) {
	return (
		<TextControl
			__nextHasNoMarginBottom
			__next40pxDefaultSize
			type="number"
			{ ...props }
			value={ String( value ?? '' ) }
			onChange={ ( next ) => onChange( parseInt( next, 10 ) || 0 ) }
		/>
	);
}

export function Select( props ) {
	return (
		<SelectControl
			__nextHasNoMarginBottom
			__next40pxDefaultSize
			{ ...props }
		/>
	);
}

export function Toggle( { checked, ...props } ) {
	return (
		<ToggleControl
			__nextHasNoMarginBottom
			{ ...props }
			checked={ !! checked }
		/>
	);
}

export function Lines( props ) {
	return (
		<TextareaControl
			__nextHasNoMarginBottom
			rows={ 4 }
			spellCheck={ false }
			{ ...props }
			value={ props.value ?? '' }
		/>
	);
}

/**
 * Checkboxes for a list setting.
 *
 * @param {Object}   props          Props.
 * @param {string}   props.label    Group label.
 * @param {Array}    props.options  [ { value, label } ].
 * @param {string[]} props.value    Chosen values.
 * @param {Function} props.onChange Receives the new list.
 */
export function Checklist( { label, options, value, onChange } ) {
	return (
		<fieldset className="shieldcraft-admin__checklist">
			<legend>{ label }</legend>
			{ options.map( ( option ) => (
				<CheckboxControl
					__nextHasNoMarginBottom
					key={ option.value }
					label={ option.label }
					checked={ value.includes( option.value ) }
					onChange={ ( on ) =>
						onChange(
							on
								? [ ...value, option.value ]
								: value.filter( ( v ) => v !== option.value )
						)
					}
				/>
			) ) }
		</fieldset>
	);
}
