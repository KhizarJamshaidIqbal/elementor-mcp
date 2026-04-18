<?php
/**
 * Rebuild FAQ page (default post 3007) via native `faq.page-full` pattern.
 *
 * Site-specific Safari Desert Dubai content lives here (per architecture
 * convention: patterns = generic templates, test scripts = site content).
 * Applies the full Claude-designed FAQ layout — hero with search, sticky
 * category tabs, 6-up popular cards, categorized accordion library, dark
 * trust band, final CTA — excluding header/footer per user spec.
 *
 * Re-run safe: apply-design-to-page replaces post content idempotently.
 *
 * Usage: php tests/create-faq-page.php [--post=3007]
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( "Run from CLI only.\n" );
}

$wp_load = dirname( __DIR__, 4 ) . '/wp-load.php';
if ( ! file_exists( $wp_load ) ) {
	exit( "wp-load.php not found at $wp_load\n" );
}
if ( ! defined( 'DB_HOST' ) ) {
	define( 'DB_HOST', getenv( 'EMCP_DB_HOST' ) ?: '127.0.0.1:10024' );
}
define( 'WP_USE_THEMES', false );
require $wp_load;

$post_id = 3007;
foreach ( $argv ?? array() as $arg ) {
	if ( strpos( $arg, '--post=' ) === 0 ) {
		$post_id = (int) substr( $arg, 7 );
	}
}

if ( ! class_exists( 'Elementor_MCP_Design_Abilities' ) ) {
	exit( "Elementor_MCP_Design_Abilities class not available. Is the plugin active?\n" );
}

$data       = new Elementor_MCP_Data();
$factory    = new Elementor_MCP_Element_Factory();
$composite  = new Elementor_MCP_Composite_Abilities( $data, $factory );
$kit_binder = new Elementor_MCP_Kit_Binder();
$registry   = Elementor_MCP_Pattern_Registry::instance();
$compiler   = new Elementor_MCP_Design_Compiler( $kit_binder, $registry, null );
$design     = new Elementor_MCP_Design_Abilities( $data, $factory, $composite, $compiler, $registry );

$faq_slots = array(
	'hero' => array(
		'breadcrumb'         => array(
			array( 'label' => 'Home',        'url' => '/' ),
			array( 'label' => 'Help Centre', 'url' => '#' ),
			array( 'label' => 'FAQs',        'url' => '' ),
		),
		'eyebrow'            => 'Help · Answers · Before You Book',
		'headline'           => 'Frequently Asked',
		'headline_accent'    => 'Questions',
		'subtitle'           => 'Everything you need to know before booking your Dubai desert adventure — from dune-bashing safety to hot-air balloon weather windows.',
		'bg_image_url'       => 'http://safaridesertdubai.local/wp-content/uploads/2026/04/hero-dune-buggy-dubai-30-minutes-dubai.png',
		'search_placeholder' => "Search — e.g. 'pickup time', 'age limit', 'cancellation'",
		'popular_tags'       => array(
			array( 'label' => 'What to wear', 'url' => '#q-what-to-wear' ),
			array( 'label' => 'Hotel pickup', 'url' => '#q-pickup' ),
			array( 'label' => 'Cancellation', 'url' => '#q-cancel' ),
			array( 'label' => 'Buggy age',    'url' => '#q-buggy-age' ),
		),
	),

	'tabs' => array(
		array( 'id' => 'all',             'label' => 'All',             'count' => '12', 'icon' => 'fa-solid fa-list' ),
		array( 'id' => 'booking',         'label' => 'Booking',         'count' => '3',  'icon' => 'fa-regular fa-calendar-check' ),
		array( 'id' => 'safety',          'label' => 'Safety',          'count' => '2',  'icon' => 'fa-solid fa-shield-halved' ),
		array( 'id' => 'dune-buggy',      'label' => 'Dune Buggy',      'count' => '1',  'icon' => 'fa-solid fa-car-side' ),
		array( 'id' => 'desert-safari',   'label' => 'Desert Safari',   'count' => '3',  'icon' => 'fa-solid fa-mountain-sun' ),
		array( 'id' => 'quad-bike',       'label' => 'Quad Bike',       'count' => '1',  'icon' => 'fa-solid fa-motorcycle' ),
		array( 'id' => 'hot-air-balloon', 'label' => 'Hot Air Balloon', 'count' => '1',  'icon' => 'fa-solid fa-wind' ),
	),

	'popular' => array(
		'eyebrow'         => 'Most asked this week',
		'headline'        => 'Popular',
		'headline_accent' => 'questions',
		'description'     => "These six come up in roughly every phone call. If yours isn't here, browse the full library below or ping us on WhatsApp — we reply in under five minutes.",
		'cards'           => array(
			array( 'icon' => 'fa-regular fa-calendar-check', 'tag' => 'Booking',       'question' => 'How do I book a desert safari?',         'answer' => 'Book online in under two minutes, message us on WhatsApp, or call — we confirm same-day with a hotel pickup time.', 'link' => '#q-book' ),
			array( 'icon' => 'fa-solid fa-shield-halved',    'tag' => 'Safety',        'question' => 'Is the safari safe for families?',       'answer' => 'DTCM-trained drivers, roll cages, four-point belts, monthly vehicle inspections, and booster seats for kids 3–12.', 'link' => '#q-safety' ),
			array( 'icon' => 'fa-solid fa-shirt',            'tag' => 'Before You Go', 'question' => 'What should I wear in the desert?',      'answer' => 'Light layers, closed-toe shoes, a jacket for winter evenings. We provide headscarves and hats on request.', 'link' => '#q-what-to-wear' ),
			array( 'icon' => 'fa-solid fa-location-dot',     'tag' => 'Logistics',     'question' => 'Is hotel pickup included?',              'answer' => 'Complimentary pickup across Dubai — Marina, JBR, Downtown, Palm, Deira, Business Bay, and more.', 'link' => '#q-pickup' ),
			array( 'icon' => 'fa-solid fa-car-side',         'tag' => 'Dune Buggy',    'question' => "What's the minimum age for a buggy?",    'answer' => 'Drivers 16+ with a valid licence; passengers 6+ and 120 cm. 2-seater Can-Ams fit parent + child rides.', 'link' => '#q-buggy-age' ),
			array( 'icon' => 'fa-solid fa-rotate-left',      'tag' => 'Policies',      'question' => 'Can I cancel or reschedule?',            'answer' => 'Full refund 24+ hours ahead. Free rescheduling up to 6 hours before pickup, subject to availability.', 'link' => '#q-cancel' ),
		),
	),

	'library_intro' => array(
		'eyebrow'  => 'Full library',
		'heading'  => 'Every question, answered in full',
		'subtitle' => 'Use the tabs above to filter by category, or scroll through the whole set. Click any question to expand.',
	),

	'categories' => array(
		array(
			'id'          => 'booking',
			'heading'     => 'Booking',
			'icon'        => 'fa-regular fa-calendar-check',
			'count'       => '3',
			'count_label' => '3 questions',
			'items'       => array(
				array(
					'id' => 'q-book',
					'q'  => 'How do I book a Dubai desert safari?',
					'a'  => '<p>Booking takes under two minutes. You have three options:</p><ul><li><strong>Online —</strong> <a href="https://booking.safaridesertdubai.com">booking.safaridesertdubai.com</a>, pay by card, Apple Pay, or PayPal.</li><li><strong>WhatsApp —</strong> <a href="https://wa.me/971524409525">+971 52 440 9525</a>, average reply time 4 minutes, 7 days a week.</li><li><strong>Phone —</strong> <a href="tel:+971524472719">+971 52 447 2719</a>, 08:00–23:00 Gulf time.</li></ul><p>You will receive a confirmation email and WhatsApp message with your driver\'s name and pickup window within 15 minutes.</p>',
				),
				array(
					'id' => 'q-payment',
					'q'  => 'Which payment methods do you accept?',
					'a'  => '<p>All major cards (Visa, Mastercard, Amex), Apple Pay, Google Pay, PayPal online, and cash in <strong>AED, USD, EUR, or GBP</strong> at pickup.</p><p>For your security: we never ask for card details over WhatsApp or phone. Any payment link you receive will be from <strong>booking.safaridesertdubai.com</strong> only — verify the domain before entering card info.</p>',
				),
				array(
					'id' => 'q-pickup',
					'q'  => 'Is hotel pickup included?',
					'a'  => '<p>Yes — <strong>complimentary round-trip pickup</strong> is included from Marina, JBR, Downtown, Palm Jumeirah, Deira, Business Bay, and Dubai South. Pickup is in a private 4×4; we will WhatsApp your driver\'s live location 15 minutes before arrival.</p><p>Pickups from outside central Dubai:</p><ul><li>Sharjah — AED 75</li><li>Ajman — AED 100</li><li>Abu Dhabi — AED 150</li></ul>',
				),
			),
		),
		array(
			'id'          => 'safety',
			'heading'     => 'Safety',
			'icon'        => 'fa-solid fa-shield-halved',
			'count'       => '2',
			'count_label' => '2 questions',
			'items'       => array(
				array(
					'id' => 'q-safety',
					'q'  => 'Is the desert safari safe for families and children?',
					'a'  => '<p>Yes, and we take it seriously. Every element is regulated by the Dubai Department of Tourism and Commerce Marketing (DTCM).</p><ul><li><strong>Drivers —</strong> DTCM-licensed, minimum 5 years dune-driving experience, annual refresher certification.</li><li><strong>Vehicles —</strong> Land Cruisers and Lexus LX inspected monthly, roll cages, four-point seatbelts, dual airbags.</li><li><strong>Children —</strong> under 3 ride free on a parent\'s lap; ages 3–12 get booster seats we provide.</li><li><strong>Medical —</strong> every driver carries a stocked first-aid kit; our desert camp has a resident paramedic 16:00–22:00.</li></ul>',
				),
				array(
					'id' => 'q-what-to-wear',
					'q'  => 'What should I wear for a desert safari?',
					'a'  => '<p>Light, breathable clothing and <strong>closed-toe shoes</strong> (sandals fill with sand quickly). Sunglasses and SPF 30+ are a good idea during the day.</p><p>Evening safaris: temperatures drop to around <strong>15°C in winter</strong> (Nov–Feb) once the sun sets. Bring a light jacket or jumper. We provide complimentary <strong>headscarves, shemaghs, and sun hats</strong> on request at pickup — just ask your driver.</p>',
				),
			),
		),
		array(
			'id'          => 'dune-buggy',
			'heading'     => 'Dune Buggy',
			'icon'        => 'fa-solid fa-car-side',
			'count'       => '1',
			'count_label' => '1 question',
			'items'       => array(
				array(
					'id' => 'q-buggy-age',
					'q'  => "What's the minimum age and height for a dune buggy?",
					'a'  => '<p><strong>Drivers</strong> must be 16 or older and hold a valid driving licence (any country). <strong>Passengers</strong> must be 6 or older and at least 120 cm tall to clear the harness.</p><p>Our two-seater <strong>Can-Am Maverick X3</strong> is perfect for a parent + child combo; for solo adrenaline, choose a Polaris RZR 1000. All rides include helmets, gloves, goggles, a 15-minute briefing, and a lead guide on a supervised track.</p>',
				),
			),
		),
		array(
			'id'          => 'desert-safari',
			'heading'     => 'Desert Safari',
			'icon'        => 'fa-solid fa-mountain-sun',
			'count'       => '3',
			'count_label' => '3 questions',
			'items'       => array(
				array(
					'id' => 'q-evening-includes',
					'q'  => 'What does the evening safari include?',
					'a'  => '<p>Our flagship 6-hour evening experience covers the full Bedouin-camp arc. Pickup to drop-off:</p><ul><li>45 minutes of dune-bashing on the Lahbab red dunes</li><li>Sandboarding on a signature 60° slope</li><li>A short camel ride through the reserve</li><li>Henna, Arabic coffee, and fresh dates at the camp</li><li>A 4-course BBQ buffet with vegetarian, vegan, and halal options</li><li>Three live shows — Tanoura, belly dance, and fire performance</li><li>Shisha corner (smoking optional) and open-sky stargazing</li></ul>',
				),
				array(
					'id' => 'q-meals',
					'q'  => 'Are vegetarian, vegan, and halal meals available?',
					'a'  => '<p>All three, yes — plus <strong>gluten-free, Jain, and Kosher-style</strong> options with 12 hours\' notice. All meat served at the camp is <strong>100% halal</strong>, sourced from UAE-certified butchers.</p><p>Tell us at booking, or WhatsApp your dietary needs the morning of your tour and we will confirm with the camp kitchen.</p>',
				),
				array(
					'id' => 'q-private-price',
					'q'  => 'How much does a private desert safari cost?',
					'a'  => '<p><strong>Private tours start from AED 950</strong> for up to 6 guests — this is the vehicle + driver/guide, not per person. It is the cheapest way to go private if you are travelling in a group.</p><p>Our most popular upgrade is the <strong>Premium Private Evening Safari at AED 1,450</strong>, which adds reserved camp seating, unlimited soft drinks and juices, a private camp photographer, and priority camel access.</p>',
				),
			),
		),
		array(
			'id'          => 'quad-bike',
			'heading'     => 'Quad Bike',
			'icon'        => 'fa-solid fa-motorcycle',
			'count'       => '1',
			'count_label' => '1 question',
			'items'       => array(
				array(
					'id' => 'q-quad-experience',
					'q'  => 'Do I need experience to ride a quad bike?',
					'a'  => '<p><strong>No experience needed.</strong> We run a 10-minute briefing covering throttle, brakes, body position on the dunes, and hand signals — then you ride in a supervised group behind a lead guide on a beginner-friendly track.</p><p>Two engine sizes are available: automatic <strong>250cc</strong> for first-timers, <strong>350cc</strong> for returning riders or experienced hands. Helmet, gloves, goggles, and a dust mask are included; closed-toe shoes required.</p>',
				),
			),
		),
		array(
			'id'          => 'hot-air-balloon',
			'heading'     => 'Hot Air Balloon',
			'icon'        => 'fa-solid fa-wind',
			'count'       => '1',
			'count_label' => '1 question',
			'items'       => array(
				array(
					'id' => 'q-balloon-weather',
					'q'  => 'Is the hot-air balloon ride weather-dependent?',
					'a'  => '<p>Yes. Balloons fly from <strong>October through April only</strong> — summer winds and heat make it unsafe. On your flight day, the chief pilot confirms the go/no-go at 04:00 based on live wind and thermal readings.</p><p>If we cancel for weather, you choose: a <strong>full refund</strong> or a <strong>free reschedule</strong> to any available date. Flights take off pre-sunrise and last 45–60 minutes in the air, reaching around 4,000 feet over the Dubai Desert Conservation Reserve.</p>',
				),
			),
		),
		array(
			'id'          => 'policies',
			'heading'     => 'Cancellations & Changes',
			'icon'        => 'fa-solid fa-rotate-left',
			'count'       => '1',
			'count_label' => '1 question',
			'data_cat'    => 'booking',
			'items'       => array(
				array(
					'id' => 'q-cancel',
					'q'  => 'Can I cancel or reschedule my booking?',
					'a'  => '<p>Flexible as we can reasonably be:</p><ul><li><strong>Full refund</strong> if cancelled 24+ hours before pickup.</li><li><strong>50% refund</strong> for same-day cancellations — this covers the driver call-out and reserved camp seating.</li><li><strong>Free rescheduling</strong> any time until 6 hours before pickup, subject to availability.</li></ul><p>Weather cancellations (our side) are always 100% refundable or a free reschedule — your call.</p>',
				),
			),
		),
	),

	'trust' => array(
		array( 'icon' => 'fa-solid fa-certificate',   'title' => 'DTCM Licensed',  'subtitle' => 'Dubai Tourism regulated operator' ),
		array( 'icon' => 'fa-solid fa-star',          'title' => '5,000+ Reviews', 'subtitle' => '4.9 avg · TripAdvisor & Google' ),
		array( 'icon' => 'fa-solid fa-shield-halved', 'title' => 'Full Insurance', 'subtitle' => 'All guests & vehicles covered' ),
		array( 'icon' => 'fa-regular fa-clock',       'title' => '24/7 Support',   'subtitle' => 'WhatsApp, phone & email' ),
	),

	'final_cta' => array(
		'eyebrow'         => "Still stuck? We're on it.",
		'headline'        => 'Still have',
		'headline_accent' => 'questions?',
		'subtitle'        => 'Our team replies on WhatsApp in under five minutes, every day of the year. Ask us anything — itineraries, dietary needs, private pricing, last-minute weather calls.',
		'primary_label'   => 'WhatsApp +971 52 440 9525',
		'primary_url'     => 'https://wa.me/971524409525',
		'primary_icon'    => 'fa-brands fa-whatsapp',
		'secondary_label' => 'Call +971 52 447 2719',
		'secondary_url'   => 'tel:+971524472719',
		'secondary_icon'  => 'fa-solid fa-phone',
		'note'            => 'Average reply 4 min · English, Arabic, Hindi, Russian, French',
		'bg_image_url'    => 'http://safaridesertdubai.local/wp-content/uploads/2026/04/hero-dune-buggy-dubai-30-minutes-dubai.png',
	),
);

$input = array(
	'post_id'      => $post_id,
	'brand_tokens' => array(
		'palette'    => 'emcp-classic-desert',
		'typography' => 'editorial-classic',
	),
	'sections'     => array(
		array( 'pattern' => 'faq.page-full', 'slots' => $faq_slots ),
	),
);

$result = $design->execute_apply_design_to_page( $input );
if ( is_wp_error( $result ) ) {
	printf( "ERROR (%s): %s\n", $result->get_error_code(), $result->get_error_message() );
	exit( 1 );
}
printf( "SUCCESS.\n" );
printf( "  post_id:            %d\n", $result['post_id'] );
printf( "  preview_url:        %s\n", $result['preview_url'] );
printf( "  edit_url:           %s\n", $result['edit_url'] );
printf( "  elements_replaced:  %d\n", $result['elements_replaced'] );
exit( 0 );
