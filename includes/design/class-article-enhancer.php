<?php
/**
 * Article Enhancer — post-processes `the_content` for blog single-post
 * pages so editorial formatting (dropcap, anchored headings, callout
 * shortcodes, stat chips) ships without author effort.
 *
 * Scope: active only when the current single-post context matches an
 * EMCP-generated template (tagged with `_emcp_generated` postmeta).
 * Everything is additive — existing Gutenberg/Elementor rendering
 * pipelines unchanged.
 *
 * @package Elementor_MCP
 * @since   1.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wraps post content in `.emcp-article`, injects dropcap first-para
 * marker, anchor-links H2/H3s, registers callout + stat shortcodes,
 * enqueues scoped article CSS.
 *
 * @since 1.5.0
 */
class Elementor_MCP_Article_Enhancer {

	/**
	 * Singleton.
	 */
	private static $instance = null;

	/**
	 * Whether CSS enqueued this request.
	 */
	private $enqueued = false;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function register_hooks(): void {
		add_shortcode( 'emcp_info', array( $this, 'shortcode_info' ) );
		add_shortcode( 'emcp_warn', array( $this, 'shortcode_warn' ) );
		add_shortcode( 'emcp_success', array( $this, 'shortcode_success' ) );
		add_shortcode( 'emcp_tip', array( $this, 'shortcode_tip' ) );
		add_shortcode( 'emcp_stat', array( $this, 'shortcode_stat' ) );
		add_shortcode( 'emcp_pull', array( $this, 'shortcode_pull_quote' ) );

		// Priority 12 runs after wpautop(10) + do_shortcode(11).
		add_filter( 'the_content', array( $this, 'enhance_content' ), 12 );

		// Editor preview parity — Elementor editor iframe fires these
		// actions (NOT wp_enqueue_scripts) when rendering the live
		// preview inside the builder. Without this, frontend styles
		// (card hover, brand colors, table design, social icons etc.)
		// are invisible inside the editor, causing false "my changes
		// aren't showing" confusion.
		add_action( 'elementor/preview/enqueue_styles', array( $this, 'enqueue_styles_unconditional' ) );
		add_action( 'elementor/editor/after_enqueue_styles', array( $this, 'enqueue_styles_unconditional' ) );

		// FAQ page CSS — frontend enqueue (detects .emcp-faqpage wrapper).
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_faq_page_styles' ) );
	}

	/**
	 * Editor-preview variant — bypasses `should_enhance()` because
	 * `is_singular()` is false during template edit, yet designer
	 * still expects final-look parity. Also loads faq-page.css so
	 * FAQ-pattern pages render correctly inside the editor iframe.
	 */
	public function enqueue_styles_unconditional(): void {
		wp_enqueue_style(
			'emcp-article',
			ELEMENTOR_MCP_URL . 'includes/design/css/article-styles.css',
			array(),
			ELEMENTOR_MCP_VERSION
		);
		wp_enqueue_style(
			'emcp-faq-page',
			ELEMENTOR_MCP_URL . 'includes/design/css/faq-page.css',
			array(),
			ELEMENTOR_MCP_VERSION
		);
	}

	/**
	 * Enqueues FAQ page CSS on the frontend when the current singular
	 * page's `_elementor_data` postmeta contains the `emcp-faqpage`
	 * wrapper class. Class-based detection survives slug changes
	 * and works for any page rendered via the `faq.page-full` pattern.
	 */
	public function enqueue_faq_page_styles(): void {
		if ( is_admin() || ! is_singular() ) {
			return;
		}
		$post_id = get_queried_object_id();
		if ( ! $post_id ) {
			return;
		}
		$data = get_post_meta( $post_id, '_elementor_data', true );
		if ( ! is_string( $data ) || '' === $data ) {
			return;
		}
		if ( false === strpos( $data, 'emcp-faqpage' ) ) {
			return;
		}
		wp_enqueue_style(
			'emcp-faq-page',
			ELEMENTOR_MCP_URL . 'includes/design/css/faq-page.css',
			array(),
			ELEMENTOR_MCP_VERSION
		);
	}

	/**
	 * Wraps content + enriches markup when applicable.
	 *
	 * v2: prepends reading-progress bar + meta strip (read time) +
	 * auto-TOC built from H2 list. Parses ==keyword== marks and
	 * anchor-links H2/H3.
	 */
	public function enhance_content( $content ) {
		if ( is_admin() || ! is_singular() ) {
			return $content;
		}
		if ( ! $this->should_enhance() ) {
			return $content;
		}
		if ( ! is_string( $content ) || '' === trim( $content ) ) {
			return $content;
		}

		$this->enqueue_styles();

		// Anchor-link H2/H3 (adds id + # anchor link).
		$content = preg_replace_callback(
			'#<h([23])([^>]*)>(.*?)</h\1>#is',
			array( $this, 'callback_heading_anchor' ),
			$content
		);

		// Keyword mark parser: ==word== → <mark>word</mark>.
		$content = preg_replace(
			'/==([^=\n]+?)==/u',
			'<mark class="emcp-mark">$1</mark>',
			$content
		);

		// First <p> gets data-first for dropcap.
		$content = preg_replace(
			'#<p(\s[^>]*)?>#i',
			'<p$1 data-first="true">',
			$content,
			1
		);

		// Prefix — progress bar + meta strip + TOC.
		$prefix  = $this->render_progress_bar();
		$prefix .= $this->build_meta_strip( $content );
		$prefix .= $this->build_toc( $content );

		$palette_vars = $this->palette_css_vars();
		$style_attr   = $palette_vars ? ' style="' . esc_attr( $palette_vars ) . '"' : '';
		return '<div class="emcp-article"' . $style_attr . '>' . $prefix . $content . '</div>';
	}

	/**
	 * Reading-progress bar + client-side TOC filler.
	 *
	 * Progress: scroll listener updates width of fixed bar.
	 * TOC: on DOMContentLoaded, scans `.emcp-article h2[id]` and
	 * injects links into the placeholder `<ol>` so the TOC works
	 * even when H2s come from Elementor widgets rendered AFTER
	 * `the_content` filter.
	 */
	private function render_progress_bar(): string {
		$js = <<<'JS'
(function(){
var b=document.querySelector(".emcp-reading-progress");
if(b){function u(){var s=window.scrollY||document.documentElement.scrollTop,h=document.documentElement.scrollHeight-window.innerHeight;b.style.width=(h>0?(s/h*100):0)+"%"}window.addEventListener("scroll",u,{passive:true});u();}

function tocBuild(){
  var holder=document.querySelector(".emcp-toc[data-emcp-auto]");
  if(!holder)return;
  var art=document.querySelector(".emcp-article");
  if(!art)return;
  var h2s=art.querySelectorAll("h2[id]");
  if(h2s.length<3){holder.style.display="none";return;}
  var ol=holder.querySelector("ol");
  if(!ol)return;
  var frag=document.createDocumentFragment();
  h2s.forEach(function(h){
    var t=h.cloneNode(true);
    var a=t.querySelector("a.emcp-anchor");
    if(a)a.remove();
    var li=document.createElement("li");
    var link=document.createElement("a");
    link.href="#"+h.id;
    link.dataset.emcpTarget=h.id;
    link.textContent=t.textContent.trim();
    li.appendChild(link);
    frag.appendChild(li);
  });
  ol.innerHTML="";
  ol.appendChild(frag);
  holder.style.display="";
  var slot=document.querySelector(".emcp-toc-sidebar-slot");
  if(slot&&!slot.dataset.emcpFilled){
    var clone=holder.cloneNode(true);
    clone.removeAttribute("data-emcp-auto");
    clone.style.display="";
    slot.appendChild(clone);
    slot.dataset.emcpFilled="1";
    holder.style.display="none";
  }
  wireToc();
}

function wireToc(){
  var links=document.querySelectorAll(".emcp-toc li a[data-emcp-target]");
  if(!links.length)return;
  // Hover bridge: hover TOC link → flash H2.
  links.forEach(function(link){
    link.addEventListener("mouseenter",function(){
      var id=link.dataset.emcpTarget;
      var h=document.getElementById(id);
      if(h)h.classList.add("emcp-hovered");
    });
    link.addEventListener("mouseleave",function(){
      var id=link.dataset.emcpTarget;
      var h=document.getElementById(id);
      if(h)h.classList.remove("emcp-hovered");
    });
  });
  // Scroll spy: mark visible H2's TOC links as active.
  if(!("IntersectionObserver" in window))return;
  var linkMap={};
  links.forEach(function(l){linkMap[l.dataset.emcpTarget]=linkMap[l.dataset.emcpTarget]||[];linkMap[l.dataset.emcpTarget].push(l);});
  var h2s=document.querySelectorAll(".emcp-article h2[id]");
  var io=new IntersectionObserver(function(entries){
    entries.forEach(function(e){
      var id=e.target.id;
      var ls=linkMap[id]||[];
      if(e.isIntersecting){ls.forEach(function(l){l.classList.add("emcp-active")});}
      else{ls.forEach(function(l){l.classList.remove("emcp-active")});}
    });
  },{rootMargin:"-20% 0px -60% 0px",threshold:0});
  h2s.forEach(function(h){io.observe(h);});
}

function fixSocialGradients(){
  var styles={
    "elementor-social-icon-instagram":"linear-gradient(135deg,#f09433 0%,#e6683c 25%,#dc2743 50%,#cc2366 75%,#bc1888 100%)",
    "elementor-social-icon-tiktok":"linear-gradient(135deg,#25F4EE 0%,#000000 50%,#FE2C55 100%)"
  };
  Object.keys(styles).forEach(function(cls){
    document.querySelectorAll(".emcp-author-socials ."+cls).forEach(function(el){
      el.style.setProperty("background-image",styles[cls],"important");
      el.style.setProperty("background-color","transparent","important");
    });
  });
}

if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",function(){tocBuild();fixSocialGradients();});}else{tocBuild();fixSocialGradients();}
setTimeout(function(){tocBuild();fixSocialGradients();},1000);
setTimeout(function(){tocBuild();fixSocialGradients();},3000);
})();
JS;
		return '<div class="emcp-reading-progress" aria-hidden="true"></div><script>' . $js . '</script>';
	}

	/**
	 * Meta strip — word count + read time + last modified + author.
	 */
	private function build_meta_strip( string $content ): string {
		$post_id = get_queried_object_id();
		if ( ! $post_id ) {
			return '';
		}

		$plain = wp_strip_all_tags( $content, true );
		$words = max( 1, str_word_count( $plain ) );
		$mins  = max( 1, (int) ceil( $words / 225 ) );

		$modified = get_the_modified_date( 'M j, Y', $post_id );
		$author   = get_the_author_meta( 'display_name', get_post_field( 'post_author', $post_id ) );

		$parts = array();
		$parts[] = '<span class="emcp-meta-item"><span class="emcp-meta-icon">⏱</span>' . esc_html( $mins ) . ' min read</span>';
		$parts[] = '<span class="emcp-meta-sep" aria-hidden="true"></span>';
		$parts[] = '<span class="emcp-meta-item"><span class="emcp-meta-icon">📖</span>' . esc_html( number_format( $words ) ) . ' words</span>';
		if ( $modified ) {
			$parts[] = '<span class="emcp-meta-sep" aria-hidden="true"></span>';
			$parts[] = '<span class="emcp-meta-item"><span class="emcp-meta-icon">📅</span>Updated ' . esc_html( $modified ) . '</span>';
		}
		if ( $author ) {
			$parts[] = '<span class="emcp-meta-sep" aria-hidden="true"></span>';
			$parts[] = '<span class="emcp-meta-item"><span class="emcp-meta-icon">✎</span>' . esc_html( $author ) . '</span>';
		}

		return '<div class="emcp-article-meta">' . implode( '', $parts ) . '</div>';
	}

	/**
	 * Auto-TOC placeholder. JS (in render_progress_bar) fills it
	 * client-side by scanning `.emcp-article h2[id]` after Elementor
	 * widgets render. Hidden by JS when fewer than 3 H2s found.
	 */
	private function build_toc( string $content ): string {
		unset( $content ); // JS fills from DOM, not server-side.
		return '<nav class="emcp-toc" data-emcp-auto aria-label="Table of Contents" style="display:none">'
			. '<div class="emcp-toc-title">In This Guide</div>'
			. '<ol></ol>'
			. '</nav>';
	}

	/**
	 * Active only for singular posts rendered via an EMCP-generated
	 * single-post theme template.
	 */
	private function should_enhance(): bool {
		$post = get_queried_object();
		if ( ! $post || ! isset( $post->post_type ) ) {
			return false;
		}
		if ( 'post' !== $post->post_type ) {
			return false;
		}

		if ( class_exists( '\\ElementorPro\\Modules\\ThemeBuilder\\Module' ) ) {
			try {
				$module = \ElementorPro\Modules\ThemeBuilder\Module::instance();
				$docs   = $module->get_conditions_manager()->get_documents_for_location( 'single' );
				if ( empty( $docs ) ) {
					return false;
				}
				foreach ( $docs as $doc ) {
					$tid = is_object( $doc ) && method_exists( $doc, 'get_main_id' ) ? (int) $doc->get_main_id() : 0;
					if ( $tid && get_post_meta( $tid, '_emcp_generated', true ) ) {
						return true;
					}
				}
			} catch ( \Throwable $e ) {
				// Fall through.
			}
		}

		/**
		 * Force-enable filter for explicit opt-in.
		 */
		return (bool) apply_filters( 'emcp_article_enhance_force', false, $post );
	}

	private function enqueue_styles(): void {
		if ( $this->enqueued ) {
			return;
		}
		$this->enqueued = true;
		wp_enqueue_style(
			'emcp-article',
			ELEMENTOR_MCP_URL . 'includes/design/css/article-styles.css',
			array(),
			ELEMENTOR_MCP_VERSION
		);
	}

	public function callback_heading_anchor( array $m ): string {
		$level      = $m[1];
		$attrs      = $m[2];
		$inner_html = $m[3];
		$plain_text = wp_strip_all_tags( $inner_html );
		$slug       = sanitize_title( $plain_text );
		if ( '' === $slug ) {
			return $m[0];
		}
		if ( preg_match( '#\bid=#', $attrs ) ) {
			return $m[0];
		}
		$anchor = ' <a href="#' . esc_attr( $slug ) . '" class="emcp-anchor" aria-label="Link to section">#</a>';
		return '<h' . $level . ' id="' . esc_attr( $slug ) . '"' . $attrs . '>' . $inner_html . $anchor . '</h' . $level . '>';
	}

	/**
	 * CSS custom properties from bound kit palette for scoped styles.
	 */
	private function palette_css_vars(): string {
		if ( ! class_exists( '\\Elementor\\Plugin' ) ) {
			return '';
		}
		$kits_manager = \Elementor\Plugin::$instance->kits_manager ?? null;
		if ( ! $kits_manager ) {
			return '';
		}
		$kit_id = $kits_manager->get_active_id();
		if ( ! $kit_id ) {
			return '';
		}
		$settings = get_post_meta( $kit_id, '_elementor_page_settings', true );
		if ( ! is_array( $settings ) ) {
			return '';
		}
		$colors = is_array( $settings['system_colors'] ?? null ) ? $settings['system_colors'] : array();
		$slots  = array(
			'primary'     => '--emcp-primary',
			'secondary'   => '--emcp-secondary',
			'accent'      => '--emcp-accent',
			'text'        => '--emcp-text',
			'text-muted'  => '--emcp-muted',
			'surface'     => '--emcp-surface',
			'surface-alt' => '--emcp-surf-alt',
			'border'      => '--emcp-border',
		);
		$out = array();
		foreach ( $colors as $c ) {
			$title = (string) ( $c['title'] ?? '' );
			if ( 0 !== strpos( $title, 'EMCP · ' ) ) {
				continue;
			}
			$parts = array_map( 'trim', explode( '·', $title ) );
			if ( count( $parts ) < 3 ) {
				continue;
			}
			$slot = strtolower( end( $parts ) );
			if ( isset( $slots[ $slot ] ) && ! empty( $c['color'] ) ) {
				$out[ $slots[ $slot ] ] = $c['color'];
			}
		}
		if ( empty( $out ) ) {
			return '';
		}
		$css = '';
		foreach ( $out as $var => $value ) {
			$css .= $var . ':' . $value . ';';
		}
		return $css;
	}

	// ─── Shortcodes ──────────────────────────────────────────────

	public function shortcode_info( $atts, $content = '' ): string {
		return $this->render_callout( 'info', $atts, $content );
	}

	public function shortcode_warn( $atts, $content = '' ): string {
		return $this->render_callout( 'warn', $atts, $content );
	}

	public function shortcode_success( $atts, $content = '' ): string {
		return $this->render_callout( 'success', $atts, $content );
	}

	public function shortcode_tip( $atts, $content = '' ): string {
		return $this->render_callout( 'tip', $atts, $content );
	}

	private function render_callout( string $variant, $atts, string $content ): string {
		$title   = isset( $atts['title'] ) ? sanitize_text_field( $atts['title'] ) : '';
		$heading = '' !== $title ? '<strong>' . esc_html( $title ) . '</strong>' : '';
		return '<div class="emcp-callout emcp-callout-' . esc_attr( $variant ) . '">' . $heading . do_shortcode( wpautop( $content ) ) . '</div>';
	}

	/**
	 * [emcp_stat value="AED 180" label="From"]
	 */
	public function shortcode_stat( $atts ): string {
		$atts  = shortcode_atts( array( 'value' => '', 'label' => '' ), $atts );
		$value = sanitize_text_field( $atts['value'] );
		$label = sanitize_text_field( $atts['label'] );
		return '<span class="emcp-stat"><span class="emcp-stat-value">' . esc_html( $value ) . '</span><span class="emcp-stat-label">' . esc_html( $label ) . '</span></span>';
	}

	/**
	 * [emcp_pull]Quote text[/emcp_pull]
	 */
	public function shortcode_pull_quote( $atts, $content = '' ): string {
		return '<div class="emcp-pull-quote">' . wp_kses_post( $content ) . '</div>';
	}
}
