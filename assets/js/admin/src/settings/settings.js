import { useState, useEffect, useCallback } from '@wordpress/element';
import {
	Button,
	Notice,
	Spinner,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { fetchSettings, saveSettings, eraseLogs } from '../api';
import SettingRow from './setting-row';
import EraseLogsRow from './erase-logs-row';

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
					<SettingRow
						id="aal-logs-lifespan"
						schema={ schema.logs_lifespan }
						value={ values.logs_lifespan }
						onChange={ ( v ) => updateValue( 'logs_lifespan', v ) }
					/>
					<SettingRow
						id="aal-logs-failed-login"
						schema={ schema.logs_failed_login }
						value={ values.logs_failed_login }
						onChange={ ( v ) => updateValue( 'logs_failed_login', v ) }
					/>
					<SettingRow
						id="aal-logs-email"
						schema={ schema.logs_email }
						value={ values.logs_email }
						onChange={ ( v ) => updateValue( 'logs_email', v ) }
					/>
					<SettingRow
						id="aal-log-visitor-ip-source"
						schema={ schema.log_visitor_ip_source }
						value={ values.log_visitor_ip_source }
						onChange={ ( v ) => updateValue( 'log_visitor_ip_source', v ) }
					/>
					<SettingRow
						id="aal-abilities-agent-exposure"
						schema={ schema.abilities_agent_exposure }
						value={ values.abilities_agent_exposure }
						onChange={ ( v ) => updateValue( 'abilities_agent_exposure', v ) }
					/>
					{ canEraseLogs && (
						<EraseLogsRow erasing={ erasing } onErase={ handleErase } />
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
