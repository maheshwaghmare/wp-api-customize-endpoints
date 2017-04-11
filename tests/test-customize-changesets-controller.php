<?php
/**
 * Unit tests covering WP_Test_REST_Customize_Changesets_Controller functionality.
 *
 * @package WordPress
 * @subpackage REST API
 */

/**
 * Class WP_Test_REST_Customize_Changesets_Controller.
 *
 * @group restapi
 */
class WP_Test_REST_Customize_Changesets_Controller extends WP_Test_REST_Controller_TestCase {
	/**
	 * Subscriber user ID.
	 *
	 * @todo Grant or deny caps to the user rather than assuming that roles do or don't have caps.
	 *
	 * @var int
	 */
	protected static $subscriber_id;

	/**
	 * Admin user ID.
	 *
	 * @todo Grant or deny caps to the user rather than assuming that roles do or don't have caps.
	 *
	 * @var int
	 */
	protected static $admin_id;

	/**
	 * Editor user ID.
	 *
	 * @todo Grant or deny caps to the user rather than assuming that roles do or don't have caps.
	 *
	 * @var int
	 */
	protected static $editor_id;

	/**
	 * Set up before class.
	 *
	 * @param object $factory Factory.
	 */
	public static function wpSetUpBeforeClass( $factory ) {

		if ( ! class_exists( 'WP_Customize_Manager' ) ) {
			require_once( ABSPATH . WPINC . '/class-wp-customize-manager.php' );
		}

		// Deleted by _delete_all_data() in WP_UnitTestCase::tearDownAfterClass().
		self::$subscriber_id = $factory->user->create( array(
			'role' => 'subscriber',
		) );

		self::$admin_id = $factory->user->create( array(
			'role' => 'administrator',
		) );

		self::$editor_id = $factory->user->create( array(
			'role' => 'editor',
		) );
	}

	/**
	 * Return a WP_Error with an 'illegal' code..
	 *
	 * @return WP_Error
	 */
	public function __return_error_illegal() {
		return new WP_Error( 'illegal' );
	}

	/**
	 * Test register_routes.
	 *
	 * @covers WP_REST_Customize_Changesets_Controller::register_routes()
	 */
	public function test_register_routes() {
		$routes = $this->server->get_routes();
		$this->assertArrayHasKey( '/wp/v2/customize/changesets', $routes );
		$this->assertArrayHasKey( '/wp/v2/customize/changesets/(?P<uuid>[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}+)', $routes );
	}

	/**
	 * Test (get_)context_param.
	 *
	 * @covers WP_REST_Customize_Changesets_Controller::get_context_param()
	 */
	public function test_context_param() {

		// Test collection.
		$request = new WP_REST_Request( 'OPTIONS', '/wp/v2/changesets' );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();
		$this->assertEquals( 'view', $data['endpoints'][0]['args']['context']['default'] );
		$this->assertEquals( array( 'view', 'embed', 'edit' ), $data['endpoints'][0]['args']['context']['enum'] );

		// Test single.
		$manager = new WP_Customize_Manager();
		$manager->save_changeset_post();
		$request = new WP_REST_Request( 'OPTIONS', '/wp/v2/changesets/' . $manager->changeset_uuid() );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();
		$this->assertEquals( 'view', $data['endpoints'][0]['args']['context']['default'] );
		$this->assertEquals( array( 'view', 'embed', 'edit' ), $data['endpoints'][0]['args']['context']['enum'] );
	}

	/**
	 * Test register_routes.
	 *
	 * @covers WP_REST_Customize_Changesets_Controller::get_item_schema()
	 */
	public function test_get_item_schema() {

		// @todo Add all properties.
		$request = new WP_REST_Request( 'OPTIONS', '/wp/v2/changesets' );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();
		$properties = $data['schema']['properties'];

		$this->assertEquals( 6, count( $properties ) );
		$this->assertArrayHasKey( 'author', $properties );
		$this->assertArrayHasKey( 'date', $properties );
		$this->assertArrayHasKey( 'date_gmt', $properties );
		$this->assertArrayHasKey( 'settings', $properties ); // Instead of content.
		$this->assertArrayHasKey( 'status', $properties );
		$this->assertArrayHasKey( 'title', $properties );
	}

	/**
	 * Test get_item.
	 *
	 * @covers WP_REST_Customize_Changesets_Controller::get_item()
	 */
	public function test_get_item() {
		$manager = new WP_Customize_Manager();
		$manager->save_changeset_post();

		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/changesets/%s', $manager->changeset_uuid() ) );
		$response = $this->server->dispatch( $request );

		$this->assertNotInstanceOf( 'WP_Error', $response );
		$response = rest_ensure_response( $response );
		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( 1, count( $response->get_data() ) );
	}

	/**
	 * Test getting changeset without having proper permissions.
	 */
	public function test_get_item_without_permissions() {
		$manager = new WP_Customize_Manager();
		$manager->save_changeset_post();

		wp_set_current_user( self::$subscriber_id );
		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/changesets/%s', $manager->changeset_uuid() ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_forbidden', $response, 403 );
	}

	/**
	 * Test getting changeset with invalid UUID.
	 */
	public function test_get_item_with_invalid_uuid() {

		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/changesets/%s', REST_TESTS_IMPOSSIBLY_HIGH_NUMBER ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_changeset_invalid_uuid', $response, 404 );
	}

	/**
	 * Test getting changeset list with edit context with proper permissions.
	 */
	public function test_get_item_list_context_with_permission() {
		wp_set_current_user( self::$admin_id );
		$request = new WP_REST_Request( 'GET', '/wp/v2/changesets' );
		$request->set_query_params( array(
			'context' => 'edit',
		) );
		$response = $this->server->dispatch( $request );
		$this->assertNotInstanceOf( 'WP_Error', $response );
		$response = rest_ensure_response( $response );
		$this->assertEquals( 200, $response->get_status() );
	}

	/**
	 * Test getting changeset list with edit context without proper permissions.
	 */
	public function test_get_item_list_context_without_permission() {
		wp_set_current_user( self::$subscriber_id );
		$request = new WP_REST_Request( 'GET', '/wp/v2/changesets' );
		$request->set_query_params( array(
			'context' => 'edit',
		) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_forbidden_context', $response, 401 );
	}

	/**
	 * Test getting a changeset with edit context without proper permissions.
	 */
	public function test_get_item_context_without_permission() {
		$manager = new WP_Customize_Manager();
		$manager->save_changeset_post();

		wp_set_current_user( self::$subscriber_id );
		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/changesets/%s', $manager->changeset_uuid() ) );
		$request->set_query_params( array(
			'context' => 'edit',
		) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_forbidden_context', $response, 401 );
	}

	/**
	 * Test get_items.
	 *
	 * @covers WP_REST_Customize_Changesets_Controller::get_items()
	 */
	public function test_get_items() {
		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/changesets' ) );
		$response = $this->server->dispatch( $request );

		$manager1 = new WP_Customize_Manager();
		$manager1->save_changeset_post();

		$manager2 = new WP_Customize_Manager();
		$manager2->save_changeset_post();

		$this->assertNotInstanceOf( 'WP_Error', $response );
		$response = rest_ensure_response( $response );
		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( 2, count( $response->get_data() ) );
	}

	/**
	 * Test the case when user doesn't have permissions for some of the settings.
	 */
	public function test_get_item_without_permissions_to_some_settings() {
		$manager = new WP_Customize_Manager();
		$setting_allowed_id = 'allowed_setting';
		$setting_allowed = new WP_Customize_Setting( $manager, $setting_allowed_id );
		$result_setting1 = $manager->add_setting( $setting_allowed );
		$result_setting1->capability = 'editor_can_see';

		$setting_forbidden_id = 'forbidden_setting';
		$setting_forbidden = new WP_Customize_Setting( $manager, $setting_forbidden_id );
		$result_setting2 = $manager->add_setting( $setting_forbidden );
		$result_setting2->capability = 'editor_can_not_see';

		wp_set_current_user( self::$editor_id );
		$user = new WP_User( self::$editor_id );
		$user->add_cap( 'editor_can_see' );

		$manager = new WP_Customize_Manager();
		$manager->save_changeset_post( array(
			'customize_changeset_data' => array(
				$setting_allowed_id => array(
					'value' => 'Foo',
				),
				$setting_forbidden_id => array(
					'value' => 'Bar',
				),
			),
		) );

		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/changesets/%s', $manager->changeset_uuid() ) );
		$response = $this->server->dispatch( $request );

		$this->assertNotInstanceOf( 'WP_Error', $response );
		$response = rest_ensure_response( $response );

		$changeset_data = $response->get_data();
		$changeset_settings = json_decode( $changeset_data[0]['post_content'], true );

		$this->assertTrue( isset( $changeset_settings[ $setting_allowed_id ] ) );
		$this->assertFalse( isset( $changeset_settings[ $setting_forbidden_id ] ) );
	}

	/**
	 * Test create_item.
	 *
	 * @covers WP_REST_Customize_Changesets_Controller::create_item()
	 */
	public function test_create_item() {
		$this->markTestIncomplete();
	}

	/**
	 * Test update_item.
	 *
	 * @covers WP_REST_Customize_Changesets_Controller::update_item()
	 */
	public function test_update_item() {
		$this->markTestIncomplete();
	}

	/**
	 * Test the case when the user doesn't have permissions to edit some of the settings within the changeset.
	 */
	public function test_update_item_cannot_edit_some_settings() {
		$this->markTestIncomplete();
	}

	/**
	 * Test that slug of a new changeset cannot be changed with update_item().
	 */
	public function test_update_item_cannot_create_changeset_slug() {
		wp_set_current_user( self::$admin_id );

		$uuid = wp_generate_uuid4();
		$bad_slug = 'slug-after';

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/changesets/%s', $uuid ) );
		$request->set_body_params( array(
			'slug' => $bad_slug,
		) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'cannot_edit_changeset_slug', $response, 403 );

		$manager = new WP_Customize_Manager();
		$this->assertEmpty( get_post( $manager->find_changeset_post_id( $uuid ) ) );
	}

	/**
	 * Test that slug of an existing changeset cannot be changed with update_item().
	 */
	public function test_update_item_cannot_edit_changeset_slug() {
		wp_set_current_user( self::$admin_id );

		$manager = new WP_Customize_Manager();
		$manager->save_changeset_post();

		$bad_slug = 'slug-after';

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/changesets/%s', $manager->changeset_uuid() ) );
		$request->set_body_params( array(
			'slug' => $bad_slug,
		) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'cannot_edit_changeset_slug', $response, 403 );
		$this->assertSame( get_post( $manager->changeset_post_id() )->post_name, $manager->changeset_uuid() );
	}

	/**
	 * Test that changesets cannot be created with update_item() when the user lacks capabilities.
	 */
	public function test_update_item_cannot_create_changeset_post() {
		wp_set_current_user( self::$subscriber_id );

		$uuid = wp_generate_uuid4();

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/changesets/%s', $uuid ) );
		$request->set_body_params( array(
			'customize_changeset_data' => array(
				'basic_option' => array(
					'value' => 'Foo',
				),
			),
		) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'cannot_create_changeset_post', $response, 403 );

		$manager = new WP_Customize_Manager();
		$this->assertEmpty( $manager->find_changeset_post_id( $uuid ) );
	}

	/**
	 * Test that changesets cannot be updated with update_item() when the user lacks capabilities.
	 */
	public function test_update_item_cannot_edit_changeset_post() {
		wp_set_current_user( self::$subscriber_id );

		$manager = new WP_Customize_Manager();
		$manager->save_changeset_post();
		$changeset_post_id = $manager->changeset_post_id();

		$content_before = get_post( $changeset_post_id )->post_content;

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/changesets/%s', $manager->changeset_uuid() ) );
		$request->set_body_params( array(
			'customize_changeset_data' => array(
				'basic_option' => array(
					'value' => 'Foo',
				),
			),
		) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'cannot_edit_changeset_post', $response, 403 );

		$content_after = get_post( $changeset_post_id )->post_content;
		$this->assertJsonStringEqualsJsonString( $content_before, $content_after );
	}

	/**
	 * Test that update_item() rejects invalid changeset data.
	 */
	public function test_update_item_invalid_changeset_data() {
		wp_set_current_user( self::$admin_id );

		$manager = new WP_Customize_Manager();
		$manager->save_changeset_post( array(
			'basic_option' => array(
				'value' => 'Foo',
			),
		) );
		$changeset_post_id = $manager->changeset_post_id();

		$content_before = get_post( $changeset_post_id )->post_content;

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/changesets/%s', $manager->changeset_uuid() ) );
		$request->set_body_params( array(
			'customize_changeset_data' => '[MALFORMED]',
		) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'invalid_customize_changeset_data', $response, 403 );

		$content_after = get_post( $changeset_post_id )->post_content;
		$this->assertJsonStringEqualsJsonString( $content_before, $content_after );
	}

	/**
	 * Test that update_item() can create a changeset with a title.
	 */
	public function test_update_item_create_changeset_title() {
		wp_set_current_user( self::$admin_id );

		$uuid = wp_generate_uuid4();
		$title = 'FooBarBaz';

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/changesets/%s', $uuid ) );
		$request->set_body_params( array(
			'title' => $title,
		) );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertSame( $data['title'], $title );

		$manager = new WP_Customize_Manager();
		$this->assertSame( get_post( $manager->find_changeset_post_id( $uuid ) )->post_title, $title );
	}

	/**
	 * Test that changeset titles can be updated with update_item().
	 */
	public function test_update_item_edit_changeset_title() {
		wp_set_current_user( self::$admin_id );

		$manager = new WP_Customize_Manager();
		$title_before = 'Foo';
		$title_after = 'Bar';

		$manager->save_changeset_post( array(
			'title' => $title_before,
		) );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/changesets/%s', $manager->changeset_uuid() ) );
		$request->set_body_params( array(
			'title' => $title_after,
		) );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertSame( $data['title'], $title_after );
		$this->assertSame( get_post( $manager->changeset_post_id() )->post_title, $title_after );
	}

	/**
	 * Test that update_item() rejects creating changesets with nonexistant and disallowed post statuses.
	 *
	 * @param string $bad_status Bad status.
	 *
	 * @dataProvider data_bad_customize_changeset_status
	 */
	public function test_update_item_create_bad_customize_changeset_status( $bad_status ) {
		wp_set_current_user( self::$admin_id );

		$uuid = wp_generate_uuid4();

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/changesets/%s', $uuid ) );
		$request->set_body_params( array(
			'status' => $bad_status,
		) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bad_customize_changeset_status', $response, 400 );

		$manager = new WP_Customize_Manager();
		$this->assertEmpty( $manager->find_changeset_post_id( $uuid ) );
	}

	/**
	 * Test that update_item() rejects updating a changeset with nonexistant and disallowed post statuses.
	 *
	 * @param string $bad_status Bad status.
	 *
	 * @dataProvider data_bad_customize_changeset_status
	 */
	public function test_update_item_edit_bad_customize_changeset_status( $bad_status ) {
		wp_set_current_user( self::$admin_id );

		$manager = new WP_Customize_Manager();
		$manager->save_changeset_post();
		$status_before = get_post_status( $manager->changeset_post_id() );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/changesets/%s', $manager->changeset_uuid() ) );
		$request->set_body_params( array(
			'status' => $bad_status,
		) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bad_customize_changeset_status', $response, 400 );
		$this->assertSame( $status_before, get_post_status( $manager->changeset_post_id() ) );
	}

	/**
	 * Bad changeset statuses.
	 */
	public function data_bad_customize_changeset_status() {
		return array(
			// Doesn't exist.
			array( rand_str() ),
			// Not in the whitelist.
			array( 'trash' ),
		);
	}

	/**
	 * Test that update_item() does not create a published changeset if the user lacks capabilities.
	 *
	 * @dataProvider data_publish_changeset_status
	 *
	 * @param array $publish_status Status to publish the changeset.
	 */
	public function test_update_item_create_changeset_publish_unauthorized( $publish_status ) {
		// TODO: Allow the user to create changesets but not publish.
		wp_set_current_user( self::$subscriber_id );

		$uuid = wp_generate_uuid4();

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/changesets/%s', $uuid ) );
		$request->set_body_params( array(
			'status' => $publish_status,
		) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'changeset_publish_unauthorized', $response, 403 );

		$manager = new WP_Customize_Manager();
		$this->assertFalse( get_post_status( $manager->find_changeset_post_id( $uuid ) ) );
	}

	/**
	 * Test that update_item() rejects publishing changesets if the user lacks capabilities.
	 *
	 * @dataProvider data_publish_changeset_status
	 *
	 * @param array $publish_status Status to publish the changeset.
	 */
	public function test_update_item_changeset_publish_unauthorized( $publish_status ) {
		// TODO: Allow the user to create changesets but not publish.
		wp_set_current_user( self::$subscriber_id );

		$manager = new WP_Customize_Manager();
		$manager->save_changeset_post();
		$status_before = get_post_status( $manager->changeset_post_id() );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/changesets/%s', $manager->changeset_uuid() ) );
		$request->set_body_params( array(
			'status' => $publish_status,
		) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'changeset_publish_unauthorized', $response, 403 );
		$this->assertSame( $status_before, get_post_status( $manager->changeset_post_id() ) );
	}

	/**
	 * "Publish (verb) changeset" statuses.
	 */
	public function data_publish_changeset_status() {
		return array(
			array( 'publish' ),
			array( 'future' ),
		);
	}

	/**
	 * Test that update_item() rejects updating a published changeset.
	 *
	 * @param string $published_status Published status.
	 * @dataProvider data_published_changeset_status
	 */
	public function test_update_item_changeset_already_published( $published_status ) {
		wp_set_current_user( self::$admin_id );

		$manager = new WP_Customize_Manager();
		$manager->save_changeset_post( array(
			'customize_changeset_data' => array(
				'basic_option' => array(
					'value' => 'Foo',
				),
			),
			'status' => $published_status,
		) );

		$changeset_data_before = $manager->changeset_data();

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/changesets/%s', $manager->changeset_uuid() ) );
		$request->set_body_params( array(
			'customize_changeset_data' => array(
				'basic_option' => array(
					'value' => 'Bar',
				),
			),
		) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'changeset_already_published', $response );
		$this->assertSame( $changeset_data_before, $manager->changeset_data() );
	}

	/**
	 * "Published" (noun) changeset statuses.
	 */
	public function data_published_changeset_status() {
		return array(
			array( 'publish' ),
			array( 'trash' ),
		);
	}

	/**
	 * Test that update_item() can create a changeset with a passed date.
	 */
	public function test_update_item_create_changeset_date() {
		wp_set_current_user( self::$admin_id );

		$uuid = wp_generate_uuid4();
		$date_gmt = date( 'Y-m-d H:i:s', ( time() + YEAR_IN_SECONDS ) );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/changesets/%s', $uuid ) );
		$request->set_body_params( array(
			'date_gmt' => $date_gmt,
			'status' => 'draft',
		) );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertSame( $data['date_gmt'], $date_gmt );

		$manager = new WP_Customize_Manager();
		$this->assertSame( get_post( $manager->find_changeset_post_id( $uuid ) )->post_date_gmt, $date_gmt );
	}

	/**
	 * Test that update_item() can edit a changeset with a passed date.
	 */
	public function test_update_item_edit_changeset_date() {
		wp_set_current_user( self::$admin_id );

		$manager = new WP_Customize_Manager();
		$manager->save_changeset_post();

		$date_before = get_post( $manager->changeset_post_id() )->post_date_gmt;
		$date_after = date( 'Y-m-d H:i:s', ( strtotime( $date_before ) + YEAR_IN_SECONDS ) );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/changesets/%s', $manager->changeset_uuid() ) );
		$request->set_body_params( array(
			'date_gmt' => $date_after,
			'status' => 'draft',
		) );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertSame( $data['date_gmt'], $date_after );
		$this->assertSame( get_post( $manager->changeset_post_id() )->post_date_gmt, $date_after );
	}

	/**
	 * Test that a update_item() can change a 'future' changeset to 'draft', keeping the date.
	 */
	public function test_update_item_future_to_draft_keeps_post_date() {
		wp_set_current_user( self::$admin_id );

		$future_date = date( 'Y-m-d H:i:s', strtotime( '+1 year' ) );

		$manager = new WP_Customize_Manager();
		$manager->save_changeset_post( array(
			'date_gmt' => $future_date,
			'status' => 'future',
		) );

		$status_after = 'draft';

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/changesets/%s', $manager->changeset_uuid() ) );
		$request->set_body_params( array(
			'status' => $status_after,
		) );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( $status_after, get_post_status( $manager->changeset_post_id() ) );
		$this->assertSame( $future_date, get_post( $manager->changeset_post_id() )->post_date );
	}

	/**
	 * Test that update_item() can schedule a changeset if it already has a date in the future.
	 */
	public function test_update_item_schedule_with_existing_future_date() {
		wp_set_current_user( self::$admin_id );

		$future_date = date( 'Y-m-d H:i:s', strtotime( '+1 year' ) );

		$manager = new WP_Customize_Manager();
		$manager->save_changeset_post( array(
			'date_gmt' => $future_date,
			'status' => 'draft',
		) );

		$status_after = 'future';

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/changesets/%s', $manager->changeset_uuid() ) );
		$request->set_body_params( array(
			'status' => $status_after,
		) );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( $status_after, get_post_status( $manager->changeset_post_id() ) );
		$this->assertSame( $future_date, get_post( $manager->changeset_post_id() )->post_date );
	}

	/**
	 * Test that publishing a future-dated changeset with update_item() resets the changeset date to now.
	 */
	public function test_update_item_publishing_resets_date() {
		wp_set_current_user( self::$admin_id );

		$this_year = date( 'Y' );

		$manager = new WP_Customize_Manager();
		$manager->save_changeset_post( array(
			'date_gmt' => ( strtotime( $this_year ) + YEAR_IN_SECONDS ),
			'status' => 'future',
		) );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/changesets/%s', $manager->changeset_uuid() ) );
		$request->set_body_params( array(
			'status' => 'publish',
		) );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertSame( $this_year, date( 'Y', strtotime( $data['date_gmt'] ) ) );
		$changeset_post = get_post( $manager->changeset_post_id() );
		$this->assertSame( $this_year, date( 'Y', strtotime( $changeset_post->post_date_gmt ) ) );
	}

	/**
	 * Test that update_item() rejects setting the date of a changeset to the past.
	 */
	public function test_update_item_not_future_date_with_past_date() {
		wp_set_current_user( self::$admin_id );

		$manager = new WP_Customize_Manager();
		$manager->save_changeset_post();

		$date_before = get_post( $manager->changeset_post_id() )->post_date_gmt;

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/changesets/%s', $manager->changeset_uuid() ) );
		$request->set_body_params( array(
			'date_gmt' => strtotime( '-1 week' ),
		) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'not_future_date', $response );
		$this->assertSame( $date_before, get_post( $manager->changeset_post_id() )->post_date_gmt );
	}

	/**
	 * Test that update_item() rejects scheduling a changeset when it has a past date.
	 */
	public function test_update_item_not_future_date_with_future_status() {
		wp_set_current_user( self::$admin_id );

		$manager = new WP_Customize_Manager();
		$manager->save_changeset_post( array(
			'date_gmt' => date( 'Y-m-d H:i:s', strtotime( '-1 year' ) ),
		) );

		$status_before = get_post_status( $manager->changeset_post_id() );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/changesets/%s', $manager->changeset_uuid() ) );
		$request->set_body_params( array(
			'status' => 'future',
		) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'not_future_date', $response );
		$this->assertSame( $status_before, get_post_status( $manager->changeset_post_id() ) );
	}

	/**
	 * Test that update_item() rejects setting a the date of an auto-draft changeset.
	 */
	public function test_update_item_cannot_supply_date_for_auto_draft_changeset() {
		wp_set_current_user( self::$admin_id );

		$uuid = wp_generate_uuid4();
		$manager = new WP_Customize_Manager( array(
			'changeset_uuid' => $uuid,
		) );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/changesets/%s', $uuid ) );
		$request->set_body_params( array(
			'date_gmt' => strtotime( '+1 week' ),
		) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'cannot_supply_date_for_auto_draft_changeset', $response );
		$this->assertEmpty( $manager->changeset_post_id() );
	}

	/**
	 * Test that update_item() rejects invalid changeset dates.
	 *
	 * TODO: Another test when the UUID is not saved to verify no post is created.
	 */
	public function test_update_item_bad_customize_changeset_date() {
		wp_set_current_user( self::$admin_id );

		$manager = new WP_Customize_Manager();
		$manager->save_changeset_post();

		$date_before = get_post( $manager->changeset_post_id() )->post_date_gmt;

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/changesets/%s', $manager->changeset_uuid() ) );
		$request->set_body_params( array(
			'date_gmt' => 'BAD DATE',
		) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bad_customize_changeset_date', $response, 400 );
		$this->assertSame( $date_before, get_post( $manager->changeset_post_id() )->post_date_gmt );
	}

	/**
	 * Test that publishing a changeset with update_item() returns a 'publish' status.
	 */
	public function test_update_item_status_is_publish_after_publish() {
		if ( post_type_supports( 'customize_changeset', 'revisions' ) ) {
			$this->markTestSkipped( 'Changesets are not trashed when revisions are enabled.' );
		}

		wp_set_current_user( self::$admin_id );

		$manager = new WP_Customize_Manager();
		$manager->save_changeset_post();

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/changesets/%s', $manager->changeset_uuid() ) );
		$request->set_body_params( array(
			'status' => 'publish',
		) );
		$response = $this->server->dispatch( $request );

		$data = $response->get_data();
		$this->assertSame( 'publish', $data['status'] );
	}

	/**
	 * Test that publishing a changeset with update_item() returns a new changeset UUID.
	 */
	public function test_update_item_has_next_changeset_id_after_publish() {
		wp_set_current_user( self::$admin_id );

		$uuid_before = wp_generate_uuid4();

		$manager = new WP_Customize_Manager( array(
			'changeset_uuid' => $uuid_before,
		) );
		$manager->save_changeset_post();

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/changesets/%s', $uuid_before ) );
		$request->set_body_params( array(
			'status' => 'publish',
		) );
		$response = $this->server->dispatch( $request );

		$data = $response->get_data();
		$this->assertTrue( isset( $data['next_changeset_uuid'] ) );
		$this->assertNotSame( $data['next_changeset_uuid'], $uuid_before );
	}

	/**
	 * Test that publishing a changeset with update_item() updates a valid setting.
	 */
	public function test_update_item_with_bloginfo() {
		wp_set_current_user( self::$admin_id );

		$blogname_after = get_option( 'blogname' ) . ' Amended';

		$manager = new WP_Customize_Manager();
		$manager->save_changeset_post( array(
			'data' => array(
				'blogname' => array(
					'value' => $blogname_after,
				),
			),
		) );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/changesets/%s', $manager->changeset_uuid() ) );
		$request->set_body_params( array(
			'status' => 'publish',
		) );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( $blogname_after, get_option( 'blogname' ) );
	}

	/**
	 * Test that update_item() returns setting validities.
	 */
	public function test_update_item_setting_validities() {
		wp_set_current_user( self::$admin_id );

		$bad_setting = rand_str();

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/changesets/%s', wp_generate_uuid4() ) );
		$request->set_body_params( array(
			'customize_changeset_data' => array(
				$bad_setting => array(
					'value' => 'Foo',
				),
			),
		) );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertTrue( isset( $data['setting_validities'][ $bad_setting ]['unrecognized'] ) );
	}

	/**
	 * Test that using update_item() to transactionally update a changeset fails when settings are invalid.
	 */
	public function test_update_item_transaction_fail_setting_validities() {
		wp_set_current_user( self::$admin_id );

		$illegal_setting = 'foo_illegal';
		add_filter( "customize_validate_{$illegal_setting}", array( $this, '__return_error_illegal' ) );

		$manager = new WP_Customize_Manager();

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/changesets/%s', $manager->changeset_uuid() ) );
		$request->set_body_params( array(
			'customize_changeset_data' => array(
				$illegal_setting => array(
					'value' => 'Foo',
				),
			),
		) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'transaction_fail', $response );

		$error_data = $response->as_error()->get_error_data();
		$illegal_code = $this->__return_error_illegal()->get_error_code();
		$this->assertNotEmpty( $error_data['setting_validities'][ $illegal_setting ][ $illegal_code ] );
	}

	/**
	 * Test that update_item() reports errors inserting or updating a changeset.
	 */
	public function test_update_item_changeset_post_save_failure() {
		wp_set_current_user( self::$admin_id );

		add_filter( 'wp_insert_post_empty_content', '__return_true' );

		$manager = new WP_Customize_Manager();

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/changesets/%s', $manager->changeset_uuid() ) );
		$request->set_body_params( array(
			'status' => 'draft',
		) );
		$response = $this->server->dispatch( $request );

		$error_code = 'changeset_post_save_failure';

		$this->assertErrorResponse( $error_code, $response );

		$error_data = $response->as_error()->get_error_data();
		$this->assertSame( 'empty_content', $error_data[ $error_code ][ $error_code ] );
	}

	/**
	 * Test delete_item.
	 *
	 * @covers WP_REST_Customize_Changesets_Controller::delete_item()
	 */
	public function test_delete_item() {
		wp_set_current_user( self::$admin_id );

		$uuid = wp_generate_uuid4();

		$changeset_id = self::factory()->post->create( array(
			'post_name' => $uuid,
			'post_type' => 'customize_changeset',
		) );

		$manager = new WP_Customize_Manager();

		$this->assertSame( $changeset_id, $manager->find_changeset_post_id( $uuid ) );

		$request = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/changesets/%s', $uuid ) );
		$request->set_param( 'force', false );
		$response = $this->server->dispatch( $request );

		$this->assertNotInstanceOf( 'WP_Error', $response->as_error() );
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertSame( 'trash', $data['status'] );

		$this->assertSame( 'trash', get_post_status( $manager->find_changeset_post_id( $uuid ) ) );
	}

	/**
	 * Test delete_item() by a user without capabilities.
	 */
	public function test_delete_item_without_permission() {
		wp_set_current_user( self::$subscriber_id );

		$uuid = wp_generate_uuid4();

		$changeset_id = self::factory()->post->create( array(
			'post_name' => $uuid,
			'post_type' => 'customize_changeset',
		) );

		$manager = new WP_Customize_Manager();

		$this->assertSame( $changeset_id, $manager->find_changeset_post_id( $uuid ) );

		$request = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/changesets/%s', $uuid ) );
		$request->set_param( 'force', false );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_cannot_delete', $response, 403 );

		$this->assertNotSame( 'trash', get_post_status( $manager->find_changeset_post_id( $uuid ) ) );
	}

	/**
	 * Test delete_item() with `$force = true`.
	 */
	public function test_force_delete_item() {
		wp_set_current_user( self::$admin_id );

		$uuid = wp_generate_uuid4();

		$changeset_id = self::factory()->post->create( array(
			'post_name' => $uuid,
			'post_type' => 'customize_changeset',
		) );

		$manager = new WP_Customize_Manager();

		$this->assertSame( $changeset_id, $manager->find_changeset_post_id( $uuid ) );

		$request = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/changesets/%s', $uuid ) );
		$request->set_param( 'force', true );
		$response = $this->server->dispatch( $request );

		$this->assertNotInstanceOf( 'WP_Error', $response->as_error() );
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertTrue( $data['deleted'] );
		$this->assertNotEmpty( $data['previous'] );

		$this->assertNull( $manager->find_changeset_post_id( $uuid ) );
	}


	/**
	 * Test delete_item() with `$force = true` by a user without capabilities.
	 */
	public function test_force_delete_item_without_permission() {
		wp_set_current_user( self::$subscriber_id );

		$uuid = wp_generate_uuid4();

		$changeset_id = self::factory()->post->create( array(
			'post_name' => $uuid,
			'post_type' => 'customize_changeset',
		) );

		$manager = new WP_Customize_Manager();

		$this->assertSame( $changeset_id, $manager->find_changeset_post_id( $uuid ) );

		$request = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/changesets/%s', $uuid ) );
		$request->set_param( 'force', true );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_cannot_delete', $response, 403 );
		$this->assertSame( $changeset_id, $manager->find_changeset_post_id( $uuid ) );
	}

	/**
	 * Test delete_item() where the item is already in the trash.
	 */
	public function test_delete_item_already_trashed() {
		wp_set_current_user( self::$admin_id );

		$uuid = wp_generate_uuid4();

		$changeset_id = self::factory()->post->create( array(
			'post_name'   => $uuid,
			'post_type'   => 'customize_changeset',
		) );

		$manager = new WP_Customize_Manager();
		$this->assertSame( $changeset_id, $manager->find_changeset_post_id( $uuid ) );

		$request = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/changesets/%s', $uuid ) );
		$request->set_param( 'force', false );

		$response = $this->server->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_already_trashed', $response, 410 );
	}

	/**
	 * Test delete_item() by a user without capabilities where the item is already in the trash.
	 */
	public function test_delete_item_already_trashed_without_permission() {
		wp_set_current_user( self::$subscriber_id );

		$uuid = wp_generate_uuid4();

		$changeset_id = self::factory()->post->create( array(
			'post_name'   => $uuid,
			'post_type'   => 'customize_changeset',
		) );

		wp_trash_post( $changeset_id );

		$request = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/changesets/%s', $uuid ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_cannot_delete', $response, 403 );
	}

	/**
	 * Test delete_item with an invalid changeset ID.
	 */
	public function test_delete_item_invalid_id() {
		wp_set_current_user( self::$admin_id );
		$request = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/changesets/%s', wp_generate_uuid4() ) );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_post_invalid_id', $response, 404 );
	}

	/**
	 * Test delete_item by a user without capabilities with an invalid changeset ID.
	 */
	public function test_delete_item_invalid_id_without_permission() {
		wp_set_current_user( self::$subscriber_id );
		$request = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/changesets/%s', wp_generate_uuid4() ) );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_delete', $response, 403 );
	}

	/**
	 * Test prepare_item(_for_response).
	 *
	 * @covers WP_REST_Customize_Changesets_Controller::prepare_item_for_response()
	 */
	public function test_prepare_item() {
		$this->markTestIncomplete();
	}
}
