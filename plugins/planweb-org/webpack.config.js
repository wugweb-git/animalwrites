/**
 * The default @wordpress/scripts build, plus one external: `@kits/shell` is the bundled PlanWeb admin shell
 * (window.planweb.shell, script handle `planweb-shell`, from packages/planweb-shell), so it is never bundled here and
 * lands in index.asset.php as a dependency.
 */
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const DependencyExtractionWebpackPlugin = require( '@wordpress/dependency-extraction-webpack-plugin' );

module.exports = {
	...defaultConfig,
	// The bundled shell is built separately into build/shell/; do not wipe it when the app rebuilds into build/.
	output: {
		...defaultConfig.output,
		clean: false,
	},
	plugins: [
		...defaultConfig.plugins.filter(
			( p ) => p.constructor.name !== 'DependencyExtractionWebpackPlugin'
		),
		new DependencyExtractionWebpackPlugin( {
			requestToExternal: ( request ) =>
				'@kits/shell' === request ? [ 'planweb', 'shell' ] : undefined,
			requestToHandle: ( request ) =>
				'@kits/shell' === request ? 'planweb-shell' : undefined,
		} ),
	],
};
