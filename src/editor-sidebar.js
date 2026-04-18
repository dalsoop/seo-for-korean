/**
 * Gutenberg sidebar — minimal scaffold.
 *
 * Build with esbuild/Vite/wp-scripts. Output to editor-sidebar.js + editor-sidebar.asset.php.
 * Recommended: `@wordpress/scripts` build (npm install --save-dev @wordpress/scripts).
 *
 * @package SEOForKorean
 */

import { registerPlugin } from '@wordpress/plugins';
import { PluginSidebar, PluginSidebarMoreMenuItem } from '@wordpress/edit-post';
import { PanelBody, PanelRow } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const SIDEBAR_NAME = 'seo-for-korean-sidebar';

const Sidebar = () => (
	<>
		<PluginSidebarMoreMenuItem target={ SIDEBAR_NAME }>
			{ __( 'SEO for Korean', 'seo-for-korean' ) }
		</PluginSidebarMoreMenuItem>

		<PluginSidebar
			name={ SIDEBAR_NAME }
			title={ __( 'SEO for Korean', 'seo-for-korean' ) }
			icon="admin-generic"
		>
			<PanelBody title={ __( 'Overview', 'seo-for-korean' ) } initialOpen>
				<PanelRow>
					{ __( 'Replace this with your module UI.', 'seo-for-korean' ) }
				</PanelRow>
			</PanelBody>
		</PluginSidebar>
	</>
);

registerPlugin( SIDEBAR_NAME, { render: Sidebar, icon: 'admin-generic' } );
