<?php

use Wpistic\Seoistic\BusinessAutomator\RecipeEngine;

final class BusinessAutomatorRecipeEngineTest extends PHPUnit\Framework\TestCase
{
    protected function setUp(): void
    {
        global $GLOBALS;
        $GLOBALS['seoistic_test_options'] = array();
        $GLOBALS['seoistic_test_transients'] = array();
        $GLOBALS['seoistic_test_post_meta'] = array();
        $GLOBALS['seoistic_test_posts'] = array();
        $GLOBALS['seoistic_test_mail'] = array();
    }

    public function testProvidesFiveStarterRecipesWithApprovalDefaults(): void
    {
        $engine = $this->engine();
        $this->assertSame(array(
            'weekly_audit_report',
            'new_post_seo_polish',
            'freshness_monitor',
            'schema_reminder',
            'llms_txt_refresh',
        ), array_keys($engine->starter_recipes()));
        foreach ($engine->starter_recipes() as $recipe) {
            $this->assertFalse($recipe['auto_apply']);
        }
    }

    public function testLlmsRefreshRequiresApprovalBeforeWriteAndRecordsDiffAndAudit(): void
    {
        $engine = $this->engine();
        $result = $engine->run('llms_txt_refresh', 'schedule');
        $this->assertSame('awaiting_approval', $result['status']);
        $run = $engine->run_by_id($result['run_id']);
        $this->assertSame('awaiting_approval', $run['status']);
        $this->assertStringContainsString('# Testing SEOistic', (string) $run['draft']['llms_txt']);
        $this->assertSame('', (string) get_option('seoistic_llms_txt_content', ''));

        $approved = $engine->approve($result['run_id'], 42);
        $this->assertIsArray($approved);
        $this->assertSame('completed', $approved['status']);
        $this->assertSame($run['draft']['llms_txt'], (string) get_option('seoistic_llms_txt_content'));
        $approval = $approved['steps'][1];
        $this->assertSame('approved', $approval['status']);
        $this->assertSame('user:42', $approval['audit']['approved_by']);
        $this->assertArrayHasKey('changed_at', $approved['steps'][3]['data']['audit']);
    }

    public function testParallelRunIsRejectedByTransientLock(): void
    {
        set_transient(RecipeEngine::LOCK_TRANSIENT, 'already-running');
        $engine = $this->engine();
        $this->assertSame(array('run_id' => '', 'status' => 'locked'), $engine->run('llms_txt_refresh', 'schedule'));
    }

    public function testHistoryIsCapped(): void
    {
        $runs = array();
        for ($index = 0; $index < 60; $index++) {
            $runs[] = array('run_id' => 'run-' . $index, 'steps' => array());
        }
        update_option(RecipeEngine::HISTORY_OPTION, $runs);
        $method = new ReflectionMethod(RecipeEngine::class, 'persist');
        $method->setAccessible(true);
        $method->invoke($this->engine(), array('run_id' => 'new-run', 'steps' => array()));
        $this->assertCount(RecipeEngine::MAX_HISTORY, (new RecipeEngine())->history());
        $this->assertSame('new-run', (new RecipeEngine())->history()[0]['run_id']);
    }

    private function engine(): RecipeEngine
    {
        return new RecipeEngine(null, fn(string $key): string => '2026-09-11T10:00:00+00:00');
    }
}
