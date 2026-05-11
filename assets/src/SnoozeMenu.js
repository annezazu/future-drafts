import { useState } from '@wordpress/element';
import {
	Dropdown,
	Button,
	MenuGroup,
	MenuItem,
	DatePicker,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { chevronDown } from '@wordpress/icons';
import { today, addDays, addMonths } from './api';

const PRESETS = [
	{ key: '1w', label: __( '+1 week', 'future-drafts' ), apply: ( t ) => addDays( t, 7 ) },
	{ key: '1m', label: __( '+1 month', 'future-drafts' ), apply: ( t ) => addMonths( t, 1 ) },
	{ key: '3m', label: __( '+3 months', 'future-drafts' ), apply: ( t ) => addMonths( t, 3 ) },
];

export default function SnoozeMenu( { onSnooze } ) {
	const [ pickingCustom, setPickingCustom ] = useState( false );

	return (
		<Dropdown
			popoverProps={ { placement: 'bottom-end' } }
			onClose={ () => setPickingCustom( false ) }
			renderToggle={ ( { isOpen, onToggle } ) => (
				<Button
					variant="tertiary"
					icon={ chevronDown }
					iconPosition="right"
					onClick={ onToggle }
					aria-expanded={ isOpen }
				>
					{ __( 'Snooze', 'future-drafts' ) }
				</Button>
			) }
			renderContent={ ( { onClose } ) =>
				pickingCustom ? (
					<div className="future-drafts-snooze__custom">
						<DatePicker
							onChange={ ( value ) => {
								if ( value ) {
									onSnooze( value.slice( 0, 10 ) );
									onClose();
								}
							} }
						/>
					</div>
				) : (
					<MenuGroup>
						{ PRESETS.map( ( p ) => (
							<MenuItem
								key={ p.key }
								onClick={ () => {
									onSnooze( p.apply( today() ) );
									onClose();
								} }
							>
								{ p.label }
							</MenuItem>
						) ) }
						<MenuItem onClick={ () => setPickingCustom( true ) }>
							{ __( 'Custom date…', 'future-drafts' ) }
						</MenuItem>
					</MenuGroup>
				)
			}
		/>
	);
}
