/**
 * Log tab: every stopped or held submission, with Not spam, Block sender and Delete.
 */
import apiFetch from '@wordpress/api-fetch';
import { Button, Notice, SearchControl, Spinner } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

import config from './config';
import { Select } from './controls';

const PER_PAGE = 20;

const STATUS = {
	spam: __( 'In the Spam folder', 'wp-spam-protecter' ),
	held: __( 'Held for moderation', 'wp-spam-protecter' ),
	approved: __( 'Published', 'wp-spam-protecter' ),
	released: __( 'Marked not spam', 'wp-spam-protecter' ),
	blocked: __( 'Sender blocked', 'wp-spam-protecter' ),
};

function Reasons( { reasons } ) {
	return (
		<ul className="shieldcraft-admin__reasons">
			{ reasons.map( ( reason, i ) => (
				<li key={ i }>
					{ config.reasons[ reason.check ] || reason.check }
					{ reason.detail && (
						<span className="shieldcraft-admin__detail">
							{ ' ' }
							({ reason.detail })
						</span>
					) }
				</li>
			) ) }
		</ul>
	);
}

function Row( { row, onChange } ) {
	const [ busy, setBusy ] = useState( '' );
	const act = ( action ) => {
		setBusy( action );
		apiFetch( {
			path: `/shieldcraft/v1/log/${ row.id }/action`,
			method: 'POST',
			data: { action },
		} )
			.then( ( result ) => onChange( row.id, result ) )
			.catch( () => setBusy( '' ) );
	};
	return (
		<tr>
			<td>
				{ new Date( row.created ).toLocaleString() }
				<div className="shieldcraft-admin__muted">
					{ config.contexts[ row.context ] || row.context }
					{ row.form && ` #${ row.form }` }
				</div>
			</td>
			<td>
				<span
					className={ `shieldcraft-admin__badge shieldcraft-admin__badge--${ row.verdict }` }
				>
					{ row.verdict === 'spam'
						? __( 'Spam', 'wp-spam-protecter' )
						: __( 'Suspicious', 'wp-spam-protecter' ) }
				</span>
				<Reasons reasons={ row.reasons } />
			</td>
			<td className="shieldcraft-admin__message">
				<strong>{ row.name || '—' }</strong>
				{ row.ip && (
					<span className="shieldcraft-admin__muted">
						{ ' ' }
						{ row.ip }
					</span>
				) }
				<div>{ row.excerpt }</div>
				<div className="shieldcraft-admin__muted">
					{ STATUS[ row.status ] || '' }
					{ row.link && (
						<>
							{ ' ' }
							<a href={ row.link }>
								{ __( 'Edit comment', 'wp-spam-protecter' ) }
							</a>
						</>
					) }
				</div>
			</td>
			<td className="shieldcraft-admin__actions">
				<Button
					variant="secondary"
					size="compact"
					isBusy={ busy === 'not_spam' }
					disabled={ !! busy || row.status === 'released' }
					onClick={ () => act( 'not_spam' ) }
				>
					{ __( 'Not spam', 'wp-spam-protecter' ) }
				</Button>
				<Button
					variant="secondary"
					size="compact"
					isBusy={ busy === 'block_sender' }
					disabled={ !! busy || row.status === 'blocked' }
					onClick={ () => act( 'block_sender' ) }
				>
					{ __( 'Block sender', 'wp-spam-protecter' ) }
				</Button>
				<Button
					variant="tertiary"
					size="compact"
					isDestructive
					isBusy={ busy === 'delete' }
					disabled={ !! busy }
					onClick={ () => act( 'delete' ) }
				>
					{ __( 'Delete', 'wp-spam-protecter' ) }
				</Button>
			</td>
		</tr>
	);
}

export default function Log() {
	const [ filters, setFilters ] = useState( {
		context: '',
		verdict: '',
		search: '',
		page: 1,
	} );
	const [ data, setData ] = useState( null );
	const [ error, setError ] = useState( '' );

	useEffect( () => {
		const query = new URLSearchParams( {
			...filters,
			per_page: PER_PAGE,
		} );
		setData( null );
		apiFetch( { path: `/shieldcraft/v1/log?${ query }` } )
			.then( setData )
			.catch( ( e ) => setError( e.message ) );
	}, [ filters ] );

	const filter = ( changes ) =>
		setFilters( ( current ) => ( { ...current, ...changes, page: 1 } ) );
	const pages = data ? Math.max( 1, Math.ceil( data.total / PER_PAGE ) ) : 1;

	const onChange = ( id, result ) =>
		setData( ( current ) => ( {
			total: result.deleted ? current.total - 1 : current.total,
			rows: result.deleted
				? current.rows.filter( ( r ) => r.id !== id )
				: current.rows.map( ( r ) => ( r.id === id ? result : r ) ),
		} ) );

	return (
		<>
			<div className="shieldcraft-admin__filters">
				<Select
					label={ __( 'Form', 'wp-spam-protecter' ) }
					value={ filters.context }
					options={ [
						{
							value: '',
							label: __( 'All forms', 'wp-spam-protecter' ),
						},
						...Object.entries( config.contexts ).map(
							( [ value, label ] ) => ( { value, label } )
						),
					] }
					onChange={ ( context ) => filter( { context } ) }
				/>
				<Select
					label={ __( 'Result', 'wp-spam-protecter' ) }
					value={ filters.verdict }
					options={ [
						{
							value: '',
							label: __( 'All', 'wp-spam-protecter' ),
						},
						{
							value: 'spam',
							label: __( 'Spam', 'wp-spam-protecter' ),
						},
						{
							value: 'suspicious',
							label: __( 'Suspicious', 'wp-spam-protecter' ),
						},
					] }
					onChange={ ( verdict ) => filter( { verdict } ) }
				/>
				<SearchControl
					__nextHasNoMarginBottom
					label={ __(
						'Search names and messages',
						'wp-spam-protecter'
					) }
					value={ filters.search }
					onChange={ ( search ) => filter( { search } ) }
				/>
			</div>
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			{ ! data && ! error && <Spinner /> }
			{ data && ! data.rows.length && (
				<p>{ __( 'Nothing in the log.', 'wp-spam-protecter' ) }</p>
			) }
			{ data && data.rows.length > 0 && (
				<div className="shieldcraft-admin__table">
					<table className="widefat striped">
						<thead>
							<tr>
								<th>{ __( 'When', 'wp-spam-protecter' ) }</th>
								<th>{ __( 'Why', 'wp-spam-protecter' ) }</th>
								<th>
									{ __(
										'Sender and message',
										'wp-spam-protecter'
									) }
								</th>
								<th>
									<span className="screen-reader-text">
										{ __( 'Actions', 'wp-spam-protecter' ) }
									</span>
								</th>
							</tr>
						</thead>
						<tbody>
							{ data.rows.map( ( row ) => (
								<Row
									key={ row.id }
									row={ row }
									onChange={ onChange }
								/>
							) ) }
						</tbody>
					</table>
				</div>
			) }
			{ data && pages > 1 && (
				<div className="shieldcraft-admin__pages">
					<Button
						variant="secondary"
						size="compact"
						disabled={ filters.page <= 1 }
						onClick={ () =>
							setFilters( ( f ) => ( {
								...f,
								page: f.page - 1,
							} ) )
						}
					>
						{ __( 'Newer', 'wp-spam-protecter' ) }
					</Button>
					<span>
						{ sprintf(
							/* translators: 1: page, 2: pages. */
							__( 'Page %1$d of %2$d', 'wp-spam-protecter' ),
							filters.page,
							pages
						) }
					</span>
					<Button
						variant="secondary"
						size="compact"
						disabled={ filters.page >= pages }
						onClick={ () =>
							setFilters( ( f ) => ( {
								...f,
								page: f.page + 1,
							} ) )
						}
					>
						{ __( 'Older', 'wp-spam-protecter' ) }
					</Button>
				</div>
			) }
		</>
	);
}
