import apiFetch from '@wordpress/api-fetch';

const ns = ( window.futureDrafts && window.futureDrafts.restNamespace ) || 'future-drafts/v1';

export function listEntries() {
	return apiFetch( { path: `/${ ns }/entries` } );
}

export function createEntry( { title, content, remind_on } ) {
	return apiFetch( {
		path: `/${ ns }/entries`,
		method: 'POST',
		data: { title, content, remind_on },
	} );
}

export function snoozeEntry( id, remind_on ) {
	return apiFetch( {
		path: `/${ ns }/entries/${ id }/snooze`,
		method: 'POST',
		data: { remind_on },
	} );
}

export function deleteEntry( id ) {
	return apiFetch( {
		path: `/${ ns }/entries/${ id }`,
		method: 'DELETE',
	} );
}

export function today() {
	return ( window.futureDrafts && window.futureDrafts.today ) || new Date().toISOString().slice( 0, 10 );
}

export function addDays( isoDate, days ) {
	const d = new Date( `${ isoDate }T00:00:00` );
	d.setDate( d.getDate() + days );
	return d.toISOString().slice( 0, 10 );
}

export function addMonths( isoDate, months ) {
	const d = new Date( `${ isoDate }T00:00:00` );
	d.setMonth( d.getMonth() + months );
	return d.toISOString().slice( 0, 10 );
}
