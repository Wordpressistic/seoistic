<?php
/**
 * Local dev overrides for the SEOistic docker test stack.
 *
 * Loaded automatically (mu-plugin). Routes the metered AI gateway to the
 * mock WPistic platform container instead of https://ai.wpistic.com so the
 * full license → gateway → credits pipeline can be exercised offline.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter(
	'seoistic/ai/gateway_url',
	static function () {
		return 'http://mock:8088/v1/license/chat';
	}
);
