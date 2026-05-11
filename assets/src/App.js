import { useState, useEffect, useCallback } from '@wordpress/element';
import { Spinner, Notice, Button } from '@wordpress/components';
import { __, sprintf, _n } from '@wordpress/i18n';
import { chevronDown, chevronRight } from '@wordpress/icons';
import CaptureForm from './CaptureForm';
import EntryRow from './EntryRow';
import { listEntries, snoozeEntry, deleteEntry } from './api';

const SUBTITLE = ( window.futureDrafts && window.futureDrafts.subtitle ) || '';

export default function App() {
	const [ data, setData ] = useState( null );
	const [ error, setError ] = useState( null );
	const [ pendingExpanded, setPendingExpanded ] = useState( false );

	const reload = useCallback( async () => {
		try {
			const next = await listEntries();
			setData( next );
			setError( null );
		} catch ( e ) {
			setError( e.message || __( 'Could not load entries.', 'future-drafts' ) );
		}
	}, [] );

	useEffect( () => {
		reload();
	}, [ reload ] );

	const onCreated = () => reload();

	const onSnooze = async ( entry, date ) => {
		try {
			await snoozeEntry( entry.id, date );
			reload();
		} catch ( e ) {
			setError( e.message );
		}
	};

	const onDelete = async ( entry ) => {
		try {
			await deleteEntry( entry.id );
			reload();
		} catch ( e ) {
			setError( e.message );
		}
	};

	const due = data?.due || [];
	const pending = data?.pending || [];
	const showPendingExpanded = pendingExpanded || pending.length <= 2;

	return (
		<div className="future-drafts">
			{ SUBTITLE && <div className="future-drafts__subtitle">{ SUBTITLE }</div> }

			{ due.length > 0 && (
				<section className="future-drafts__section future-drafts__section--due">
					<h3 className="future-drafts__heading">{ __( 'Pick this draft back up', 'future-drafts' ) }</h3>
					{ due.map( ( entry ) => (
						<EntryRow
							key={ entry.id }
							entry={ entry }
							variant="due"
							onSnooze={ onSnooze }
							onDelete={ onDelete }
						/>
					) ) }
				</section>
			) }

			<CaptureForm onCreated={ onCreated } />

			{ error && <Notice status="error" onRemove={ () => setError( null ) }>{ error }</Notice> }

			{ data === null && <Spinner /> }

			{ pending.length > 0 && (
				<section className="future-drafts__section future-drafts__section--pending">
					<Button
						variant="tertiary"
						size="small"
						icon={ showPendingExpanded ? chevronDown : chevronRight }
						iconPosition="left"
						onClick={ () => setPendingExpanded( ( v ) => ! v ) }
						aria-expanded={ showPendingExpanded }
					>
						{ sprintf(
							/* translators: %d: number of pending drafts */
							_n( '%d draft waiting', '%d drafts waiting', pending.length, 'future-drafts' ),
							pending.length
						) }
					</Button>
					{ showPendingExpanded &&
						pending.map( ( entry ) => (
							<EntryRow
								key={ entry.id }
								entry={ entry }
								variant="pending"
								onSnooze={ onSnooze }
								onDelete={ onDelete }
							/>
						) ) }
				</section>
			) }
		</div>
	);
}
