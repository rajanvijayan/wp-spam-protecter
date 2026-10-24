/**
 * Protection tab: which forms are protected, and what happens to spam and suspicious submissions.
 */
import { Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import { Section, Select, Toggle } from './controls';
import config from './config';

export default function Protection( { value, set } ) {
	const toggle = ( key, label, help ) => (
		<Toggle
			label={ label }
			help={ help }
			checked={ value[ key ] }
			onChange={ ( on ) => set( { [ key ]: on } ) }
		/>
	);
	return (
		<>
			<Section
				title={ __( 'Comments', 'wp-spam-protecter' ) }
				description={ __(
					'Comments and product reviews sent through the comment form. Comments added by other plugins are not checked.',
					'wp-spam-protecter'
				) }
			>
				{ toggle(
					'comments',
					__( 'Protect comments and reviews', 'wp-spam-protecter' )
				) }
				<Select
					label={ __( 'Spam', 'wp-spam-protecter' ) }
					value={ value.comments_spam }
					disabled={ ! value.comments }
					options={ [
						{
							value: 'spam',
							label: __(
								'Move to the Spam folder (review it later)',
								'wp-spam-protecter'
							),
						},
						{
							value: 'reject',
							label: __(
								'Refuse it with an error',
								'wp-spam-protecter'
							),
						},
					] }
					onChange={ ( v ) => set( { comments_spam: v } ) }
				/>
				<Select
					label={ __( 'Suspicious', 'wp-spam-protecter' ) }
					help={ __(
						'For example, sent from a browser without JavaScript.',
						'wp-spam-protecter'
					) }
					value={ value.comments_suspicious }
					disabled={ ! value.comments }
					options={ [
						{
							value: 'hold',
							label: __(
								'Hold for moderation',
								'wp-spam-protecter'
							),
						},
						{
							value: 'spam',
							label: __( 'Treat as spam', 'wp-spam-protecter' ),
						},
						{
							value: 'allow',
							label: __( 'Let it through', 'wp-spam-protecter' ),
						},
					] }
					onChange={ ( v ) => set( { comments_suspicious: v } ) }
				/>
				<Select
					label={ __(
						'Trackbacks and pingbacks',
						'wp-spam-protecter'
					) }
					value={ value.trackbacks }
					options={ [
						{
							value: 'check',
							label: __(
								'Check them with the content rules',
								'wp-spam-protecter'
							),
						},
						{
							value: 'block',
							label: __(
								'Mark all as spam',
								'wp-spam-protecter'
							),
						},
						{
							value: 'allow',
							label: __( 'Do not check', 'wp-spam-protecter' ),
						},
					] }
					onChange={ ( v ) => set( { trackbacks: v } ) }
				/>
			</Section>

			<Section
				title={ __( 'Accounts', 'wp-spam-protecter' ) }
				description={ __(
					'Forms that cannot hold a submission for moderation refuse spam with an error.',
					'wp-spam-protecter'
				) }
			>
				{ toggle(
					'registration',
					__( 'Registration', 'wp-spam-protecter' )
				) }
				{ toggle(
					'lost_password',
					__( 'Lost password', 'wp-spam-protecter' ),
					__(
						'Stops floods of password reset emails.',
						'wp-spam-protecter'
					)
				) }
				{ config.multisite &&
					toggle(
						'signup',
						__( 'Network sign-up', 'wp-spam-protecter' )
					) }
				{ toggle(
					'login',
					__( 'Login', 'wp-spam-protecter' ),
					__(
						'Checks wp-login.php and wp_login_form() logins. Administrators and editors are always let in; XML-RPC, the REST API and application passwords are not affected.',
						'wp-spam-protecter'
					)
				) }
				{ value.login && (
					<Notice status="warning" isDismissible={ false }>
						{ __(
							'A custom login form that does not print the login_form action (or use wp_login_form()) will be refused. Test logging in from another browser before you leave this page. To switch ShieldCraft off in an emergency, add define( "SHIELDCRAFT_DISABLE", true ); to wp-config.php.',
							'wp-spam-protecter'
						) }
					</Notice>
				) }
				<Select
					label={ __(
						'Suspicious submissions',
						'wp-spam-protecter'
					) }
					value={ value.forms_suspicious }
					options={ [
						{
							value: 'block',
							label: __( 'Refuse them', 'wp-spam-protecter' ),
						},
						{
							value: 'allow',
							label: __(
								'Let them through (logged)',
								'wp-spam-protecter'
							),
						},
					] }
					help={ __(
						'Applies to account, checkout and form plugin forms. Logins are never refused only for being suspicious.',
						'wp-spam-protecter'
					) }
					onChange={ ( v ) => set( { forms_suspicious: v } ) }
				/>
			</Section>

			{ config.woocommerce && (
				<Section title={ __( 'WooCommerce', 'wp-spam-protecter' ) }>
					{ toggle(
						'wc_register',
						__( 'My Account registration', 'wp-spam-protecter' )
					) }
					{ toggle(
						'wc_login',
						__( 'My Account login', 'wp-spam-protecter' )
					) }
					{ toggle(
						'wc_checkout',
						__( 'Checkout', 'wp-spam-protecter' ),
						__(
							'The classic checkout gets every check. The block checkout gets the content rules and the rate limit, which also slows down card-testing bots.',
							'wp-spam-protecter'
						)
					) }
				</Section>
			) }

			<Section
				title={ __( 'Form plugins', 'wp-spam-protecter' ) }
				description={ __(
					'Refused messages show the form’s usual error.',
					'wp-spam-protecter'
				) }
			>
				{ Object.entries( config.integrations ).map(
					( [ key, plugin ] ) => (
						<Toggle
							key={ key }
							label={ plugin.label }
							help={
								plugin.active
									? __( 'Active', 'wp-spam-protecter' )
									: __(
											'Not installed or not active',
											'wp-spam-protecter'
									  )
							}
							checked={ value[ key ] }
							onChange={ ( on ) => set( { [ key ]: on } ) }
						/>
					)
				) }
			</Section>
		</>
	);
}
