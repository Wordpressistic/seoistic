<?php

use Wpistic\Seoistic\RankTracker\RankTrackerRepository;
use Wpistic\Seoistic\RankTracker\RankTrackerService;

final class RankTrackerServiceTest extends PHPUnit\Framework\TestCase
{
    private RankTrackerService $service;
    private RankTrackerRepository $repository;

    protected function setUp(): void
    {
        global $wpdb;
        $GLOBALS['seoistic_test_options'] = array();
        $GLOBALS['seoistic_test_transients'] = array();
        $wpdb = new RankTrackerTestWpdb();
        $this->repository = new RankTrackerRepository();
        $this->service = new RankTrackerService($this->repository);
    }

    public function testSearchBatchChecksOnlyDueKeywordsAndRecordsPosition(): void
    {
        $first = $this->repository->save_keyword('due keyword', 'en-US', 'desktop');
        $second = $this->repository->save_keyword('already checked', 'en-US', 'desktop');
        $this->repository->record_position($second, 3, 'https://example.com/checked/', current_time('mysql', true), true);
        // Yesterday data establishes history without making today's row due.
        $this->repository->record_position($first, 12, 'https://example.com/yesterday/', '2026-09-10 10:00:00');
        $payloads = array();
        $this->service->set_test_dependencies(static function (array $payload) use (&$payloads) {
            $payloads[] = $payload;
            return array('results' => array(array('keyword' => 'due keyword', 'position' => 6, 'url' => 'https://example.com/')));
        });

        $result = $this->service->check_search_api_batch(10);

        $this->assertSame(array('checked' => 1, 'remaining' => 0, 'mode' => 'search_api'), $result);
        $this->assertSame(array('due keyword'), $payloads[0]['keywords']);
        $history = array_values(array_filter($this->repository->history($first), static fn(array $row): bool => 'https://example.com/' === $row['url']));
        $this->assertSame(6.0, $history[0]['position'] ?? null);
        $this->assertSame('https://example.com/', $history[0]['url']);
    }

    public function testDailyRunIsLocked(): void
    {
        set_transient(RankTrackerService::LOCK_TRANSIENT, gmdate('c'));
        $result = $this->service->run_daily(true);
        $this->assertSame('seoistic_rank_tracker_locked', $result->get_error_code());
    }

    public function testGscImportStoresDelayedRows(): void
    {
        $this->service->set_test_dependencies(null, static function () {
            return array('success' => true, 'data' => array(array('keys' => array('dashboard plugin'), 'position' => 4.2)));
        });
        $result = $this->service->import_gsc(1);
        $this->assertSame(1, $result['checked']);
        $rows = $this->service->table_rows('en-US', 'desktop');
        $this->assertSame('Search Console data (delayed)', $rows[0]['source_label']);
        $this->assertSame(4.2, $rows[0]['current']);
    }

    public function testNormalizeAcceptsCompactAndWrappedResponses(): void
    {
        $service = new RankTrackerService();
        $this->assertSame(array(
            'red widgets' => array('position' => 2.0, 'url' => 'https://example.com/red/'),
        ), $service->normalize_search_results(array('data' => array('results' => array(array('query' => 'red widgets', 'rank' => 2, 'url' => 'https://example.com/red/'))))));
    }
}

final class RankTrackerTestWpdb {
    public $prefix = 'wp_';
    public $insert_id = 0;
    public $last_error = '';
    public array $keywords = array();
    public array $positions = array();
    private int $sequence = 0;

    public function prepare($query, ...$args)
    {
        if (1 === count($args) && is_array($args[0])) {
            $args = $args[0];
        }
        preg_match_all('/%[sd]/', $query, $matches, PREG_OFFSET_CAPTURE);
        $count = count($matches[0]);
        for ($index = $count - 1; $index >= 0; $index--) {
            $value = $args[$index] ?? '';
            $replacement = is_int($value) ? (string) $value : "'" . addslashes((string) $value) . "'";
            $query = substr_replace($query, $replacement, (int) $matches[0][$index][1], 2);
        }
        return $query;
    }

    public function get_row($query, $output = OBJECT)
    {
        if (str_contains($query, 'seoistic_keywords') && str_contains($query, 'WHERE keyword =')) {
            foreach ($this->keywords as $keyword) {
                if (str_contains($query, addslashes($keyword['keyword'])) && str_contains($query, $keyword['locale']) && str_contains($query, $keyword['device'])) {
                    return array('id' => $keyword['id']);
                }
            }
            return null;
        }
        if (str_contains($query, 'seoistic_positions') && str_contains($query, 'DATE(checked_at)')) {
            $keywordId = (int) filter_var(substr($query, 0, strpos($query, "AND DATE")), FILTER_SANITIZE_NUMBER_INT);
            foreach ($this->positions as $position) {
                if ((int) $position['keyword_id'] === $keywordId && substr($position['checked_at'], 0, 10) === substr((string) $this->extractDate($query), 0, 10)) {
                    return array('id' => $position['id']);
                }
            }
            return null;
        }
        return null;
    }

    private function extractDate($query)
    {
        preg_match("/DATE\('([0-9-]+ [0-9:]+)'\)/", $query, $matches);
        return $matches[1] ?? '';
    }

    public function get_results($query, $output = OBJECT)
    {
        if (str_contains($query, 'LEFT JOIN')) {
            $today = substr(current_time('mysql', true), 0, 10);
            return array_values(array_filter($this->keywords, function (array $keyword) use ($today) {
                foreach ($this->positions as $position) {
                    if ((int) $position['keyword_id'] === (int) $keyword['id'] && substr($position['checked_at'], 0, 10) === $today) {
                        return false;
                    }
                }
                return true;
            }));
        }

        $rows = array();
        if (str_contains($query, 'FROM wp_seoistic_keywords')) {
            $rows = $this->keywords;
        } elseif (str_contains($query, 'FROM wp_seoistic_positions')) {
            preg_match('/keyword_id = ([0-9]+)/', $query, $matches);
            $keywordId = (int) ($matches[1] ?? 0);
            $rows = array_values(array_filter($this->positions, static function (array $position) use ($keywordId): bool {
                return (int) $position['keyword_id'] === $keywordId;
            }));
        }
        return $rows;
    }

    public function insert($table, array $data)
    {
        $this->sequence++;
        $data['id'] = $this->sequence;
        $this->insert_id = $this->sequence;
        if (str_ends_with($table, 'keywords')) {
            $this->keywords[] = $data;
        } else {
            $this->positions[] = $data;
        }
        return 1;
    }

    public function update($table, array $data, array $where)
    {
        $source = str_ends_with($table, 'keywords') ? $this->keywords : $this->positions;
        foreach ($source as $index => $row) {
            if ((int) $row['id'] === (int) reset($where)) {
                $data['id'] = $row['id'];
                if (str_ends_with($table, 'keywords')) {
                    $this->keywords[$index] = $data;
                } else {
                    $this->positions[$index] = $data;
                }
                return 1;
            }
        }
        return 0;
    }

    public function delete($table, array $where)
    {
        if (str_ends_with($table, 'keywords')) {
            $this->keywords = array_values(array_filter($this->keywords, static fn(array $row): bool => (int) $row['id'] !== (int) reset($where)));
            return 1;
        }
        $this->positions = array_values(array_filter($this->positions, static fn(array $row): bool => (int) $row['keyword_id'] !== (int) reset($where)));
        return 1;
    }
}
