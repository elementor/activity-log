<?php

class AAL_Test_Action_Label extends WP_UnitTestCase {

	public function test_get_action_label_untranslated() {
		$this->assertSame( 'Logged In', AAL_Log_Presenter::get_action_label( 'logged_in' ) );
	}

	public function test_get_action_label_translated() {
		$filter = function ( $translated, $text, $domain ) {
			if ( 'trashed' === $text && 'aryo-activity-log' === $domain ) {
				return 'movido para o lixo';
			}

			return $translated;
		};

		add_filter( 'gettext', $filter, 10, 3 );

		$this->assertSame( 'Movido para o lixo', AAL_Log_Presenter::get_action_label( 'trashed' ) );

		remove_filter( 'gettext', $filter, 10 );
	}
}
