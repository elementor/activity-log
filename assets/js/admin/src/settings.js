import { useState, useEffect, useCallback } from '@wordpress/element';
import {
	Button,
	SelectControl,
	Notice,
	Spinner,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { fetchSettings, saveSettings, eraseLogs } from './api';

export default function Settings() {
	const [ schema, setSchema ] = useState( null );
	const [ values, setValues ] = useState( {} );
	const [ canEraseLogs, setCanEraseLogs ] = useState( false );
	const [ loading, setLoading ] = useState( true );
	const [ saving, setSaving ] = useState( false );
	const [ erasing, setErasing ] = useState( false );
	const [ notice, setNotice ] = useState( null );

	const loadSettings = useCallback( () => {
		setLoading( true );
		fetchSettings()
			.then( ( result ) => {
				setSchema( result.fields );
				setCanEraseLogs( result.canEraseLogs );

				const initial = {};
				Object.entries( result.fields ).forEach( ( [ key, field ] ) => {
					initial[ key ] = field.value;
				} );
				setValues( initial );

				setLoading( false );
			} )
			.catch( ( err ) => {
				setNotice( { status: 'error', message: err.message || __( 'Failed to load settings.', 'aryo-activity-log' ) } );
				setLoading( false );
			} );
	}, [] );

	useEffect( () => {
		loadSettings();
	}, [ loadSettings ] );

	const handleSave = () => {
		setSaving( true );
		setNotice( null );

		saveSettings( values )
			.then( () => {
				setNotice( { status: 'success', message: __( 'Settings saved.', 'aryo-activity-log' ) } );
				setSaving( false );
			} )
			.catch( ( err ) => {
				setNotice( { status: 'error', message: err.message || __( 'Failed to save settings.', 'aryo-activity-log' ) } );
				setSaving( false );
			} );
	};

	const handleErase = () => {
		if ( ! window.confirm( __( 'Attention: We are going to DELETE ALL ACTIVITIES from the database. Are you sure you want to do that?', 'aryo-activity-log' ) ) ) {
			return;
		}

		setErasing( true );
		setNotice( null );

		eraseLogs()
			.then( () => {
				setNotice( { status: 'success', message: __( 'All activities have been successfully deleted.', 'aryo-activity-log' ) } );
				setErasing( false );
			} )
			.catch( ( err ) => {
				setNotice( { status: 'error', message: err.message || __( 'Failed to delete activities.', 'aryo-activity-log' ) } );
				setErasing( false );
			} );
	};

	const updateValue = ( key, val ) => {
		setValues( ( prev ) => ( { ...prev, [ key ]: val } ) );
	};

	if ( loading ) {
		return (
			<div style={ { textAlign: 'center', padding: '40px 0' } }>
				<Spinner />
			</div>
		);
	}

	if ( ! schema ) {
		return null;
	}

	return (
		<div style={ { marginTop: 16, maxWidth: 680 } }>
			{ notice && (
				<Notice
					status={ notice.status }
					isDismissible
					onDismiss={ () => setNotice( null ) }
					style={ { margin: '0 0 16px' } }
				>
					{ notice.message }
				</Notice>
			) }

			<table className="form-table" role="presentation">
				<tbody>
					{ schema.logs_lifespan && (
						<tr>
							<th scope="row">
								<label htmlFor="aal-logs-lifespan">
									{ schema.logs_lifespan.label }
								</label>
							</th>
							<td>
								<input
									id="aal-logs-lifespan"
									type="number"
									min="1"
									step="1"
									className="small-text"
									value={ values.logs_lifespan || '' }
									onChange={ ( e ) => updateValue( 'logs_lifespan', e.target.value ) }
								/>
								{ ' ' }
								<span>{ schema.logs_lifespan.suffix }</span>
								{ schema.logs_lifespan.description && (
									<p className="description">{ schema.logs_lifespan.description }</p>
								) }
							</td>
						</tr>
					) }

					{ schema.logs_failed_login && (
						<tr>
							<th scope="row">
								<label htmlFor="aal-logs-failed-login">
									{ schema.logs_failed_login.label }
								</label>
							</th>
							<td>
								<SelectControl
									id="aal-logs-failed-login"
									value={ values.logs_failed_login || 'yes' }
									options={ schema.logs_failed_login.options }
									onChange={ ( v ) => updateValue( 'logs_failed_login', v ) }
									__nextHasNoMarginBottom
								/>
							</td>
						</tr>
					) }

					{ schema.logs_email && (
						<tr>
							<th scope="row">
								<label htmlFor="aal-logs-email">
									{ schema.logs_email.label }
								</label>
							</th>
							<td>
								<SelectControl
									id="aal-logs-email"
									value={ values.logs_email || 'yes' }
									options={ schema.logs_email.options }
									onChange={ ( v ) => updateValue( 'logs_email', v ) }
									__nextHasNoMarginBottom
								/>
							</td>
						</tr>
					) }

					{ schema.log_visitor_ip_source && (
						<tr>
							<th scope="row">
								<label htmlFor="aal-log-visitor-ip-source">
									{ schema.log_visitor_ip_source.label }
								</label>
							</th>
							<td>
								<SelectControl
									id="aal-log-visitor-ip-source"
									value={ values.log_visitor_ip_source || 'REMOTE_ADDR' }
									options={ schema.log_visitor_ip_source.options }
									onChange={ ( v ) => updateValue( 'log_visitor_ip_source', v ) }
									__nextHasNoMarginBottom
								/>
								{ schema.log_visitor_ip_source.description && (
									<p className="description">{ schema.log_visitor_ip_source.description }</p>
								) }
							</td>
						</tr>
					) }

					{ canEraseLogs && (
						<tr>
							<th scope="row">
								{ __( 'Delete Log Activities', 'aryo-activity-log' ) }
							</th>
							<td>
								<Button
									variant="secondary"
									isDestructive
									onClick={ handleErase }
									isBusy={ erasing }
									disabled={ erasing }
								>
									{ __( 'Reset Database', 'aryo-activity-log' ) }
								</Button>
								<p className="description">
									{ __( 'Warning: Clicking this will delete all activities from the database.', 'aryo-activity-log' ) }
								</p>
							</td>
						</tr>
					) }
				</tbody>
			</table>

			<p className="submit">
				<Button
					variant="primary"
					onClick={ handleSave }
					isBusy={ saving }
					disabled={ saving }
				>
					{ __( 'Save Changes', 'aryo-activity-log' ) }
				</Button>
			</p>
		</div>
	);
}
