import { useState } from '@wordpress/element';
import {
	Button,
	DatePicker,
	Dropdown,
	Notice,
	Flex,
	FlexItem,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { dateI18n } from '@wordpress/date';
import { createEntry, today, addDays, addMonths } from './api';

const PRESETS = [
	{ key: '1w', label: __( '1 week', 'future-drafts' ), apply: ( t ) => addDays( t, 7 ) },
	{ key: '1m', label: __( '1 month', 'future-drafts' ), apply: ( t ) => addMonths( t, 1 ) },
	{ key: '3m', label: __( '3 months', 'future-drafts' ), apply: ( t ) => addMonths( t, 3 ) },
];

export default function CaptureForm( { onCreated } ) {
	const [ title, setTitle ] = useState( '' );
	const [ content, setContent ] = useState( '' );
	const [ date, setDate ] = useState( null );
	const [ submitting, setSubmitting ] = useState( false );
	const [ error, setError ] = useState( null );

	const canSubmit = ( title.trim() !== '' || content.trim() !== '' ) && date && ! submitting;

	const submit = async () => {
		if ( ! canSubmit ) {
			return;
		}
		setSubmitting( true );
		setError( null );
		try {
			const entry = await createEntry( { title, content, remind_on: date } );
			setTitle( '' );
			setContent( '' );
			setDate( null );
			onCreated && onCreated( entry );
		} catch ( e ) {
			setError( e.message || __( 'Could not save.', 'future-drafts' ) );
		} finally {
			setSubmitting( false );
		}
	};

	const applyPreset = ( preset ) => {
		setDate( preset.apply( today() ) );
	};

	return (
		<div className="future-drafts-capture">
			<div className="input-text-wrap">
				<label htmlFor="future-drafts-title">
					{ __( 'Title of Post', 'future-drafts' ) }
				</label>
				<input
					type="text"
					id="future-drafts-title"
					name="future-drafts-title"
					value={ title }
					onChange={ ( e ) => setTitle( e.target.value ) }
					autoComplete="off"
				/>
			</div>

			<div className="textarea-wrap">
				<label htmlFor="future-drafts-content">
					{ __( 'Get a heads start', 'future-drafts' ) }
				</label>
				<textarea
					id="future-drafts-content"
					name="future-drafts-content"
					placeholder={ __( 'A few notes for your future self…', 'future-drafts' ) }
					rows={ 3 }
					value={ content }
					onChange={ ( e ) => setContent( e.target.value ) }
					autoComplete="off"
				/>
			</div>

			<div className="future-drafts-capture__date">
				<label htmlFor="future-drafts-date-toggle">
					{ __( 'Remind me to pick this back up', 'future-drafts' ) }
				</label>
				<Flex gap={ 2 } justify="flex-start" wrap>
					{ PRESETS.map( ( p ) => (
						<FlexItem key={ p.key }>
							<Button
								variant={ date === p.apply( today() ) ? 'primary' : 'secondary' }
								size="small"
								onClick={ () => applyPreset( p ) }
							>
								{ p.label }
							</Button>
						</FlexItem>
					) ) }
					<FlexItem>
						<Dropdown
							popoverProps={ {
								placement: 'right-start',
								// Lock the top edge to the trigger so the
								// calendar grows downward as months change
								// height; without this Floating UI flips
								// the placement when the popover would
								// overflow the viewport, which moves the
								// prev/next chevrons.
								flip: false,
								resize: false,
							} }
							renderToggle={ ( { isOpen, onToggle } ) => (
								<Button
									id="future-drafts-date-toggle"
									variant="link"
									className="future-drafts-capture__date-link"
									onClick={ onToggle }
									aria-expanded={ isOpen }
								>
									{ date
										? dateI18n( 'M j, Y', `${ date }T00:00:00` )
										: __( 'Pick a date', 'future-drafts' ) }
								</Button>
							) }
							renderContent={ ( { onClose } ) => (
								<div className="future-drafts-capture__datepicker">
									<DatePicker
										currentDate={ date || undefined }
										onChange={ ( value ) => {
											setDate( value ? value.slice( 0, 10 ) : null );
											onClose();
										} }
									/>
								</div>
							) }
						/>
					</FlexItem>
				</Flex>
			</div>

			{ error && <Notice status="error" isDismissible={ false }>{ error }</Notice> }
			<div className="future-drafts-capture__actions">
				<Button
					variant="primary"
					onClick={ submit }
					disabled={ ! canSubmit }
					isBusy={ submitting }
				>
					{ __( 'Save for later', 'future-drafts' ) }
				</Button>
			</div>
		</div>
	);
}
