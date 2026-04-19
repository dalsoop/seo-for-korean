/**
 * Settings page — React app for the WordPress admin.
 *
 * Mounted at #sfk-settings-root on the Settings → SEO for Korean page.
 * Communicates with /seo-for-korean/v1/settings (GET/PUT). The whole
 * settings object is fetched on mount and re-sent on save — simple,
 * no diffing logic needed for V1.
 */

import { createRoot, useState, useEffect } from '@wordpress/element';
import {
	TabPanel,
	ToggleControl,
	TextControl,
	TextareaControl,
	SelectControl,
	Button,
	Notice,
	Spinner,
	Card,
	CardBody,
	CardHeader,
	BaseControl,
	__experimentalDivider as Divider,
} from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';

const MODULES = [
	{ id: 'content-analyzer', label: 'Content Analyzer', desc: '편집기 사이드바의 라이브 SEO 점수.' },
	{ id: 'head-meta', label: 'Head Meta', desc: 'description, OG, Twitter, canonical 자동 출력.' },
	{ id: 'schema', label: 'Schema', desc: '11종 JSON-LD 자동 (Article/FAQ/Recipe/Event 등).' },
	{ id: 'sitemap', label: 'Sitemap', desc: '/sitemap.xml 인덱스 + posts/pages/categories/tags.' },
	{ id: 'templates', label: 'Templates', desc: '제목/메타 변수 템플릿 (%title%, %sitename% 등).' },
	{ id: 'images', label: 'Images', desc: '이미지 alt 자동 채움.' },
	{ id: 'redirections', label: 'Redirections', desc: '패턴 기반 URL 리다이렉트.' },
	{ id: 'monitor-404', label: '404 Monitor', desc: '404 발생 URL 자동 로깅.' },
	{ id: 'naver-meta', label: 'Naver Meta', desc: '네이버 인증 메타 + 카카오 OG 힌트.' },
	{ id: 'naver-sitemap', label: 'Naver Sitemap', desc: '/sitemap-naver.xml 별칭 (legacy).' },
];

const TEMPLATE_CONTEXTS = [
	{ id: 'home', label: '홈' },
	{ id: 'single', label: '단일 글' },
	{ id: 'page', label: '페이지' },
	{ id: 'category', label: '카테고리' },
	{ id: 'tag', label: '태그' },
	{ id: 'search', label: '검색 결과' },
	{ id: 'notfound', label: '404' },
];

const VARIABLES_HINT =
	'사용 가능 변수: %title%, %sitename%, %sitedesc%, %separator%, %excerpt%, %category%, %tag%, %date%, %modified%, %author%, %focuskw%, %searchphrase%';

const App = () => {
	const [ settings, setSettings ] = useState( null );
	const [ loading, setLoading ] = useState( true );
	const [ saving, setSaving ] = useState( false );
	const [ notice, setNotice ] = useState( null );

	useEffect( () => {
		apiFetch( { path: '/seo-for-korean/v1/settings' } )
			.then( ( res ) => {
				setSettings( res || {} );
				setLoading( false );
			} )
			.catch( ( err ) => {
				setNotice( { status: 'error', text: err?.message || 'Load failed' } );
				setLoading( false );
			} );
	}, [] );

	const save = () => {
		setSaving( true );
		apiFetch( {
			path: '/seo-for-korean/v1/settings',
			method: 'PUT',
			data: settings,
		} )
			.then( () => {
				setSaving( false );
				setNotice( { status: 'success', text: __( '저장되었습니다.', 'seo-for-korean' ) } );
			} )
			.catch( ( err ) => {
				setSaving( false );
				setNotice( { status: 'error', text: err?.message || 'Save failed' } );
			} );
	};

	const update = ( path, value ) => {
		const keys = path.split( '.' );
		setSettings( ( prev ) => {
			const next = { ...( prev || {} ) };
			let ref = next;
			for ( let i = 0; i < keys.length - 1; i++ ) {
				ref[ keys[ i ] ] = { ...( ref[ keys[ i ] ] || {} ) };
				ref = ref[ keys[ i ] ];
			}
			ref[ keys[ keys.length - 1 ] ] = value;
			return next;
		} );
	};

	if ( loading ) {
		return <Spinner />;
	}

	return (
		<Card style={ { marginTop: 20, maxWidth: 960 } }>
			<CardHeader>
				<h1 style={ { margin: 0, fontSize: 18 } }>SEO for Korean</h1>
			</CardHeader>
			<CardBody>
				{ notice && (
					<Notice
						status={ notice.status }
						onRemove={ () => setNotice( null ) }
						isDismissible
					>
						{ notice.text }
					</Notice>
				) }

				<TabPanel
					tabs={ [
						{ name: 'modules', title: __( 'Modules', 'seo-for-korean' ) },
						{ name: 'templates', title: __( 'Templates', 'seo-for-korean' ) },
						{ name: 'redirections', title: __( 'Redirections', 'seo-for-korean' ) },
						{ name: 'log404', title: __( '404 Log', 'seo-for-korean' ) },
						{ name: 'naver', title: __( 'Naver', 'seo-for-korean' ) },
					] }
				>
					{ ( tab ) => {
						if ( tab.name === 'modules' ) {
							return <ModulesTab settings={ settings } update={ update } />;
						}
						if ( tab.name === 'templates' ) {
							return <TemplatesTab settings={ settings } update={ update } />;
						}
						if ( tab.name === 'redirections' ) {
							return <RedirectionsTab settings={ settings } update={ update } />;
						}
						if ( tab.name === 'log404' ) {
							return <Log404Tab update={ update } settings={ settings } />;
						}
						if ( tab.name === 'naver' ) {
							return <NaverTab settings={ settings } update={ update } />;
						}
						return null;
					} }
				</TabPanel>

				<Divider />

				<div style={ { marginTop: 16, textAlign: 'right' } }>
					<Button variant="primary" onClick={ save } isBusy={ saving } disabled={ saving }>
						{ __( '저장', 'seo-for-korean' ) }
					</Button>
				</div>
			</CardBody>
		</Card>
	);
};

const ModulesTab = ( { settings, update } ) => {
	const enabled = settings.enabled_modules || [];
	return (
		<div style={ { padding: '8px 0' } }>
			<p style={ { color: '#475569' } }>
				{ __( '활성화된 모듈만 동작합니다. 비활성화하면 해당 기능은 사이트에 출력되지 않습니다.', 'seo-for-korean' ) }
			</p>
			{ MODULES.map( ( m ) => (
				<div key={ m.id } style={ { padding: '6px 0', borderBottom: '1px solid #f1f5f9' } }>
					<ToggleControl
						label={ m.label }
						help={ m.desc }
						checked={ enabled.includes( m.id ) }
						onChange={ ( checked ) => {
							const next = checked
								? [ ...enabled, m.id ]
								: enabled.filter( ( x ) => x !== m.id );
							update( 'enabled_modules', next );
						} }
					/>
				</div>
			) ) }
		</div>
	);
};

const TemplatesTab = ( { settings, update } ) => {
	const templates = settings.templates || {};
	return (
		<div style={ { padding: '8px 0' } }>
			<p style={ { color: '#475569', fontSize: 12 } }>{ VARIABLES_HINT }</p>
			{ TEMPLATE_CONTEXTS.map( ( ctx ) => (
				<BaseControl
					__nextHasNoMarginBottom
					key={ ctx.id }
					label={ ctx.label }
					id={ `tpl-${ ctx.id }` }
				>
					<div style={ { marginBottom: 16 } }>
						<TextControl
							label={ __( '제목', 'seo-for-korean' ) }
							value={ ( templates[ ctx.id ] || {} ).title || '' }
							onChange={ ( v ) => update( `templates.${ ctx.id }.title`, v ) }
						/>
						<TextareaControl
							label={ __( '메타 설명', 'seo-for-korean' ) }
							value={ ( templates[ ctx.id ] || {} ).description || '' }
							onChange={ ( v ) => update( `templates.${ ctx.id }.description`, v ) }
							rows={ 2 }
						/>
					</div>
				</BaseControl>
			) ) }
		</div>
	);
};

const RedirectionsTab = ( { settings, update } ) => {
	const rules = Array.isArray( settings.redirects ) ? settings.redirects : [];
	const [ draft, setDraft ] = useState( {
		from: '',
		to: '',
		type: 'exact',
		status: 301,
		enabled: true,
	} );

	const addRule = () => {
		if ( ! draft.from || ! draft.to ) {
			return;
		}
		update( 'redirects', [ ...rules, { ...draft } ] );
		setDraft( { from: '', to: '', type: 'exact', status: 301, enabled: true } );
	};

	const removeRule = ( index ) => {
		update( 'redirects', rules.filter( ( _, i ) => i !== index ) );
	};

	const toggleRule = ( index, enabled ) => {
		update(
			'redirects',
			rules.map( ( r, i ) => ( i === index ? { ...r, enabled } : r ) )
		);
	};

	return (
		<div style={ { padding: '8px 0' } }>
			<p style={ { color: '#475569', fontSize: 12 } }>
				{ __( '패턴 매칭 기반 URL 리다이렉트. exact (정확히 일치) / prefix (앞부분 일치, 뒷부분 보존) / regex (preg_replace 스타일).', 'seo-for-korean' ) }
			</p>

			<div style={ { display: 'grid', gridTemplateColumns: '2fr 2fr 1fr 1fr auto', gap: 8, alignItems: 'flex-end', marginBottom: 12 } }>
				<TextControl
					label={ __( 'From', 'seo-for-korean' ) }
					value={ draft.from }
					onChange={ ( v ) => setDraft( { ...draft, from: v } ) }
					placeholder="/old-page"
				/>
				<TextControl
					label={ __( 'To', 'seo-for-korean' ) }
					value={ draft.to }
					onChange={ ( v ) => setDraft( { ...draft, to: v } ) }
					placeholder="/new-page"
				/>
				<SelectControl
					label={ __( 'Type', 'seo-for-korean' ) }
					value={ draft.type }
					options={ [
						{ value: 'exact', label: 'exact' },
						{ value: 'prefix', label: 'prefix' },
						{ value: 'regex', label: 'regex' },
					] }
					onChange={ ( v ) => setDraft( { ...draft, type: v } ) }
				/>
				<SelectControl
					label={ __( 'Status', 'seo-for-korean' ) }
					value={ String( draft.status ) }
					options={ [
						{ value: '301', label: '301' },
						{ value: '302', label: '302' },
						{ value: '307', label: '307' },
						{ value: '308', label: '308' },
						{ value: '410', label: '410 (Gone)' },
					] }
					onChange={ ( v ) => setDraft( { ...draft, status: parseInt( v, 10 ) } ) }
				/>
				<Button variant="secondary" onClick={ addRule } disabled={ ! draft.from || ! draft.to }>
					{ __( '추가', 'seo-for-korean' ) }
				</Button>
			</div>

			{ rules.length === 0 && (
				<p style={ { color: '#94a3b8' } }>{ __( '아직 리다이렉트 규칙이 없습니다.', 'seo-for-korean' ) }</p>
			) }

			{ rules.length > 0 && (
				<table style={ { width: '100%', borderCollapse: 'collapse', fontSize: 13 } }>
					<thead>
						<tr style={ { borderBottom: '2px solid #e2e8f0', textAlign: 'left' } }>
							<th style={ { padding: 6 } }>{ __( '활성', 'seo-for-korean' ) }</th>
							<th style={ { padding: 6 } }>From</th>
							<th style={ { padding: 6 } }>To</th>
							<th style={ { padding: 6 } }>Type</th>
							<th style={ { padding: 6 } }>Status</th>
							<th style={ { padding: 6 } }></th>
						</tr>
					</thead>
					<tbody>
						{ rules.map( ( r, i ) => (
							<tr key={ i } style={ { borderBottom: '1px solid #f1f5f9' } }>
								<td style={ { padding: 6 } }>
									<ToggleControl
										__nextHasNoMarginBottom
										checked={ r.enabled !== false }
										onChange={ ( c ) => toggleRule( i, c ) }
									/>
								</td>
								<td style={ { padding: 6, fontFamily: 'monospace' } }>{ r.from }</td>
								<td style={ { padding: 6, fontFamily: 'monospace' } }>{ r.to }</td>
								<td style={ { padding: 6 } }>{ r.type }</td>
								<td style={ { padding: 6 } }>{ r.status || 301 }</td>
								<td style={ { padding: 6 } }>
									<Button variant="link" isDestructive onClick={ () => removeRule( i ) }>
										{ __( '삭제', 'seo-for-korean' ) }
									</Button>
								</td>
							</tr>
						) ) }
					</tbody>
				</table>
			) }
		</div>
	);
};

const Log404Tab = ( { settings, update } ) => {
	const [ log, setLog ] = useState( null );
	const [ loading, setLoading ] = useState( true );

	const reload = () => {
		setLoading( true );
		apiFetch( { path: '/seo-for-korean/v1/404-log' } )
			.then( ( res ) => {
				setLog( Array.isArray( res ) ? res : [] );
				setLoading( false );
			} )
			.catch( () => {
				setLog( [] );
				setLoading( false );
			} );
	};

	useEffect( reload, [] );

	const clear = () => {
		if ( ! window.confirm( __( '404 로그를 모두 지우시겠습니까?', 'seo-for-korean' ) ) ) {
			return;
		}
		apiFetch( { path: '/seo-for-korean/v1/404-log', method: 'DELETE' } ).then( reload );
	};

	const promoteToRedirect = ( path ) => {
		const rules = Array.isArray( settings.redirects ) ? settings.redirects : [];
		update( 'redirects', [
			...rules,
			{ from: path, to: '/', type: 'exact', status: 301, enabled: true },
		] );
		window.alert( __( 'Redirections 탭에서 To 값을 채운 뒤 저장하세요.', 'seo-for-korean' ) );
	};

	if ( loading ) {
		return <Spinner />;
	}

	return (
		<div style={ { padding: '8px 0' } }>
			<div style={ { display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 8 } }>
				<p style={ { color: '#475569', fontSize: 12, margin: 0 } }>
					{ __( '404 발생 URL 상위 50건. 항목을 리다이렉트로 승격할 수 있습니다.', 'seo-for-korean' ) }
				</p>
				<Button variant="secondary" onClick={ reload } size="small">
					{ __( '새로고침', 'seo-for-korean' ) }
				</Button>
			</div>

			{ log.length === 0 && (
				<p style={ { color: '#94a3b8' } }>{ __( '404 로그가 비어 있습니다.', 'seo-for-korean' ) }</p>
			) }

			{ log.length > 0 && (
				<>
					<table style={ { width: '100%', borderCollapse: 'collapse', fontSize: 13 } }>
						<thead>
							<tr style={ { borderBottom: '2px solid #e2e8f0', textAlign: 'left' } }>
								<th style={ { padding: 6 } }>Path</th>
								<th style={ { padding: 6 } }>Hits</th>
								<th style={ { padding: 6 } }>Last</th>
								<th style={ { padding: 6 } }>Referer</th>
								<th style={ { padding: 6 } }></th>
							</tr>
						</thead>
						<tbody>
							{ log.map( ( e, i ) => (
								<tr key={ i } style={ { borderBottom: '1px solid #f1f5f9' } }>
									<td style={ { padding: 6, fontFamily: 'monospace', maxWidth: 280, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' } }>
										{ e.path }
									</td>
									<td style={ { padding: 6 } }>{ e.count }</td>
									<td style={ { padding: 6 } }>
										{ e.last ? new Date( e.last * 1000 ).toLocaleString() : '-' }
									</td>
									<td style={ { padding: 6, maxWidth: 200, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap', fontSize: 11, color: '#64748b' } }>
										{ e.referer || '-' }
									</td>
									<td style={ { padding: 6 } }>
										<Button variant="link" onClick={ () => promoteToRedirect( e.path ) }>
											{ __( '리다이렉트', 'seo-for-korean' ) }
										</Button>
									</td>
								</tr>
							) ) }
						</tbody>
					</table>
					<div style={ { marginTop: 12, textAlign: 'right' } }>
						<Button variant="link" isDestructive onClick={ clear }>
							{ __( '로그 비우기', 'seo-for-korean' ) }
						</Button>
					</div>
				</>
			) }
		</div>
	);
};

const NaverTab = ( { settings, update } ) => {
	const naver = settings.naver_meta || {};
	return (
		<div style={ { padding: '8px 0' } }>
			<TextControl
				label={ __( '네이버 사이트 인증 코드', 'seo-for-korean' ) }
				help={ __( '네이버 서치어드바이저에서 발급받은 메타 태그의 content 값. <head>에 자동으로 박힙니다.', 'seo-for-korean' ) }
				value={ naver.site_verification || '' }
				onChange={ ( v ) => update( 'naver_meta.site_verification', v ) }
				placeholder="abcd1234..."
			/>
			<p style={ { color: '#475569', fontSize: 12, marginTop: 12 } }>
				{ __( '네이버 사이트맵 URL: ', 'seo-for-korean' ) }
				<code>{ window.location.origin }/sitemap.xml</code>
				{ ' / ' }
				<code>{ window.location.origin }/sitemap-naver.xml</code>
			</p>
		</div>
	);
};

const root = document.getElementById( 'sfk-settings-root' );
if ( root ) {
	createRoot( root ).render( <App /> );
}
