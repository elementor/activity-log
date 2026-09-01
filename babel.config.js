module.exports = ( api ) => {
	api.cache( true );

	return {
		presets: [
			'@babel/preset-env',
			[
				'@babel/preset-react',
				{
					runtime: 'classic',
					pragma: 'wp.element.createElement',
					pragmaFrag: 'wp.element.Fragment',
				},
			],
		],
	};
};
