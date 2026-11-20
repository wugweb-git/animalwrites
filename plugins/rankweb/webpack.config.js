/**
 * The default @wordpress/scripts build, plus one external: `@kits/shell` is Kits Core's shared admin shell
 * (window.kits.shell, script handle `kits-shell`), so it is never bundled here and lands in index.asset.php.
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
				'@kits/shell' === request ? [ 'kits', 'shell' ] : undefined,
			requestToHandle: ( request ) =>
				'@kits/shell' === request ? 'kits-shell' : undefined,
		} ),
	],
};
