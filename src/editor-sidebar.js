/**
 * SEO for Korean — Gutenberg sidebar.
 *
 * Reads post title/content/slug from the editor store, lets the user enter a
 * focus keyword + meta description (persisted as post meta), and debounces a
 * call to /wp-json/seo-for-korean/v1/analyze for a live SEO score.
 */

import { registerPlugin } from '@wordpress/plugins';
import { PluginSidebar, PluginSidebarMoreMenuItem } from '@wordpress/edit-post';
import {
	PanelBody,
	PanelRow,
	TextControl,
	TextareaControl,
	Spinner,
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { useEntityProp } from '@wordpress/core-data';
import { useEffect, useState, useMemo } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { __, sprintf } from '@wordpress/i18n';

const SIDEBAR_NAME = 'seo-for-korean-sidebar';
const DEBOUNCE_MS = 600;

const GRADE_COLOR = {
	great: '#16a34a',
	good: '#65a30d',
	needs_work: '#d97706',
	poor: '#dc2626',
};

const STATUS_ICON = {
	pass: '✓',
	warning: '⚠',
	fail: '✗',
	na: '–',
};

const STATUS_COLOR = {
	pass: '#16a34a',
	warning: '#d97706',
	fail: '#dc2626',
	na: '#9ca3af',
};

const ScoreCircle = ( { score, grade } ) => {
	const color = GRADE_COLOR[ grade ] || GRADE_COLOR.poor;
	return (
		<div
			style={ {
				width: 80,
				height: 80,
				borderRadius: '50%',
				border: `5px solid ${ color }`,
				display: 'flex',
				alignItems: 'center',
				justifyContent: 'center',
				fontSize: 24,
				fontWeight: 700,
				color,
				margin: '0 auto',
			} }
			aria-label={ sprintf( __( 'SEO score %d', 'seo-for-korean' ), score ) }
		>
			{ score }
		</div>
	);
};

const CheckRow = ( { check } ) => {
	if ( check.status === 'na' ) {
		return null;
	}
	return (
		<li
			style={ {
				display: 'flex',
				alignItems: 'flex-start',
				gap: 8,
				padding: '6px 0',
				borderBottom: '1px solid #f1f5f9',
				margin: 0,
			} }
		>
			<span
				style={ {
					color: STATUS_COLOR[ check.status ],
					fontWeight: 700,
					minWidth: 16,
					textAlign: 'center',
				} }
				aria-label={ check.status }
			>
				{ STATUS_ICON[ check.status ] }
			</span>
			<div style={ { flex: 1 } }>
				<div style={ { fontWeight: 600, fontSize: 12 } }>
					{ check.label }
				</div>
				<div style={ { fontSize: 12, color: '#475569' } }>
					{ check.message }
				</div>
			</div>
		</li>
	);
};

const Sidebar = () => {
	const postType = useSelect(
		( select ) => select( 'core/editor' ).getCurrentPostType(),
		[]
	);

	const { title, content, slug } = useSelect( ( select ) => {
		const editor = select( 'core/editor' );
		return {
			title: editor.getEditedPostAttribute( 'title' ) || '',
			content: editor.getEditedPostAttribute( 'content' ) || '',
			slug: editor.getEditedPostAttribute( 'slug' ) || '',
		};
	}, [] );

	const [ meta, setMeta ] = useEntityProp( 'postType', postType, 'meta' );
	const focusKeyword = meta?.sfk_focus_keyword || '';
	const metaDescription = meta?.sfk_meta_description || '';

	const updateMeta = ( key, value ) =>
		setMeta( { ...( meta || {} ), [ key ]: value } );

	const [ result, setResult ] = useState( null );
	const [ loading, setLoading ] = useState( false );
	const [ error, setError ] = useState( null );

	const inputs = useMemo(
		() => ( {
			title,
			content,
			slug,
			focus_keyword: focusKeyword,
			meta_description: metaDescription,
		} ),
		[ title, content, slug, focusKeyword, metaDescription ]
	);

	useEffect( () => {
		const handle = setTimeout( () => {
			setLoading( true );
			setError( null );
			apiFetch( {
				path: '/seo-for-korean/v1/analyze',
				method: 'POST',
				data: inputs,
			} )
				.then( ( res ) => {
					setResult( res );
					setLoading( false );
				} )
				.catch( ( err ) => {
					setError( err?.message || __( 'Analysis failed.', 'seo-for-korean' ) );
					setLoading( false );
				} );
		}, DEBOUNCE_MS );
		return () => clearTimeout( handle );
	}, [ inputs ] );

	return (
		<>
			<PluginSidebarMoreMenuItem target={ SIDEBAR_NAME }>
				{ __( 'SEO for Korean', 'seo-for-korean' ) }
			</PluginSidebarMoreMenuItem>

			<PluginSidebar
				name={ SIDEBAR_NAME }
				title={ __( 'SEO for Korean', 'seo-for-korean' ) }
				icon="chart-line"
			>
				<PanelBody
					title={ __( '점수', 'seo-for-korean' ) }
					initialOpen
				>
					<PanelRow>
						<div style={ { width: '100%', textAlign: 'center' } }>
							{ loading && ! result && <Spinner /> }
							{ result && (
								<ScoreCircle
									score={ result.score }
									grade={ result.grade }
								/>
							) }
							{ error && (
								<div style={ { color: '#dc2626', fontSize: 12 } }>
									{ error }
								</div>
							) }
						</div>
					</PanelRow>
				</PanelBody>

				<PanelBody
					title={ __( '키워드 & 메타', 'seo-for-korean' ) }
					initialOpen
				>
					<TextControl
						label={ __( '포커스 키워드', 'seo-for-korean' ) }
						help={ __( '이 글의 핵심 검색어 한 개', 'seo-for-korean' ) }
						value={ focusKeyword }
						onChange={ ( v ) => updateMeta( 'sfk_focus_keyword', v ) }
					/>
					<TextareaControl
						label={ __( '메타 설명', 'seo-for-korean' ) }
						help={ __( '검색 결과에 노출되는 80~155자 요약', 'seo-for-korean' ) }
						value={ metaDescription }
						onChange={ ( v ) =>
							updateMeta( 'sfk_meta_description', v )
						}
						rows={ 3 }
					/>
				</PanelBody>

				<PanelBody
					title={ __( '체크 리스트', 'seo-for-korean' ) }
					initialOpen
				>
					{ ! result && ! error && (
						<PanelRow>
							<Spinner />
						</PanelRow>
					) }
					{ result && (
						<ul style={ { margin: 0, padding: 0, listStyle: 'none' } }>
							{ result.checks.map( ( check ) => (
								<CheckRow key={ check.id } check={ check } />
							) ) }
						</ul>
					) }
				</PanelBody>
			</PluginSidebar>
		</>
	);
};

registerPlugin( SIDEBAR_NAME, { render: Sidebar, icon: 'chart-line' } );
