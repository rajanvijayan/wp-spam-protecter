/**
 * Rules tab: the invisible checks and the content rules.
 */
import { ExternalLink } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import {
	Checklist,
	Lines,
	NumberInput,
	Section,
	Select,
	Toggle,
} from './controls';
import config from './config';

export default function Rules( { value, set } ) {
	return (
		<>
			<Section
				title={ __( 'Invisible checks', 'wp-spam-protecter' ) }
				description={ __(
					'Visitors never see these. Bots that post straight to a form, fill in every field or send within a moment are stopped.',
					'wp-spam-protecter'
				) }
			>
				<Toggle
					label={ __(
						'Hidden field that people never fill in',
						'wp-spam-protecter'
					) }
					checked={ value.honeypot }
					onChange={ ( on ) => set( { honeypot: on } ) }
				/>
				<Toggle
					label={ __( 'Time trap', 'wp-spam-protecter' ) }
					checked={ value.time_trap }
					onChange={ ( on ) => set( { time_trap: on } ) }
				/>
				<NumberInput
					label={ __(
						'Minimum seconds between opening and sending a form',
						'wp-spam-protecter'
					) }
					help={ __(
						'Login forms use at most 1 second, so password managers keep working.',
						'wp-spam-protecter'
					) }
					min={ 0 }
					max={ 60 }
					disabled={ ! value.time_trap }
					value={ value.min_seconds }
					onChange={ ( v ) => set( { min_seconds: v } ) }
				/>
				<Select
					label={ __(
						'Submissions without JavaScript',
						'wp-spam-protecter'
					) }
					value={ value.javascript }
					options={ [
						{
							value: 'suspicious',
							label: __(
								'Suspicious (comments are held)',
								'wp-spam-protecter'
							),
						},
						{
							value: 'spam',
							label: __( 'Spam', 'wp-spam-protecter' ),
						},
						{
							value: 'off',
							label: __( 'Do not check', 'wp-spam-protecter' ),
						},
					] }
					onChange={ ( v ) => set( { javascript: v } ) }
				/>
				<NumberInput
					label={ __(
						'Hours a form may stay open without JavaScript',
						'wp-spam-protecter'
					) }
					min={ 1 }
					max={ 168 }
					value={ value.token_hours }
					onChange={ ( v ) => set( { token_hours: v } ) }
				/>
			</Section>

			<Section title={ __( 'Content rules', 'wp-spam-protecter' ) }>
				<Toggle
					label={ __( 'Limit links', 'wp-spam-protecter' ) }
					checked={ value.links }
					onChange={ ( on ) => set( { links: on } ) }
				/>
				<NumberInput
					label={ __(
						'Most links allowed in a message',
						'wp-spam-protecter'
					) }
					min={ 0 }
					max={ 50 }
					disabled={ ! value.links }
					value={ value.max_links }
					onChange={ ( v ) => set( { max_links: v } ) }
				/>
				<Lines
					label={ __( 'Blocked words', 'wp-spam-protecter' ) }
					help={ __(
						'One per line, matched anywhere in the name, email, website or message. Lines starting with # are ignored.',
						'wp-spam-protecter'
					) }
					value={ value.blocked_words }
					onChange={ ( v ) => set( { blocked_words: v } ) }
				/>
				<Toggle
					label={ __(
						'Also block the “Disallowed Comment Keys”',
						'wp-spam-protecter'
					) }
					help={
						<ExternalLink href={ config.discussion }>
							{ __(
								'Settings → Discussion',
								'wp-spam-protecter'
							) }
						</ExternalLink>
					}
					checked={ value.disallowed_keys }
					onChange={ ( on ) => set( { disallowed_keys: on } ) }
				/>
				<Lines
					label={ __(
						'Blocked email addresses',
						'wp-spam-protecter'
					) }
					help={ __(
						'Addresses, domains (example.com) or patterns (*@example.com).',
						'wp-spam-protecter'
					) }
					value={ value.blocked_emails }
					onChange={ ( v ) => set( { blocked_emails: v } ) }
				/>
				<Lines
					label={ __( 'Blocked IP addresses', 'wp-spam-protecter' ) }
					help={ __(
						'Addresses, ranges (203.0.113.0/24, 2001:db8::/32) or prefixes (203.0.113.*).',
						'wp-spam-protecter'
					) }
					value={ value.blocked_ips }
					onChange={ ( v ) => set( { blocked_ips: v } ) }
				/>
				<Checklist
					label={ __(
						'Block messages written mostly in',
						'wp-spam-protecter'
					) }
					options={ config.scripts.map( ( script ) => ( {
						value: script,
						label: script,
					} ) ) }
					value={ value.blocked_scripts }
					onChange={ ( v ) => set( { blocked_scripts: v } ) }
				/>
			</Section>

			<Section title={ __( 'Repetition', 'wp-spam-protecter' ) }>
				<Toggle
					label={ __(
						'Block the same message sent again',
						'wp-spam-protecter'
					) }
					checked={ value.duplicate }
					onChange={ ( on ) => set( { duplicate: on } ) }
				/>
				<NumberInput
					label={ __( 'Within minutes', 'wp-spam-protecter' ) }
					min={ 1 }
					max={ 1440 }
					disabled={ ! value.duplicate }
					value={ value.duplicate_minutes }
					onChange={ ( v ) => set( { duplicate_minutes: v } ) }
				/>
				<Toggle
					label={ __( 'Rate limit', 'wp-spam-protecter' ) }
					checked={ value.rate_limit }
					onChange={ ( on ) => set( { rate_limit: on } ) }
				/>
				<NumberInput
					label={ __(
						'Submissions per visitor per minute, for each kind of form',
						'wp-spam-protecter'
					) }
					min={ 1 }
					max={ 100 }
					disabled={ ! value.rate_limit }
					value={ value.rate_per_minute }
					onChange={ ( v ) => set( { rate_per_minute: v } ) }
				/>
			</Section>
		</>
	);
}
