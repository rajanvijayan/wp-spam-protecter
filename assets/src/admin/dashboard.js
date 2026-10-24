/**
 * Dashboard tab: what was stopped, where and why.
 */
import apiFetch from '@wordpress/api-fetch';
import { Button, ButtonGroup, Spinner } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

import config from './config';
import { Section } from './controls';

function Tile( { label, value, tone } ) {
	return (
		<div
			className={ `shieldcraft-admin__tile shieldcraft-admin__tile--${ tone }` }
		>
			<strong>{ value }</strong>
			<span>{ label }</span>
		</div>
	);
}

function Bars( { rows, labels } ) {
	const max = Math.max( 1, ...rows.map( ( [ , n ] ) => n ) );
	return (
		<ul className="shieldcraft-admin__bars">
			{ rows.map( ( [ key, n ] ) => (
				<li key={ key }>
					<span className="shieldcraft-admin__bar-label">
						{ labels[ key ] || key }
					</span>
					<span className="shieldcraft-admin__bar">
						<span style={ { width: `${ ( n / max ) * 100 }%` } } />
					</span>
					<span className="shieldcraft-admin__bar-value">{ n }</span>
				</li>
			) ) }
		</ul>
	);
}

function Days( { days } ) {
	const entries = Object.entries( days );
	const max = Math.max( 1, ...entries.map( ( [ , n ] ) => n ) );
	return (
		<div
			className="shieldcraft-admin__days"
			role="img"
			aria-label={ __(
				'Stopped and held submissions per day',
				'wp-spam-protecter'
			) }
		>
			{ entries.map( ( [ day, n ] ) => (
				<span
					key={ day }
					title={ `${ day }: ${ n }` }
					style={ {
						height: `${ Math.max( 2, ( n / max ) * 100 ) }%`,
					} }
				/>
			) ) }
		</div>
	);
}

export default function Dashboard( { openLog } ) {
	const [ days, setDays ] = useState( 30 );
	const [ stats, setStats ] = useState( null );

	useEffect( () => {
		setStats( null );
		apiFetch( { path: `/shieldcraft/v1/stats?days=${ days }` } ).then(
			setStats
		);
	}, [ days ] );

	return (
		<>
			<div className="shieldcraft-admin__period">
				<ButtonGroup>
					{ [ 7, 30 ].map( ( d ) => (
						<Button
							key={ d }
							variant={ d === days ? 'primary' : 'secondary' }
							size="compact"
							onClick={ () => setDays( d ) }
						>
							{ sprintf(
								/* translators: %d: days. */
								__( 'Last %d days', 'wp-spam-protecter' ),
								d
							) }
						</Button>
					) ) }
				</ButtonGroup>
			</div>
			{ ! stats ? (
				<Spinner />
			) : (
				<>
					<div className="shieldcraft-admin__tiles">
						<Tile
							label={ __(
								'Stopped or held',
								'wp-spam-protecter'
							) }
							value={ stats.total }
							tone="total"
						/>
						<Tile
							label={ __( 'Spam', 'wp-spam-protecter' ) }
							value={ stats.spam }
							tone="spam"
						/>
						<Tile
							label={ __( 'Suspicious', 'wp-spam-protecter' ) }
							value={ stats.suspicious }
							tone="suspicious"
						/>
					</div>
					<Section title={ __( 'Per day', 'wp-spam-protecter' ) }>
						<Days days={ stats.days } />
					</Section>
					<div className="shieldcraft-admin__columns">
						<Section title={ __( 'Where', 'wp-spam-protecter' ) }>
							{ Object.keys( stats.contexts ).length ? (
								<Bars
									rows={ Object.entries( stats.contexts ) }
									labels={ config.contexts }
								/>
							) : (
								<p>
									{ __(
										'Nothing stopped yet.',
										'wp-spam-protecter'
									) }
								</p>
							) }
						</Section>
						<Section title={ __( 'Why', 'wp-spam-protecter' ) }>
							{ Object.keys( stats.reasons ).length ? (
								<Bars
									rows={ Object.entries(
										stats.reasons
									).slice( 0, 8 ) }
									labels={ config.reasons }
								/>
							) : (
								<p>
									{ __(
										'Nothing stopped yet.',
										'wp-spam-protecter'
									) }
								</p>
							) }
						</Section>
					</div>
					<Button variant="secondary" onClick={ openLog }>
						{ __( 'Open the log', 'wp-spam-protecter' ) }
					</Button>
				</>
			) }
		</>
	);
}
