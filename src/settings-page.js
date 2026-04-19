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
