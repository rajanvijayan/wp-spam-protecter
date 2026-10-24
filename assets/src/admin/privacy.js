/**
 * Allowlist and privacy tab.
 */
import apiFetch from '@wordpress/api-fetch';
import { Button } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';

import {
	Checklist,
	Lines,
	NumberInput,
	Section,
	Select,
	Toggle,
} from './controls';
import config from './config';

function SenderList( { list, count, onCleared } ) {
	const [ busy, setBusy ] = useState( false );
	const label =
		list === 'allowed'
			? sprintf(
					/* translators: %d: number of senders. */
					_n(
						'%d sender marked “Not spam” in the log',
						'%d senders marked “Not spam” in the log',
						count,
						'wp-spam-protecter'
					),
					count
			  )
			: sprintf(
					/* translators: %d: number of senders. */
					_n(
						'%d sender blocked from the log',
						'%d senders blocked from the log',
						count,
						'wp-spam-protecter'
					),
					count
			  );
	return (
		<div className="shieldcraft-admin__row">
			<span>{ label }</span>
			<Button
				variant="secondary"
				size="compact"
				isBusy={ busy }
				disabled={ busy || ! count }
				onClick={ () => {
					setBusy( true );
					apiFetch( {
						path: '/shieldcraft/v1/senders/' + list,
						method: 'DELETE',
					} )
						.then( onCleared )
						.finally( () => setBusy( false ) );
				} }
			>
				{ __( 'Clear', 'wp-spam-protecter' ) }
			</Button>
		</div>
	);
}

export default function Privacy( { value, set, senders, setSenders } ) {
	return (
		<>
			<Section
				title={ __( 'Allowlist', 'wp-spam-protecter' ) }
				description={ __(
					'These senders skip every check. Users who can moderate comments always do.',
					'wp-spam-protecter'
				) }
			>
				<Checklist
					label={ __(
						'Logged-in users with these roles',
						'wp-spam-protecter'
					) }
					options={ config.roles }
					value={ value.allowed_roles }
					onChange={ ( v ) => set( { allowed_roles: v } ) }
				/>
				<Lines
					label={ __( 'IP addresses', 'wp-spam-protecter' ) }
					help={ __(
						'Addresses, ranges or prefixes, one per line.',
						'wp-spam-protecter'
					) }
					value={ value.allowed_ips }
					onChange={ ( v ) => set( { allowed_ips: v } ) }
				/>
				<Lines
					label={ __( 'Email addresses', 'wp-spam-protecter' ) }
					help={ __(
						'Addresses, domains or patterns, one per line.',
						'wp-spam-protecter'
					) }
					value={ value.allowed_emails }
					onChange={ ( v ) => set( { allowed_emails: v } ) }
				/>
				<SenderList
					list="allowed"
					count={ senders.allowed }
					onCleared={ setSenders }
				/>
				<SenderList
					list="blocked"
					count={ senders.blocked }
					onCleared={ setSenders }
				/>
			</Section>

			<Section
				title={ __( 'Log and privacy', 'wp-spam-protecter' ) }
				description={ __(
					'Only stopped and held submissions are logged. Email and IP addresses are stored as coded (hashed) values that cannot be read back. ShieldCraft never contacts other services.',
					'wp-spam-protecter'
				) }
			>
				<NumberInput
					label={ __( 'Keep the log for days', 'wp-spam-protecter' ) }
					min={ 1 }
					max={ 365 }
					value={ value.retention_days }
					onChange={ ( v ) => set( { retention_days: v } ) }
				/>
				<Toggle
					label={ __(
						'Store IP addresses in the log',
						'wp-spam-protecter'
					) }
					help={ __(
						'Shows the address of each stopped submission. Mention it in your privacy policy.',
						'wp-spam-protecter'
					) }
					checked={ value.store_ip }
					onChange={ ( on ) => set( { store_ip: on } ) }
				/>
				<Select
					label={ __(
						'Visitor IP address comes from',
						'wp-spam-protecter'
					) }
					help={ __(
						'Change it only if your site is behind a proxy or CDN that sends this header; otherwise visitors could fake their address.',
						'wp-spam-protecter'
					) }
					value={ value.ip_header }
					options={ Object.entries( config.headers ).map(
						( [ header, current ] ) => ( {
							value: header === 'REMOTE_ADDR' ? '' : header,
							label: `${ header } (${
								current ||
								__( 'not sent now', 'wp-spam-protecter' )
							})`,
						} )
					) }
					onChange={ ( v ) => set( { ip_header: v } ) }
				/>
			</Section>
		</>
	);
}
