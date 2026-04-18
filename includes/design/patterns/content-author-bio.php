<?php
/**
 * Pattern: content.author-bio
 *
 * Modern author-signature card after article body. LEFT = site logo
 * (circular, accent ring). RIGHT = "WRITTEN BY" eyebrow + dynamic
 * author display-name + dynamic author-info description + site
 * tagline. Falls back to static slots when use_dynamic=false.
 *
 * Slots:
 *   - logo_url     string  Site logo URL. Falls back to WP site icon or empty.
 *   - logo_id      int     Attachment ID (0 = use URL directly).
 *   - logo_alt     string  Alt text for logo. Falls back to site name via get_bloginfo('name').
 *   - author_name  string  Static override.
 *   - author_bio   string  Static override.
 *   - tagline      string  Signature line. Empty default — caller supplies.
 *   - use_dynamic  bool    Default true.
 *   - social       array<platform,url>  Empty default. Platforms: instagram,facebook,x,youtube,tiktok,pinterest.
 *                  Example: ['instagram'=>'https://instagram.com/acme', 'facebook'=>'https://facebook.com/acme'].
 *
 * @package Elementor_MCP
 * @since   1.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'emcp_pattern_content_author_bio' ) ) {
	function emcp_pattern_content_author_bio( array $slots, Elementor_MCP_Token_Resolver $resolver ): array {
		$use_dynamic = isset( $slots['use_dynamic'] ) ? (bool) $slots['use_dynamic'] : true;
		$author_name = (string) ( $slots['author_name'] ?? '' );
		$author_bio  = (string) ( $slots['author_bio'] ?? '' );
		$tagline     = (string) ( $slots['tagline'] ?? '' );
		// Logo: slot override > WP site icon > empty. Keeps pattern site-agnostic.
		$default_logo = function_exists( 'get_site_icon_url' ) ? (string) get_site_icon_url() : '';
		$logo_url     = (string) ( $slots['logo_url'] ?? $default_logo );
		$logo_id      = (int) ( $slots['logo_id'] ?? 0 );
		$logo_alt     = (string) ( $slots['logo_alt'] ?? ( function_exists( 'get_bloginfo' ) ? (string) get_bloginfo( 'name' ) : '' ) );

		// Outer wrapper — full-width background, inner column holds card.
		// Using content_width=full + CSS max-width (via emcp-article-layout
		// parent grid on blog template) keeps card visually aligned with
		// article body above.
		$outer_settings = emcp_array_deep_merge(
			$resolver->spacing( 'section.md' ),
			array(
				'flex_direction'        => 'column',
				'align_items'           => 'center',
				'content_width'         => 'full',
				'_title'                => 'Author bio',
				'background_background' => 'classic',
			),
			$resolver->color( 'background_color', 'surface' )
		);

		// Card — horizontal row desktop, stacks mobile.
		// Width flex-fills (100%) so description reads wider, no fixed px cap.
		$card_settings = emcp_array_deep_merge(
			array(
				'content_width'         => 'full',
				'width'                 => array( 'unit' => '%', 'size' => 100, 'sizes' => array() ),
				'_element_width'        => 'initial',
				'flex_direction'        => 'row',
				'flex_align_items'      => 'center',
				'_title'                => 'Author card',
				'css_classes'           => 'emcp-author-card',
				'padding'               => array( 'unit' => 'px', 'top' => '32', 'right' => '40', 'bottom' => '32', 'left' => '40', 'isLinked' => false ),
				'background_background' => 'classic',
			),
			$resolver->radius( 'card' ),
			$resolver->shadow( 'soft' ),
			$resolver->color( 'background_color', 'surface-alt' ),
			$resolver->gap( 32 )
		);

		// LEFT column — site logo.
		$logo_col = array(
			'type'     => 'container',
			'settings' => array(
				'content_width'  => 'full',
				'flex_direction' => 'column',
				'_title'         => 'Logo col',
				'width'          => array( 'unit' => 'px', 'size' => 110, 'sizes' => array() ),
				'css_classes'    => 'emcp-author-logo-col',
			),
			'children' => array(
				array(
					'type'        => 'widget',
					'widget_type' => 'image',
					'settings'    => emcp_array_deep_merge(
						array(
							'image'        => array(
								'url' => esc_url_raw( $logo_url ),
								'id'  => $logo_id,
								'alt' => $logo_alt,
							),
							'image_size'   => 'medium',
							'width'        => array( 'unit' => 'px', 'size' => 96, 'sizes' => array() ),
							'align'        => 'center',
							'_title'       => 'Site logo',
							'_css_classes' => 'emcp-author-avatar',
						),
						$resolver->radius( 'round' )
					),
				),
			),
		);

		// RIGHT column — eyebrow + name + description + tagline.
		$text_children = array();

		$text_children[] = array(
			'type'        => 'widget',
			'widget_type' => 'heading',
			'settings'    => emcp_array_deep_merge(
				array(
					'title'                     => 'WRITTEN BY',
					'header_size'               => 'div',
					'align'                     => 'left',
					'_title'                    => 'Eyebrow',
					'_css_classes'              => 'emcp-author-eyebrow',
					'typography_text_transform' => 'uppercase',
					'typography_letter_spacing' => array( 'unit' => 'em', 'size' => 0.14, 'sizes' => array() ),
				),
				$resolver->typography( 'caption' ),
				$resolver->color( 'title_color', 'accent' )
			),
		);

		$name_settings = emcp_array_deep_merge(
			array(
				'title'        => $use_dynamic ? 'Author' : $author_name,
				'header_size'  => 'h3',
				'align'        => 'left',
				'_title'       => 'Author name',
				'_css_classes' => 'emcp-author-name',
			),
			$resolver->typography( 'heading-md' ),
			$resolver->color( 'title_color', 'text' )
		);
		if ( $use_dynamic ) {
			$name_settings['__dynamic__'] = array(
				'title' => '[elementor-tag id="emcpan1" name="author-name" settings="%7B%7D"]',
			);
		}
		$text_children[] = array(
			'type'        => 'widget',
			'widget_type' => 'heading',
			'settings'    => $name_settings,
		);

		// Author description — dynamic author-info tag outputs the WP
		// user's biographical info. When empty, tag returns empty string.
		if ( $use_dynamic ) {
			$text_children[] = array(
				'type'        => 'widget',
				'widget_type' => 'text-editor',
				'settings'    => emcp_array_deep_merge(
					array(
						'editor'       => '',
						'_title'       => 'Author description',
						'_css_classes' => 'emcp-author-desc',
						'__dynamic__'  => array(
							'editor' => '[elementor-tag id="emcpadesc" name="author-info" settings="%7B%22author_info%22%3A%22description%22%7D"]',
						),
					),
					$resolver->typography( 'body-md' ),
					$resolver->color( 'color', 'text-muted' )
				),
			);
		} elseif ( $author_bio !== '' ) {
			$text_children[] = array(
				'type'        => 'widget',
				'widget_type' => 'text-editor',
				'settings'    => emcp_array_deep_merge(
					array(
						'editor'       => '<p>' . esc_html( $author_bio ) . '</p>',
						'_title'       => 'Author description',
						'_css_classes' => 'emcp-author-desc',
					),
					$resolver->typography( 'body-md' ),
					$resolver->color( 'color', 'text-muted' )
				),
			);
		}

		// Tagline — site-level signature line.
		if ( $tagline !== '' ) {
			$text_children[] = array(
				'type'        => 'widget',
				'widget_type' => 'heading',
				'settings'    => emcp_array_deep_merge(
					array(
						'title'        => $tagline,
						'header_size'  => 'div',
						'align'        => 'left',
						'_title'       => 'Author tagline',
						'_css_classes' => 'emcp-author-tagline',
					),
					$resolver->typography( 'caption' ),
					$resolver->color( 'title_color', 'text-muted' )
				),
			);
		}

		// Social icons row — Instagram / Facebook / X / YouTube / TikTok / Pinterest.
		// Default: empty (no icons rendered). Caller supplies URLs via `social` slot.
		$social_defaults = array(
			'instagram' => '',
			'facebook'  => '',
			'x'         => '',
			'youtube'   => '',
			'tiktok'    => '',
			'pinterest' => '',
		);
		$social = is_array( $slots['social'] ?? null )
			? array_merge( $social_defaults, $slots['social'] )
			: $social_defaults;

		$icon_map = array(
			'instagram' => array( 'value' => 'fab fa-instagram', 'library' => 'fa-brands' ),
			'facebook'  => array( 'value' => 'fab fa-facebook-f', 'library' => 'fa-brands' ),
			'x'         => array( 'value' => 'fab fa-x-twitter', 'library' => 'fa-brands' ),
			'youtube'   => array( 'value' => 'fab fa-youtube', 'library' => 'fa-brands' ),
			'tiktok'    => array( 'value' => 'fab fa-tiktok', 'library' => 'fa-brands' ),
			'pinterest' => array( 'value' => 'fab fa-pinterest-p', 'library' => 'fa-brands' ),
		);

		$social_list = array();
		foreach ( array( 'instagram', 'facebook', 'x', 'youtube', 'tiktok', 'pinterest' ) as $platform ) {
			$url = (string) ( $social[ $platform ] ?? '' );
			if ( $url === '' ) {
				continue;
			}
			$social_list[] = array(
				'_id'   => substr( md5( $platform ), 0, 7 ),
				'social_icon' => $icon_map[ $platform ],
				'link'  => array(
					'url'         => esc_url_raw( $url ),
					'is_external' => 'on',
					'nofollow'    => '',
				),
				'label' => ucfirst( $platform ),
			);
		}

		if ( ! empty( $social_list ) ) {
			$text_children[] = array(
				'type'        => 'widget',
				'widget_type' => 'social-icons',
				'settings'    => array(
					'_title'        => 'Author socials',
					'_css_classes'  => 'emcp-author-socials',
					'social_icon_list' => $social_list,
					'shape'         => 'circle',
					'columns'       => '0',
					'align'         => 'left',
					'icon_size'     => array( 'unit' => 'px', 'size' => 14, 'sizes' => array() ),
					'icon_padding'  => array( 'unit' => 'em', 'size' => 0.6, 'sizes' => array() ),
					'icon_spacing'  => array( 'unit' => 'px', 'size' => 8, 'sizes' => array() ),
					'hover_animation' => 'grow',
				),
			);
		}

		$text_col = array(
			'type'     => 'container',
			'settings' => emcp_array_deep_merge(
				array(
					'content_width'  => 'full',
					'flex_direction' => 'column',
					'_title'         => 'Author text col',
					'css_classes'    => 'emcp-author-text-col',
				),
				$resolver->gap( 6 )
			),
			'children' => $text_children,
		);

		// Inner content column — caps card width at 1100px and centers,
		// matching the blog layout's wider article column.
		$inner_col = array(
			'type'     => 'container',
			'settings' => array(
				'content_width'  => 'full',
				'flex_direction' => 'column',
				'_title'         => 'Author bio content column',
				'width'          => array( 'unit' => 'px', 'size' => 1100, 'sizes' => array() ),
				'css_classes'    => 'emcp-author-inner',
			),
			'children' => array(
				array(
					'type'     => 'container',
					'settings' => $card_settings,
					'children' => array( $logo_col, $text_col ),
				),
			),
		);

		return array(
			'type'     => 'container',
			'settings' => $outer_settings,
			'children' => array( $inner_col ),
		);
	}
}

if ( ! function_exists( 'emcp_pattern_content_author_bio_meta' ) ) {
	function emcp_pattern_content_author_bio_meta(): array {
		return array(
			'category'    => 'content',
			'description' => 'Modern author-signature card. Site logo LEFT (accent ring), WRITTEN BY eyebrow + dynamic author name + author-info description + site tagline on RIGHT.',
			'slots'       => array(
				'logo_url'    => 'string URL (site logo — falls back to get_site_icon_url())',
				'logo_id'     => 'int attachment ID (0 = use URL)',
				'logo_alt'    => 'string alt text (falls back to get_bloginfo("name"))',
				'author_name' => 'string (use_dynamic=false only)',
				'author_bio'  => 'string (use_dynamic=false only)',
				'tagline'     => 'string (signature line — empty default)',
				'use_dynamic' => 'bool (default true)',
				'social'      => 'array<platform,url> — platforms: instagram,facebook,x,youtube,tiktok,pinterest. Empty default = no icons. Example: [\"instagram\"=>\"https://instagram.com/acme\"].',
			),
		);
	}
}
