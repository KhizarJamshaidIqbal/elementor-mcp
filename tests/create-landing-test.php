<?php
/**
 * Creates a test landing page exercising all Phase-2 patterns.
 * Visual verification of pattern library.
 *
 * Usage: php tests/create-landing-test.php [--force]
 */

if ( 'cli' !== PHP_SAPI ) { exit( "CLI only.\n" ); }
if ( ! defined( 'DB_HOST' ) ) { define( 'DB_HOST', '127.0.0.1:10024' ); }
define( 'WP_USE_THEMES', false );
require dirname( __DIR__, 4 ) . '/wp-load.php';

$force = in_array( '--force', $argv ?? array(), true );
$title = 'EMCP · Phase 2 Pattern Showcase';

if ( ! $force ) {
	$existing = get_posts( array( 'post_type' => 'page', 'post_status' => 'any', 'title' => $title, 'posts_per_page' => 1, 'fields' => 'ids' ) );
	if ( ! empty( $existing ) ) {
		$id = (int) $existing[0];
		printf( "Page exists: ID=%d url=%s\n", $id, get_permalink( $id ) );
		exit( 0 );
	}
}

$data       = new Elementor_MCP_Data();
$factory    = new Elementor_MCP_Element_Factory();
$composite  = new Elementor_MCP_Composite_Abilities( $data, $factory );
$kit_binder = new Elementor_MCP_Kit_Binder();
$registry   = Elementor_MCP_Pattern_Registry::instance();
$compiler   = new Elementor_MCP_Design_Compiler( $kit_binder, $registry, null );
$design     = new Elementor_MCP_Design_Abilities( $data, $factory, $composite, $compiler, $registry );

$ir = array(
	'title'        => $title,
	'status'       => 'publish',
	'brand_tokens' => array( 'palette' => 'desert-warm', 'typography' => 'editorial-classic' ),
	'sections'     => array(
		array(
			'pattern' => 'hero.minimal-center',
			'slots'   => array(
				'eyebrow'         => 'PHASE 2 SHOWCASE',
				'headline'        => 'Every Pattern in Action',
				'subhead'         => 'A single page exercising the complete Elementor MCP design pattern library.',
				'cta_label'       => 'Book on WhatsApp',
				'cta_url'         => 'https://wa.me/971524409525',
				'secondary_label' => 'View Tours',
				'secondary_url'   => '#tours',
			),
		),
		array(
			'pattern' => 'stats-bar.4-up',
			'slots'   => array( 'stats' => array(
				array( 'value' => '500+', 'label' => 'Happy travelers' ),
				array( 'value' => '4.9★', 'label' => 'Google rating' ),
				array( 'value' => '10 yrs', 'label' => 'In Dubai' ),
				array( 'value' => 'Free', 'label' => 'Hotel transfer' ),
			) ),
		),
		array(
			'pattern' => 'features.icon-grid-3col',
			'slots'   => array(
				'heading'  => 'Why Book With Us',
				'subhead'  => 'Three reasons travelers pick us.',
				'features' => array(
					array( 'icon' => 'fas fa-van-shuttle', 'title' => 'Hotel Pickup', 'body' => 'Free transfer from anywhere in Dubai.' ),
					array( 'icon' => 'fas fa-helmet-safety', 'title' => 'Safety Gear', 'body' => 'Helmet, goggles, expert briefing.' ),
					array( 'icon' => 'fas fa-camera', 'title' => 'Photos Included', 'body' => 'Professional action shots of your ride.' ),
				),
			),
		),
		array(
			'pattern' => 'features.card-grid-4col',
			'slots'   => array(
				'heading'  => 'Every Tour Includes',
				'features' => array(
					array( 'icon' => 'fas fa-route', 'title' => 'Custom Routes', 'body' => 'Red dunes, Lahbab, sunset spots.' ),
					array( 'icon' => 'fas fa-users', 'title' => 'Small Groups', 'body' => 'Max 6 riders per guide.' ),
					array( 'icon' => 'fas fa-shield-halved', 'title' => 'Full Insurance', 'body' => 'Premium coverage.' ),
					array( 'icon' => 'fas fa-cookie-bite', 'title' => 'Refreshments', 'body' => 'Water and snacks at stops.' ),
				),
			),
		),
		array(
			'pattern' => 'features.checklist-2col',
			'slots'   => array(
				'heading' => 'What to Expect',
				'items'   => array(
					'Free hotel transfer (Dubai + Sharjah)',
					'Full safety gear and briefing',
					'Experienced desert-trained guides',
					'Professional photography',
					'Water and refreshments',
					'Sunset and photo stops',
					'Clean, maintained equipment',
					'Full insurance coverage',
				),
			),
		),
		array(
			'pattern' => 'pricing.3-tier',
			'slots'   => array(
				'heading' => 'Choose Your Adventure',
				'subhead' => 'All packages include hotel transfer and safety gear.',
				'tiers'   => array(
					array(
						'name' => 'Quad Taste', 'price' => 'AED 180', 'period' => '/30 min',
						'cta_label' => 'Book Now', 'cta_url' => 'https://wa.me/971524409525',
						'features' => array( '30-min ride', 'Hotel transfer', 'Safety gear', 'Photos' ),
					),
					array(
						'name' => 'Full Safari', 'price' => 'AED 450', 'period' => '/6 hrs', 'featured' => true,
						'cta_label' => 'Book Featured', 'cta_url' => 'https://wa.me/971524409525',
						'features' => array( '6-hr experience', 'Dune bashing', 'BBQ dinner', 'Camel ride', 'Shows' ),
					),
					array(
						'name' => 'Private VIP', 'price' => 'AED 1200', 'period' => '/day',
						'cta_label' => 'Contact', 'cta_url' => 'https://wa.me/971524409525',
						'features' => array( 'Dedicated guide', 'Can-Am buggy', 'Custom itinerary', 'Premium dinner' ),
					),
				),
			),
		),
		array(
			'pattern' => 'testimonial.carousel',
			'slots'   => array(
				'heading' => 'What Travelers Say',
				'items'   => array(
					array( 'body' => 'Best adventure of our Dubai trip. Guides were fantastic.', 'author' => 'Fatima R.', 'role' => 'From UK', 'rating' => 5 ),
					array( 'body' => 'Professional team, brand new quad bikes. Photos amazing.', 'author' => 'David P.', 'role' => 'From Germany', 'rating' => 5 ),
					array( 'body' => 'Safe, fun, value for money. Highly recommend.', 'author' => 'Aisha M.', 'role' => 'From UAE', 'rating' => 5 ),
				),
			),
		),
		array(
			'pattern' => 'faq.accordion-centered',
			'slots'   => array(
				'heading' => 'Common Questions',
				'subhead' => 'Quick answers to what travelers ask most.',
				'items'   => array(
					array( 'q' => 'Do I need a driving license?', 'a' => 'No. Full briefing on site.' ),
					array( 'q' => 'What is the minimum age?', 'a' => '16+ to drive, 6+ to ride as passenger.' ),
					array( 'q' => 'What should I wear?', 'a' => 'Comfortable closed shoes. We provide helmet and goggles.' ),
					array( 'q' => 'Is hotel transfer included?', 'a' => 'Yes, free round-trip from anywhere in Dubai.' ),
				),
			),
		),
		array(
			'pattern' => 'cta.banner-full-width',
			'slots'   => array(
				'eyebrow'       => 'BOOK YOUR ADVENTURE',
				'headline'      => 'Ready for Your Desert Adventure?',
				'subhead'       => 'Free hotel transfer included. Book in under 60 seconds.',
				'primary_label' => 'Book on WhatsApp',
				'primary_url'   => 'https://wa.me/971524409525',
				'trust_stats'   => array(
					array( 'value' => '★ 4.9', 'label' => '1,000+ reviews' ),
					array( 'value' => 'DTCM',  'label' => 'Licensed operator' ),
					array( 'value' => 'FREE',  'label' => 'Hotel transfer' ),
				),
			),
		),
	),
);

$result = $design->execute_design_page( $ir );

if ( is_wp_error( $result ) ) {
	printf( "ERROR (%s): %s\n", $result->get_error_code(), $result->get_error_message() );
	exit( 1 );
}

printf( "SUCCESS.\n" );
printf( "  post_id:          %d\n", $result['post_id'] );
printf( "  edit_url:         %s\n", $result['edit_url'] );
printf( "  preview_url:      %s\n", $result['preview_url'] );
printf( "  elements_created: %d\n", $result['elements_created'] );
