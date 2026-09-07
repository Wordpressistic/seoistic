<?php
/** Dependency-free regression suite, invoked in CI. Never contacts a live site. */
require __DIR__ . '/bootstrap.php';

use Wpistic\Seoistic\Core\Crypto;
use Wpistic\Seoistic\Module\Entitlement;

$checks = 0;
function check_same($expected, $actual, string $label): void {
	global $checks;
	if ($expected !== $actual) {
		throw new RuntimeException($label . ': unexpected result ' . var_export($actual, true));
	}
	++$checks;
}
function trusted_license(array $meta): void {
	$GLOBALS['seoistic_test_options'] = array();
	update_option('seoistic_license_key', Crypto::encrypt('synthetic-license-key', 'license'));
	update_option('seoistic_license_status', 'active');
	update_option('seoistic_license_last_ok', time());
	update_option('seoistic_license_meta', $meta);
}

foreach (array('free'=>'free', 'pro'=>'pro', 'business'=>'business', 'agency'=>'agency', 'starter'=>'pro', 'professional'=>'business', 'agency-pro'=>'agency', 'unknown'=>'free', ''=>'free') as $raw => $expected) {
	trusted_license(array('plan'=>$raw));
	update_option('seoistic_plan_map', array(0=>'business'));
	check_same($expected, Entitlement::plan(), 'server plan overrides legacy map: ' . $raw);
	check_same('free' !== $expected, Entitlement::is_pro(), 'paid flag: ' . $raw);
}
trusted_license(array('plan'=>null));
update_option('seoistic_plan_map', array(0=>'business'));
check_same('free', Entitlement::plan(), 'malformed server plan fails closed');
trusted_license(array());
check_same('free', Entitlement::plan(), 'unmapped valid license defaults Free');
update_option('seoistic_plan_map', array(0=>'professional'));
check_same('business', Entitlement::plan(), 'explicit legacy map remains supported');
trusted_license(array('plan'=>'free'));
check_same(false, Entitlement::can('ai', 'pro'), 'Free cannot unlock paid AI');
trusted_license(array('plan'=>'pro'));
check_same(true, Entitlement::can('ai', 'pro'), 'Pro unlocks AI');
check_same(false, Entitlement::can('rank_tracker', 'pro'), 'Pro cannot unlock Business');
trusted_license(array('plan'=>'business'));
check_same(true, Entitlement::can('rank_tracker', 'pro'), 'Business unlocks rank tracker');
update_option('seoistic_license_status', 'invalid');
check_same('free', Entitlement::plan(), 'revoked cached Business cannot unlock');
trusted_license(array('plan'=>'business'));
update_option('seoistic_license_expires', '2000-01-01');
check_same('free', Entitlement::plan(), 'expired Business cannot unlock');
trusted_license(array('plan'=>'business'));
update_option('seoistic_license_last_ok', time() - 31 * DAY_IN_SECONDS);
check_same('free', Entitlement::plan(), 'stale trust window fails closed');
trusted_license(array('plan'=>'business'));
delete_option('seoistic_license_key');
check_same('free', Entitlement::plan(), 'missing key fails closed');

trusted_license(array('plan'=>'business'));
$GLOBALS['seoistic_test_responses'] = array(
	array('body'=>array('success'=>true)),
	array('status'=>503),
);
(new Wpistic\Seoistic\License\LicenseClient())->activate('synthetic-replacement-key');
check_same('free', Entitlement::plan(), 'new key cannot inherit old Business on outage');
trusted_license(array('plan'=>'business'));
$GLOBALS['seoistic_test_responses'] = array(array('status'=>503));
(new Wpistic\Seoistic\License\LicenseClient())->validate();
check_same('business', Entitlement::plan(), 'same key keeps bounded grace during outage');
trusted_license(array('plan'=>'business'));
$GLOBALS['seoistic_test_responses'] = array(array('body'=>array('success'=>true, 'data'=>array('status'=>'active','plan'=>'free'))));
(new Wpistic\Seoistic\License\LicenseClient())->validate();
check_same('free', Entitlement::plan(), 'server downgrade applies on next validation');
echo "PASS {$checks} license entitlement checks\n";
