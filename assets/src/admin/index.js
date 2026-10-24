/**
 * ShieldCraft admin (Settings → ShieldCraft).
 */
import apiFetch from '@wordpress/api-fetch';
import { Button, Notice, Spinner, TabPanel } from '@wordpress/components';
import { createRoot, useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import config from './config';
import Dashboard from './dashboard';
import Log from './log';
import Privacy from './privacy';
import Protection from './protection';
import Rules from './rules';
import './index.scss';

const TABS = [
	{ name: 'dashboard', title: __( 'Dashboard', 'wp-spam-protecter' ) },
	{ name: 'protection', title: __( 'Protection', 'wp-spam-protecter' ) },
	{ name: 'rules', title: __( 'Rules', 'wp-spam-protecter' ) },
	{
		name: 'privacy',
		title: __( 'Allowlist and privacy', 'wp-spam-protecter' ),
	},
	{ name: 'log', title: __( 'Log', 'wp-spam-protecter' ) },
];

// Tabs that edit settings (and show the Save button).
const SETTINGS_TABS = [ 'protection', 'rules', 'privacy' ];

function hashTab() {
	const hash = window.location.hash.replace( '#', '' );
	return TABS.some( ( tab ) => tab.name === hash ) ? hash : 'dashboard';
}

function App() {
	const [ settings, setSettings ] = useState( null );
	const [ saved, setSaved ] = useState( '' );
	const [ senders, setSenders ] = useState( { allowed: 0, blocked: 0 } );
	const [ saving, setSaving ] = useState( false );
	const [ notice, setNotice ] = useState( null );
	const [ tab, setTab ] = useState( hashTab() );
	// Remounts the tab panel when a tab is opened from code (the dashboard's "Open the log").
	const [ panelKey, setPanelKey ] = useState( 0 );

	const load = ( data ) => {
		setSettings( data.settings );
		setSaved( JSON.stringify( data.settings ) );
		setSenders( data.senders );
	};

	useEffect( () => {
		apiFetch( { path: '/shieldcraft/v1/settings' } )
			.then( load )
			.catch( ( error ) =>
				setNotice( { status: 'error', text: error.message } )
			);
	}, [] );

	const dirty = settings && JSON.stringify( settings ) !== saved;

	useEffect( () => {
		const warn = ( event ) => {
			if ( dirty ) {
				event.preventDefault();
				event.returnValue = '';
			}
		};
		window.addEventListener( 'beforeunload', warn );
		return () => window.removeEventListener( 'beforeunload', warn );
	}, [ dirty ] );

	if ( ! settings ) {
		return notice ? (
			<Notice status="error" isDismissible={ false }>
				{ notice.text }
			</Notice>
		) : (
			<Spinner />
		);
	}

	const props = {
		value: settings,
		set: ( changes ) =>
			setSettings( ( current ) => ( { ...current, ...changes } ) ),
	};

	const save = () => {
		setSaving( true );
		setNotice( null );
		apiFetch( {
			path: '/shieldcraft/v1/settings',
			method: 'POST',
			data: settings,
		} )
			.then( ( data ) => {
				load( data );
				setNotice( {
					status: 'success',
					text: __( 'Settings saved.', 'wp-spam-protecter' ),
				} );
			} )
			.catch( ( error ) =>
				setNotice( { status: 'error', text: error.message } )
			)
			.finally( () => setSaving( false ) );
	};

	const open = ( name ) => {
		window.history.replaceState( null, '', '#' + name );
		setTab( name );
		setPanelKey( ( key ) => key + 1 );
	};

	return (
		<div className="shieldcraft-admin">
			<div className="shieldcraft-admin__header">
				<h1>{ __( 'ShieldCraft', 'wp-spam-protecter' ) }</h1>
				<span className="shieldcraft-admin__version">
					{ config.version }
				</span>
			</div>
			{ config.disabled && (
				<Notice status="warning" isDismissible={ false }>
					{ __(
						'ShieldCraft is switched off by SHIELDCRAFT_DISABLE in wp-config.php: nothing is checked.',
						'wp-spam-protecter'
					) }
				</Notice>
			) }
			{ notice && (
				<Notice
					status={ notice.status }
					onRemove={ () => setNotice( null ) }
				>
					{ notice.text }
				</Notice>
			) }
			<TabPanel
				key={ panelKey }
				className="shieldcraft-admin__tabs"
				tabs={ TABS }
				initialTabName={ tab }
				onSelect={ ( name ) => {
					window.history.replaceState( null, '', '#' + name );
					setTab( name );
				} }
			>
				{ ( current ) => (
					<div className="shieldcraft-admin__panel">
						{ current.name === 'dashboard' && (
							<Dashboard openLog={ () => open( 'log' ) } />
						) }
						{ current.name === 'protection' && (
							<Protection { ...props } />
						) }
						{ current.name === 'rules' && <Rules { ...props } /> }
						{ current.name === 'privacy' && (
							<Privacy
								{ ...props }
								senders={ senders }
								setSenders={ setSenders }
							/>
						) }
						{ current.name === 'log' && <Log /> }
					</div>
				) }
			</TabPanel>
			{ ( SETTINGS_TABS.includes( tab ) || dirty ) && (
				<div className="shieldcraft-admin__footer">
					<Button
						variant="primary"
						isBusy={ saving }
						disabled={ saving || ! dirty }
						onClick={ save }
					>
						{ __( 'Save settings', 'wp-spam-protecter' ) }
					</Button>
					{ dirty && (
						<span className="shieldcraft-admin__muted">
							{ __( 'Unsaved changes', 'wp-spam-protecter' ) }
						</span>
					) }
				</div>
			) }
		</div>
	);
}

const root = document.getElementById( 'shieldcraft-admin' );
if ( root ) {
	createRoot( root ).render( <App /> );
}
