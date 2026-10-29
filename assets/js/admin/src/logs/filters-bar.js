import {
	SelectControl,
	SearchControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const dateOptions = [
	{ label: __( 'All Time', 'aryo-activity-log' ), value: '' },
	{ label: __( 'Today', 'aryo-activity-log' ), value: 'today' },
	{ label: __( 'Yesterday', 'aryo-activity-log' ), value: 'yesterday' },
	{ label: __( 'Last 7 days', 'aryo-activity-log' ), value: 'week' },
	{ label: __( 'Last 30 days', 'aryo-activity-log' ), value: 'month' },
];

export default function FiltersBar( { params, filterOptions, searchInput, hasActiveFilters, onFilterChange, onSearchChange, onReset } ) {
	return (
		<div className="aal-logs-filters">
			<div className="aal-logs-filters__controls">
				<SelectControl
					value={ params.dateshow }
					options={ dateOptions }
					onChange={ ( v ) => onFilterChange( 'dateshow', v ) }
					__nextHasNoMarginBottom
				/>

				{ filterOptions && filterOptions.roles.length > 0 && (
					<SelectControl
						value={ params.capshow }
						options={ [
							{ label: __( 'All Roles', 'aryo-activity-log' ), value: '' },
							...filterOptions.roles.map( ( r ) => ( { label: r.label, value: r.value } ) ),
						] }
						onChange={ ( v ) => onFilterChange( 'capshow', v ) }
						__nextHasNoMarginBottom
					/>
				) }

				{ filterOptions && filterOptions.users.length > 0 && (
					<SelectControl
						value={ params.usershow }
						options={ [
							{ label: __( 'All Users', 'aryo-activity-log' ), value: '' },
							...filterOptions.users.map( ( u ) => ( { label: u.name, value: String( u.id ) } ) ),
						] }
						onChange={ ( v ) => onFilterChange( 'usershow', v ) }
						__nextHasNoMarginBottom
					/>
				) }

				{ filterOptions && filterOptions.topics.length > 0 && (
					<SelectControl
						value={ params.typeshow }
						options={ [
							{ label: __( 'All Topics', 'aryo-activity-log' ), value: '' },
							...filterOptions.topics.map( ( t ) => ( { label: t.label, value: t.value } ) ),
						] }
						onChange={ ( v ) => onFilterChange( 'typeshow', v ) }
						__nextHasNoMarginBottom
					/>
				) }

				{ filterOptions && filterOptions.actions.length > 0 && (
					<SelectControl
						value={ params.showaction }
						options={ [
							{ label: __( 'All Actions', 'aryo-activity-log' ), value: '' },
							...filterOptions.actions.map( ( a ) => ( { label: a.label, value: a.value } ) ),
						] }
						onChange={ ( v ) => onFilterChange( 'showaction', v ) }
						__nextHasNoMarginBottom
					/>
				) }

				{ filterOptions && filterOptions.sources.length > 0 && (
					<SelectControl
						value={ params.sourceshow }
						options={ [
							{ label: __( 'All Sources', 'aryo-activity-log' ), value: '' },
							...filterOptions.sources.map( ( s ) => ( { label: s.label, value: s.value } ) ),
						] }
						onChange={ ( v ) => onFilterChange( 'sourceshow', v ) }
						__nextHasNoMarginBottom
					/>
				) }

				{ hasActiveFilters && (
					<a
						href="#"
						className="aal-logs-filters__reset"
						onClick={ ( e ) => { e.preventDefault(); onReset(); } }
					>
						<span className="dashicons dashicons-dismiss" aria-hidden="true"></span>
						{ __( 'Reset Filters', 'aryo-activity-log' ) }
					</a>
				) }
			</div>

			<SearchControl
				className="aal-logs-filters__search"
				value={ searchInput }
				onChange={ onSearchChange }
				placeholder={ __( 'Search…', 'aryo-activity-log' ) }
				__nextHasNoMarginBottom
			/>
		</div>
	);
}
