const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const path = require( 'path' );

module.exports = {
	...defaultConfig,
	entry: {
		widget: path.resolve( __dirname, 'assets/src/widget.js' ),
	},
};
