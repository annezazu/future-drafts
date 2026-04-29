import { Button, ConfirmDialog } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { closeSmall, arrowRight } from '@wordpress/icons';
import SnoozeMenu from './SnoozeMenu';
import { today } from './api';

function relativeLabel( remindOn ) {
	const t = today();
	if ( remindOn <= t ) {
		return __( 'today', 'future-drafts' );
	}
	const a = new Date( `${ remindOn }T00:00:00` );
	const b = new Date( `${ t }T00:00:00` );
	const days = Math.round( ( a - b ) / 86400000 );
	if ( days === 1 ) return __( 'tomorrow', 'future-drafts' );
	if ( days < 14 ) return sprintf( __( 'in %d days', 'future-drafts' ), days );
	if ( days < 60 ) return sprintf( __( 'in %d weeks', 'future-drafts' ), Math.round( days / 7 ) );
	if ( days < 365 ) return sprintf( __( 'in %d months', 'future-drafts' ), Math.round( days / 30 ) );
	return sprintf( __( 'in %d years', 'future-drafts' ), Math.round( days / 365 ) );
}

export default function EntryRow( { entry, variant, onSnooze, onDelete } ) {
	const [ confirmingDelete, setConfirmingDelete ] = useState( false );
	const isDue = variant === 'due';

	return (
		<div className={ `future-drafts-row future-drafts-row--${ variant }` }>
			<div className="future-drafts-row__main">
				<a className="future-drafts-row__title" href={ entry.edit_url }>
					{ entry.title || __( '(no title)', 'future-drafts' ) }
				</a>
				{ entry.excerpt && (
					<div className="future-drafts-row__excerpt">{ entry.excerpt }</div>
				) }
				<div className="future-drafts-row__meta">{ relativeLabel( entry.remind_on ) }</div>
			</div>
			<div className="future-drafts-row__actions">
				{ isDue && (
					<Button
						variant="primary"
						size="small"
						href={ entry.edit_url }
						icon={ arrowRight }
						iconPosition="right"
					>
						{ __( 'Finish writing', 'future-drafts' ) }
					</Button>
				) }
				<SnoozeMenu onSnooze={ ( date ) => onSnooze( entry, date ) } />
				<Button
					variant="tertiary"
					size="small"
					icon={ closeSmall }
					label={ __( 'Delete', 'future-drafts' ) }
					onClick={ () => setConfirmingDelete( true ) }
				/>
			</div>
			{ confirmingDelete && (
				<ConfirmDialog
					onConfirm={ () => {
						setConfirmingDelete( false );
						onDelete( entry );
					} }
					onCancel={ () => setConfirmingDelete( false ) }
					confirmButtonText={ __( 'Delete', 'future-drafts' ) }
				>
					{ __( 'Delete this future draft? This will trash the post.', 'future-drafts' ) }
				</ConfirmDialog>
			) }
		</div>
	);
}
