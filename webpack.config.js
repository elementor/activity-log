const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

module.exports = {
	...defaultConfig,
	module: {
		...defaultConfig.module,
		rules: defaultConfig.module.rules.map( ( rule ) => {
			if ( ! rule.test || ! rule.test.toString().includes( 'jsx' ) ) {
				return rule;
			}

			return {
				...rule,
				use: ( Array.isArray( rule.use ) ? rule.use : [ rule.use ] ).map(
					( use ) => {
						const loader =
							typeof use === 'string' ? { loader: use } : { ...use };

						if (
							loader.loader &&
							loader.loader.includes( 'babel-loader' )
						) {
							loader.options = {
								...( loader.options || {} ),
								presets: ( loader.options?.presets || [] ).map(
									( preset ) => {
										if ( Array.isArray( preset ) ) {
											const [ name, opts = {} ] = preset;
											if (
												typeof name === 'string' &&
												name.includes(
													'@babel/preset-react'
												)
											) {
												return [
													name,
													{
														...opts,
														runtime: 'classic',
													},
												];
											}
										}
										return preset;
									}
								),
							};
						}

						return loader;
					}
				),
			};
		} ),
	},
};
