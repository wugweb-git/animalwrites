/**
 * The default @wordpress/scripts build, plus one external: `@kits/shell` is the plugin's own bundled admin shell
 * (window.mailweb.shell, script handle `mailweb-shell`, from packages/mailweb-shell), so it is never bundled here and
 * lands in index.asset.php as a dependency.
 */
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const DependencyExtractionWebpackPlugin = require( '@wordpress/dependency-extraction-webpack-plugin' );

module.exports = {
	...defaultConfig,
	plugins: [
		...defaultConfig.plugins.filter(
			( p ) => p.constructor.name !== 'DependencyExtractionWebpackPlugin'
		),
		new DependencyExtractionWebpackPlugin( {
			requestToExternal: ( request ) =>
				'@kits/shell' === request ? [ 'mailweb', 'shell' ] : undefined,
			requestToHandle: ( request ) =>
				'@kits/shell' === request ? 'mailweb-shell' : undefined,
		} ),
	],
};
