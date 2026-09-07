<?php

use Wpistic\Seoistic\License\LicenseClient;
use Wpistic\Seoistic\License\Plans;
use Wpistic\Seoistic\Module\Entitlement;

class LicenseClientTest extends PHPUnit\Framework\TestCase
{
	protected function setUp(): void
	{
		$GLOBALS['seoistic_test_options'] = array();
		update_option('seoistic_license_key', Wpistic\Seoistic\Core\Crypto::encrypt('synthetic-license-key', 'license'));
		update_option('seoistic_license_last_ok', time());
	}
	public function testPlanFallsBackToCanonicalNames(): void
	{
		$this->assertSame('pro', Plans::normalize_plan('starter'));
		$this->assertSame('business', Plans::normalize_plan('professional'));
	}

	public function testEntitlementUsesCanonicalPlan(): void
	{
		update_option('seoistic_license_status', 'active');
		update_option('seoistic_license_meta', array('plan' => 'starter'));
		update_option('seoistic_license_product_active', 0);
		$this->assertSame('pro', Entitlement::plan());
	}
}
