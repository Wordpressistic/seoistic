<?php

use Wpistic\Seoistic\Core\Crypto;
use Wpistic\Seoistic\Gsc\GscClient;
use Wpistic\Seoistic\Gsc\GscSettings;

class GscClientTest extends PHPUnit\Framework\TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['seoistic_test_options'] = array();
        $GLOBALS['seoistic_test_transients'] = array();
        $GLOBALS['seoistic_test_responses'] = array();
        GscSettings::save_client('client-id', 'client-secret');
        GscSettings::set_refresh_token('old-refresh');
        GscSettings::save_site_url('https://example.com/');
    }

    public function testDetectsAccessDeniedFromGoogleErrorShape(): void
    {
        $GLOBALS['seoistic_test_responses'] = array(
            array('status' => 403, 'body' => array('error' => array('code' => 403, 'message' => 'Permission denied', 'errors' => array(array('reason' => 'accessDenied'))))),
        );
        set_transient('seoistic_gsc_access_token', 'cached');

        $result = (new GscClient())->list_sites();
        $this->assertFalse($result['success']);
        $this->assertSame('access_denied', $result['error_code']);
        $this->assertTrue($result['access_denied']);
    }

    public function testRefreshesRotatedTokenAndRetriesOnceOn401(): void
    {
        $GLOBALS['seoistic_test_responses'] = array(
            array('status' => 401, 'body' => array('error' => 'invalid_token')),
            array('status' => 200, 'body' => array('access_token' => 'rotated', 'refresh_token' => 'new-refresh', 'expires_in' => 3600)),
            array('status' => 200, 'body' => array('siteEntry' => array(array('siteUrl' => 'https://example.com/', 'permissionLevel' => 'siteOwner')))),
        );
        set_transient('seoistic_gsc_access_token', 'stale');

        $result = (new GscClient())->list_sites();
        $this->assertTrue($result['success']);
        $this->assertSame('rotated', get_transient('seoistic_gsc_access_token'));
        $this->assertSame('new-refresh', GscSettings::refresh_token());
    }

    public function testHealthCheckReportsSchemeAndHostMismatch(): void
    {
        GscSettings::save_site_url('https://sub.example.com/');
        $GLOBALS['seoistic_test_responses'] = array(
            array('status' => 200, 'body' => array('siteEntry' => array(array('siteUrl' => 'https://sub.example.com/', 'permissionLevel' => 'siteOwner')))),
        );
        set_transient('seoistic_gsc_access_token', 'cached');

        $result = (new GscClient())->test_connection();
        $this->assertFalse($result['success']);
        $this->assertFalse($result['data']['property_match']);
        $this->assertFalse(GscSettings::property_match());
    }

    public function testHealthCheckAcceptsExactUrlPrefixProperty(): void
    {
        $GLOBALS['seoistic_test_responses'] = array(
            array('status' => 200, 'body' => array('siteEntry' => array(array('siteUrl' => 'https://example.com/', 'permissionLevel' => 'siteOwner')))),
        );
        set_transient('seoistic_gsc_access_token', 'cached');

        $result = (new GscClient())->test_connection();
        $this->assertTrue($result['success']);
        $this->assertTrue($result['data']['property_match']);
    }

    public function testHealthCheckAcceptsMatchingDomainProperty(): void
    {
        GscSettings::save_site_url('sc-domain:example.com');
        $GLOBALS['seoistic_test_responses'] = array(
            array('status' => 200, 'body' => array('siteEntry' => array(array('siteUrl' => 'sc-domain:example.com', 'permissionLevel' => 'siteOwner')))),
        );
        set_transient('seoistic_gsc_access_token', 'cached');

        $result = (new GscClient())->test_connection();
        $this->assertTrue($result['success']);
        $this->assertTrue($result['data']['property_match']);
        $this->assertTrue(GscSettings::property_match());
    }
}
