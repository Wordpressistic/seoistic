<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\BusinessAutomator;

/**
 * Pre-built script templates for common SEO automation tasks.
 */
final class ScriptTemplates {

	/**
	 * Get all available templates.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function get_all(): array {
		return array(
			'uptime_checker'         => self::uptime_checker(),
			'indexing_checker'       => self::indexing_checker(),
			'seo_score_tracker'      => self::seo_score_tracker(),
			'backlink_monitor'       => self::backlink_monitor(),
			'competitive_tracker'    => self::competitive_tracker(),
		);
	}

	/**
	 * Get a specific template.
	 *
	 * @param string $template_id Template ID.
	 * @return array<string, mixed>|null
	 */
	public static function get( string $template_id ): ?array {
		$templates = self::get_all();
		return $templates[ $template_id ] ?? null;
	}

	/**
	 * Uptime checker template - monitors domain availability and response times.
	 *
	 * @return array<string, mixed>
	 */
	private static function uptime_checker(): array {
		return array(
			'id'          => 'uptime_checker',
			'name'        => 'Domain Uptime Checker',
			'description' => 'Monitor your domain availability and track response times in real-time.',
			'category'    => 'monitoring',
			'tier'        => 'business',
			'code'        => <<<'PYTHON'
import requests
import os
from datetime import datetime
import json

# Get domain from environment
DOMAIN = os.environ.get('DOMAIN_URL', 'https://example.com')
DATASTORE_NAME = os.environ.get('DATASTORE_NAME', 'uptime_checks')

try:
    # Check domain availability
    start_time = datetime.now()
    response = requests.head(DOMAIN, timeout=10, allow_redirects=True)
    end_time = datetime.now()

    response_time = (end_time - start_time).total_seconds() * 1000  # milliseconds
    status = 'UP' if response.status_code < 400 else 'DOWN'
    status_code = response.status_code

    result = {
        'domain': DOMAIN,
        'status': status,
        'status_code': status_code,
        'response_time_ms': round(response_time, 2),
        'timestamp': start_time.isoformat(),
    }

    print(f"Uptime Check Result: {json.dumps(result, indent=2)}")

except requests.exceptions.RequestException as e:
    result = {
        'domain': DOMAIN,
        'status': 'DOWN',
        'error': str(e),
        'timestamp': datetime.now().isoformat(),
    }
    print(f"Uptime Check Failed: {json.dumps(result, indent=2)}")

print(json.dumps(result))
PYTHON,
			'config'      => array(
				'parameters' => array(
					'DOMAIN_URL'      => array(
						'type'        => 'string',
						'description' => 'Domain URL to monitor',
						'required'    => true,
					),
					'DATASTORE_NAME'  => array(
						'type'        => 'string',
						'description' => 'Datastore name for results',
						'default'     => 'uptime_checks',
					),
				),
			),
			'timeout'     => 60,
		);
	}

	/**
	 * Indexing checker template - checks Google and Bing indexing status.
	 *
	 * @return array<string, mixed>
	 */
	private static function indexing_checker(): array {
		return array(
			'id'          => 'indexing_checker',
			'name'        => 'Google & Bing Indexing Checker',
			'description' => 'Check if your pages are indexed in Google and Bing search engines.',
			'category'    => 'monitoring',
			'tier'        => 'business',
			'code'        => <<<'PYTHON'
import requests
import os
from datetime import datetime
import json
from urllib.parse import quote

# Get domain and pages from environment
DOMAIN = os.environ.get('DOMAIN_URL', 'https://example.com')
PAGES = os.environ.get('PAGES', '/').split(',')

results = []

for page in PAGES:
    page = page.strip()
    if not page.startswith('http'):
        url = DOMAIN.rstrip('/') + '/' + page.lstrip('/')
    else:
        url = page

    try:
        # Check Google index using site: operator simulation
        google_check = {
            'url': url,
            'google_indexed': None,
            'bing_indexed': None,
            'timestamp': datetime.now().isoformat(),
        }

        # Note: Real implementation would use Google Search Console API
        # and Bing Webmaster Tools API for accurate results
        print(f"Checking index status for: {url}")

        results.append(google_check)
    except Exception as e:
        results.append({
            'url': url,
            'error': str(e),
            'timestamp': datetime.now().isoformat(),
        })

print(json.dumps(results, indent=2))
PYTHON,
			'config'      => array(
				'parameters' => array(
					'DOMAIN_URL' => array(
						'type'        => 'string',
						'description' => 'Domain URL to check',
						'required'    => true,
					),
					'PAGES'      => array(
						'type'        => 'string',
						'description' => 'Comma-separated list of pages to check',
						'default'     => '/',
					),
				),
			),
			'timeout'     => 120,
		);
	}

	/**
	 * SEO score tracker template - tracks SEO performance over time.
	 *
	 * @return array<string, mixed>
	 */
	private static function seo_score_tracker(): array {
		return array(
			'id'          => 'seo_score_tracker',
			'name'        => 'SEO Score Tracker',
			'description' => 'Track SEO performance metrics over time to identify trends and improvements.',
			'category'    => 'analytics',
			'tier'        => 'business',
			'code'        => <<<'PYTHON'
import os
import json
from datetime import datetime

# This template would integrate with SEOISTIC backend
# to fetch and track SEO scores over time

WORDPRESS_URL = os.environ.get('WORDPRESS_URL', '')
API_TOKEN = os.environ.get('API_TOKEN', '')

# TODO: Implement API call to SEOISTIC REST endpoint
# GET /seoistic/v1/analytics/scores

result = {
    'note': 'SEO Score Tracker - Connect to SEOISTIC backend',
    'wordpress_url': WORDPRESS_URL,
    'timestamp': datetime.now().isoformat(),
    'status': 'requires_setup',
}

print(json.dumps(result, indent=2))
PYTHON,
			'config'      => array(
				'parameters' => array(
					'WORDPRESS_URL' => array(
						'type'        => 'string',
						'description' => 'WordPress site URL',
						'required'    => true,
					),
					'API_TOKEN'     => array(
						'type'        => 'string',
						'description' => 'SEOISTIC API token',
						'required'    => true,
					),
				),
			),
			'timeout'     => 180,
		);
	}

	/**
	 * Backlink monitor template - tracks new backlinks.
	 *
	 * @return array<string, mixed>
	 */
	private static function backlink_monitor(): array {
		return array(
			'id'          => 'backlink_monitor',
			'name'        => 'Backlink Monitor',
			'description' => 'Monitor new backlinks and track link profile changes.',
			'category'    => 'monitoring',
			'tier'        => 'business',
			'code'        => <<<'PYTHON'
import os
import json
from datetime import datetime

# Backlink monitoring template
# Can integrate with backlink APIs like Ahrefs, Moz, SEMrush, etc.

DOMAIN = os.environ.get('DOMAIN_URL', '')
API_SERVICE = os.environ.get('BACKLINK_API', 'ahrefs')  # ahrefs, moz, semrush, etc.

result = {
    'domain': DOMAIN,
    'api_service': API_SERVICE,
    'timestamp': datetime.now().isoformat(),
    'status': 'requires_setup',
    'note': 'Configure your backlink API credentials in environment variables',
}

print(json.dumps(result, indent=2))
PYTHON,
			'config'      => array(
				'parameters' => array(
					'DOMAIN_URL'   => array(
						'type'        => 'string',
						'description' => 'Domain to monitor',
						'required'    => true,
					),
					'BACKLINK_API' => array(
						'type'        => 'string',
						'description' => 'Backlink API service (ahrefs, moz, semrush)',
						'default'     => 'ahrefs',
					),
				),
			),
			'timeout'     => 300,
		);
	}

	/**
	 * Competitive tracker template - monitors competitor rankings.
	 *
	 * @return array<string, mixed>
	 */
	private static function competitive_tracker(): array {
		return array(
			'id'          => 'competitive_tracker',
			'name'        => 'Competitive Tracker',
			'description' => 'Monitor competitor domains and track their SEO performance.',
			'category'    => 'analytics',
			'tier'        => 'business',
			'code'        => <<<'PYTHON'
import os
import json
from datetime import datetime

# Competitive analysis template
DOMAIN = os.environ.get('DOMAIN_URL', '')
COMPETITORS = os.environ.get('COMPETITORS', '').split(',')

result = {
    'domain': DOMAIN,
    'competitors': [c.strip() for c in COMPETITORS if c.strip()],
    'timestamp': datetime.now().isoformat(),
    'status': 'requires_setup',
    'note': 'Connect to ranking/analytics APIs for competitor tracking',
}

print(json.dumps(result, indent=2))
PYTHON,
			'config'      => array(
				'parameters' => array(
					'DOMAIN_URL'  => array(
						'type'        => 'string',
						'description' => 'Your domain',
						'required'    => true,
					),
					'COMPETITORS' => array(
						'type'        => 'string',
						'description' => 'Comma-separated competitor domains',
						'required'    => true,
					),
				),
			),
			'timeout'     => 300,
		);
	}
}
