import { useState, useEffect, useCallback } from '@wordpress/element';
import { Spinner, Notice } from '@wordpress/components';
import { __, sprintf, _n } from '@wordpress/i18n';
import { chevronUp, chevronDown } from '@wordpress/icons';
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
	const isEmpty = data !== null && due.length === 0 && pending.length === 0;
	const pendingCount = sprintf(
		/* translators: %d: number of pending drafts */
		_n( '%d draft waiting', '%d drafts waiting', pending.length, 'future-drafts' ),
		pending.length
	);

	return (
		<div className="future-drafts">
			<div className="future-drafts__main">
				{ SUBTITLE && isEmpty && (
					<div className="future-drafts__subtitle">{ SUBTITLE }</div>
				) }

				{ due.length > 0 && (
					<section className="future-drafts__section future-drafts__section--due">
						<h3 className="future-drafts__heading">
							{ _n(
								'Pick this draft back up',
								'Pick these drafts back up',
								due.length,
								'future-drafts'
							) }
						</h3>
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
			</div>

			{ pending.length > 0 && (
				<section className="future-drafts__section future-drafts__section--pending">
					<button
						type="button"
						className="future-drafts__pending-count future-drafts__pending-count--toggle"
						onClick={ () => setPendingExpanded( ( v ) => ! v ) }
						aria-expanded={ pendingExpanded }
					>
						<span>{ pendingCount }</span>
						{ pendingExpanded ? chevronUp : chevronDown }
					</button>
					{ pendingExpanded &&
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
