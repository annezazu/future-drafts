import { useState } from '@wordpress/element';
import {
	Button,
	TextControl,
	TextareaControl,
	DatePicker,
	Notice,
	Flex,
	FlexItem,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
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
			<TextControl
				label={ __( 'Title', 'future-drafts' ) }
				value={ title }
				onChange={ setTitle }
				__next40pxDefaultSize
				__nextHasNoMarginBottom
			/>
			<TextareaControl
				label={ __( "What's coming up for you?", 'future-drafts' ) }
				placeholder={ __( 'A few notes for your future self…', 'future-drafts' ) }
				value={ content }
				onChange={ setContent }
				rows={ 3 }
				__nextHasNoMarginBottom
			/>
			<div className="future-drafts-capture__date">
				<div className="future-drafts-capture__date-label">
					{ __( 'Remind me on', 'future-drafts' ) }
				</div>
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
				</Flex>
				<DatePicker
					currentDate={ date || undefined }
					onChange={ ( value ) => setDate( value ? value.slice( 0, 10 ) : null ) }
				/>
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
