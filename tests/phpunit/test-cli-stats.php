<?php

class AAL_Test_CLI_Stats extends WP_UnitTestCase {

	public function test_build_label_count_rows_maps_counts() {
		$rows = AAL_CLI_Stats::build_label_count_rows(
			array(
				'Posts' => 2,
				'Users' => 1,
			),
			AAL_CLI_Stats::OBJECT_TYPE_FIELD
		);

		$this->assertSame(
			array(
				array(
					AAL_CLI_Stats::OBJECT_TYPE_FIELD    => 'Posts',
					AAL_CLI_Stats::COUNT_ROW_FIELD_COUNT => 2,
				),
				array(
					AAL_CLI_Stats::OBJECT_TYPE_FIELD    => 'Users',
					AAL_CLI_Stats::COUNT_ROW_FIELD_COUNT => 1,
				),
			),
			$rows
		);
	}

	public function test_build_top_users_rows_maps_fields() {
		$rows = AAL_CLI_Stats::build_top_users_rows(
			array(
				array(
					'user_id' => 7,
					'name'    => 'editor-one',
					'count'   => 3,
				),
			)
		);

		$this->assertSame(
			array(
				array(
					AAL_CLI_Stats::TOP_USER_FIELD_USER_ID => 7,
					AAL_CLI_Stats::TOP_USER_FIELD_NAME    => 'editor-one',
					AAL_CLI_Stats::TOP_USER_FIELD_COUNT   => 3,
				),
			),
			$rows
		);
	}

	public function test_fetch_stats_rejects_period_with_date_range() {
		$result = AAL_CLI_Stats::fetch_stats(
			array(
				'period'    => '24h',
				'date-from' => '2026-03-10',
				'date-to'   => '2026-03-11',
			)
		);

		$this->assertWPError( $result );
		$this->assertSame( 'ability_invalid_input', $result->get_error_code() );
	}
}
