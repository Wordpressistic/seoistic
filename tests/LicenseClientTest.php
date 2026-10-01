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

	public function testCanonicalActivationStoresEncryptedTokenAndUsesLiveRoute(): void
	{
		$GLOBALS['seoistic_test_responses'] = array(
			array(
				'status' => 200,
				'body'   => array(
					'valid'            => true,
					'status'           => 'active',
					'product'          => 'seoistic',
					'plan'             => 'agency',
					'expires_at'       => '2027-12-31 23:59:59',
					'activation_token' => 'canonical-token-1234567890',
					'verification_key' => 'canonical-verify-1234567890',
				),
			),
		);

		$client = new LicenseClient();
		$result = $client->activate('seoistic_test_key');

		$this->assertTrue($result['success']);
		$this->assertSame('https://api.wpistic.com/api/v1/licenses/activate', $GLOBALS['seoistic_test_last_request']['url']);
		$body = json_decode($GLOBALS['seoistic_test_last_request']['args']['body'], true);
		$this->assertSame('seoistic_test_key', $body['key']);
		$this->assertSame('example.com', $body['domain']);
		$this->assertSame('production', $body['environment']);
		$this->assertSame('uuid', $body['installation_uuid']);
		$this->assertSame('active', $client->status());
		$this->assertSame('agency', $client->meta()['plan']);
		$this->assertNotSame('canonical-token-1234567890', get_option('seoistic_license_activation_token'));
		$this->assertTrue($client->is_valid());
	}

	public function testExposesOnlyTheStoredActivationContextForFirstPartyServices(): void
	{
		update_option('seoistic_license_activation_token', Wpistic\Seoistic\Core\Crypto::encrypt('canonical-token-1234567890', 'license_activation_token'));

		$client = new LicenseClient();
		$this->assertSame('canonical-token-1234567890', $client->activation_token());
		$this->assertSame('example.com', $client->domain());
		$this->assertSame('production', $client->environment());
		$this->assertSame('uuid', $client->installation_uuid());
	}
}
