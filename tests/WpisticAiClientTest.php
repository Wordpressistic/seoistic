<?php

use Wpistic\Seoistic\Core\AI\WpisticAiClient;
use Wpistic\Seoistic\Core\Crypto;

class WpisticAiClientTest extends PHPUnit\Framework\TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['seoistic_test_options'] = array();
        $GLOBALS['seoistic_test_responses'] = array();
        $GLOBALS['seoistic_test_transients'] = array();
        update_option('seoistic_license_key', Crypto::encrypt('synthetic-license-key', 'license'));
        update_option('seoistic_ai_options', array('enabled' => true));
    }

    public function testMirrorsSpecCreditCosts(): void
    {
        $this->assertSame(array(
            'title' => 1,
            'description' => 1,
            'keywords' => 1,
            'alt' => 1,
            'optimize_content' => 3,
            'full_optimize' => 5,
            'schema' => 2,
            'aeo_audit' => 10,
        ), WpisticAiClient::CREDIT_COSTS);
    }

    public function testCacheHitCostsZeroAndDoesNotRepeatTransport(): void
    {
        $GLOBALS['seoistic_test_responses'] = array(
            array('status' => 200, 'body' => array('data' => '{"title":"Cached title"}', 'credits' => array('left' => 29, 'charged' => 1))),
        );
        $client = new WpisticAiClient();
        $first = $client->generate('title', array('title' => 'Test', 'url' => 'https://example.com'));
        $this->assertIsArray($first);
        $this->assertFalse($first['cached']);
        $second = $client->generate('title', array('title' => 'Test', 'url' => 'https://example.com'));
        $this->assertCount(2, WpisticAiClient::usage_snapshot()['recent']);
        $this->assertSame(0, end(WpisticAiClient::usage_snapshot()['recent'])['credits']);
        $this->assertIsArray($second);
        $this->assertTrue($second['cached']);
        $this->assertSame('{"title":"Cached title"}', $second['data']);
    }

    public function testRateLimitRetriesOnce(): void
    {
        $GLOBALS['seoistic_test_responses'] = array(
            array('status' => 429, 'body' => array()),
            array('status' => 200, 'body' => array('data' => '{"title":"Retried"}')),
        );
        $result = (new WpisticAiClient())->generate('title', array('title' => 'Test'));
        $this->assertIsArray($result);
        $this->assertSame('{"title":"Retried"}', $result['data']);
    }

    public function testInsufficientCreditsReturnsFriendlyErrorContract(): void
    {
        $GLOBALS['seoistic_test_responses'] = array(
            array('status' => 402, 'body' => array('credits_left' => 0, 'plan' => 'free')),
        );
        $result = (new WpisticAiClient())->generate('title', array('title' => 'Test'));
        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('seoistic_insufficient_credits', $result->get_error_code());
        $data = $result->get_error_data();
        $this->assertSame(402, $data['status']);
        $this->assertTrue($data['upgrade_card']);
    }

}
