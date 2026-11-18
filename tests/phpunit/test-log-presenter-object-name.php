<?php

class AAL_Test_Log_Presenter_Object_Name extends WP_UnitTestCase {

	private function make_item( $object_name ) {
		return (object) array(
			'histid'           => 1,
			'hist_time'        => time(),
			'user_id'          => 1,
			'user_caps'        => 'administrator',
			'action'           => 'updated',
			'object_type'      => 'Posts',
			'object_subtype'   => 'post',
			'object_name'      => $object_name,
			'object_id'        => 1,
			'request_source'   => '',
			'meta'             => '',
			'hist_ip'          => '',
		);
	}

	/**
	 * @dataProvider legacy_encoded_object_name_provider
	 */
	public function test_legacy_html_entity_object_names_decode_in_outputs( $encoded, $expected ) {
		$item = $this->make_item( $encoded );

		$json = AAL_Log_Presenter::to_json( $item, false );
		$this->assertSame( $expected, $json['description']['text'] );

		$export = AAL_Log_Presenter::to_export_row( $item, array( 'description' => 'Description' ) );
		$this->assertSame( $expected, $export['description'] );

		$ability = AAL_Log_Presenter::to_ability_json( $item );
		$this->assertSame( $expected, $ability['object_name'] );
	}

	public function legacy_encoded_object_name_provider() {
		return array(
			'ampersand'  => array( 'Virtualization &#038; Containerization', 'Virtualization & Containerization' ),
			'apostrophe' => array( 'Ministère de l&#8217;Intérieur', "Ministère de l'Intérieur" ),
		);
	}
}