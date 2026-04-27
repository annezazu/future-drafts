import { createRoot } from '@wordpress/element';
import App from './App';
import './widget.scss';

const root = document.getElementById( 'future-drafts-root' );
if ( root ) {
	createRoot( root ).render( <App /> );
}
