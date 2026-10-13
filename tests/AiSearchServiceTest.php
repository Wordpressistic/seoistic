<?php

use Wpistic\Seoistic\AI\AiService;
use Wpistic\Seoistic\AiSearch\AeoAuditService;
use Wpistic\Seoistic\AiSearch\AiCrawlerStore;
use Wpistic\Seoistic\Core\LlmsTxt;

class AiSearchServiceTest extends PHPUnit\Framework\TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['seoistic_test_options'] = array();
        $GLOBALS['seoistic_test_transients'] = array();
        $GLOBALS['seoistic_test_responses'] = array();
        $GLOBALS['seoistic_test_posts'] = array();
        $GLOBALS['seoistic_test_post_meta'] = array();
        $GLOBALS['seoistic_test_user_agent'] = 'GPTBot/1.0';
    }

    public function testDetectsAllSpecCrawlers(): void
    {
        $store = new AiCrawlerStore();
        $this->assertSame('GPTBot', $store->bot_from_user_agent('Mozilla/5.0 GPTBot/1.0'));
        $this->assertSame('ClaudeBot', $store->bot_from_user_agent('ClaudeBot/1.0'));
        $this->assertSame('PerplexityBot', $store->bot_from_user_agent('PerplexityBot/1.0'));
        $this->assertSame('Google-Extended', $store->bot_from_user_agent('Google-Extended'));
        $this->assertSame('CCBot', $store->bot_from_user_agent('CCBot/2.0'));
        $this->assertNull($store->bot_from_user_agent('Googlebot/2.1'));
    }

    public function testStoresBoundedTrendAndMostCrawledUrls(): void
    {
        $store = new AiCrawlerStore();
        $record = new ReflectionMethod($store, 'record');
        $record->setAccessible(true);
        $record->invoke($store, 'GPTBot', 'https://example.com/guide/?utm_source=test');
        $record->invoke($store, 'GPTBot', 'https://www.example.com/guide/?utm_source=test');
        $record->invoke($store, 'ClaudeBot', 'https://example.com/other/');

        $dashboard = $store->dashboard();
        $this->assertSame(3, $dashboard['total']);
        $this->assertSame('GPTBot', $dashboard['bots'][0]['bot']);
        $this->assertSame(2, $dashboard['urls'][0]['visits']);
        $this->assertSame('https://example.com/guide?utm_source=test', $dashboard['urls'][0]['url']);
    }

    public function testLlmsBlueprintRendersAndSanitizes(): void
    {
        $blueprint = array(
            'title' => 'Example <strong>Site</strong>',
            'description' => 'Example entity',
            'sections' => array(
                array(
                    'id' => 'section',
                    'title' => 'Key resources',
                    'description' => 'Primary sources',
                    'links' => array(
                        array('text' => 'Guide', 'url' => 'https://example.com/guide/'),
                        array('text' => 'Unsafe', 'url' => 'javascript:alert(1)'),
                    ),
                ),
            ),
        );

        $content = LlmsTxt::render_blueprint($blueprint);
        $this->assertStringContainsString('# Example Site', $content);
        $this->assertStringContainsString('## Key resources', $content);
        $this->assertStringContainsString('[Guide](https://example.com/guide/)', $content);
        $this->assertStringNotContainsString('javascript:', $content);
    }

    public function testAeoAuditStoresGatewayScoreAndDeterministicChecks(): void
    {
        $post = new WP_Post();
        $post->ID = 24;
        $post->post_title = 'AI visibility guide';
        $post->post_content = '<h2>What is AEO?</h2><p>Example is a search product: its direct answer explains key facts clearly for readers.</p><h3>FAQ</h3>';
        $post->post_modified_gmt = gmdate('Y-m-d H:i:s', time() - WEEK_IN_SECONDS);
        $GLOBALS['seoistic_test_posts'][24] = $post;
        update_option('seoistic_license_key', Wpistic\Seoistic\Core\Crypto::encrypt('license', 'license'));
        update_option('seoistic_ai_options', array('enabled' => true));
        $GLOBALS['seoistic_test_responses'] = array(
            array('status' => 200, 'body' => array('data' => '{"score":92,"suggestions":["Add one concise FAQ answer."]}', 'credits' => array('left' => 90, 'charged' => 10))),
        );

        $service = new AeoAuditService(new AiService());
        $result = $service->audit(24);
        $this->assertIsArray($result);
        $this->assertSame(92, $result['data']['score']);
        $this->assertSame(92, (int) get_post_meta(24, AeoAuditService::META_SCORE, true));
        $this->assertTrue($result['data']['checks']['answer_first']);
        $this->assertTrue($result['data']['checks']['faq_presence']);
        $this->assertTrue($result['data']['checks']['freshness']);
        $this->assertContains('Add one concise FAQ answer.', $result['data']['suggestions']);
    }
}
