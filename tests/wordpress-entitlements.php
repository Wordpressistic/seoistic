<?php
// Executed by wp eval-file against an isolated WordPress database in CI only.
use Wpistic\Seoistic\Core\Crypto;
use Wpistic\Seoistic\Module\Entitlement;

update_option('seoistic_license_key', Crypto::encrypt('ci-synthetic-license', 'license'));
update_option('seoistic_license_status', 'active');
update_option('seoistic_license_last_ok', time());
update_option('seoistic_license_expires', '');
foreach (array('free'=>'free', 'starter'=>'pro', 'professional'=>'business', 'agency'=>'agency') as $plan => $expected) {
	update_option('seoistic_license_meta', array('plan'=>$plan));
	if (Entitlement::plan() !== $expected) {
		throw new RuntimeException('Wrong server plan in WordPress: ' . $plan);
	}
}
update_option('seoistic_license_status', 'invalid');
if (Entitlement::is_pro()) {
	throw new RuntimeException('Revoked license retained paid access');
}
echo "WordPress options, crypto, canonical plans and revocation passed\n";
