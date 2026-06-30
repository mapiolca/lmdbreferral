<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

/**
 * Compatibility helpers for lmdbreferral.
 */
class LmdbReferralCompatibility
{
	public const MIN_DOLIBARR_VERSION = '20.0.0';
	public const MIN_PHP_VERSION = '8.0.0';

	/**
	 * @param string $version Version
	 * @return bool
	 */
	public static function isDolibarrVersionAtLeast($version)
	{
		return defined('DOL_VERSION') && version_compare(DOL_VERSION, $version, '>=');
	}

	/**
	 * @param string $version Version
	 * @return bool
	 */
	public static function isPhpVersionAtLeast($version)
	{
		return version_compare(PHP_VERSION, $version, '>=');
	}

	/**
	 * @return array<string,array<string,mixed>>
	 */
	public static function getFeatures()
	{
		return array(
			'core_module' => array(
				'label' => 'LmdbReferralCompatibilityFeatureCore',
				'description' => 'LmdbReferralCompatibilityFeatureCoreDesc',
				'min_dolibarr' => self::MIN_DOLIBARR_VERSION,
				'min_php' => self::MIN_PHP_VERSION,
				'checks' => array(),
			),
			'thirdparty_hooks' => array(
				'label' => 'LmdbReferralCompatibilityFeatureThirdpartyHooks',
				'description' => 'LmdbReferralCompatibilityFeatureThirdpartyHooksDesc',
				'min_dolibarr' => '20.0.0',
				'min_php' => self::MIN_PHP_VERSION,
				'checks' => array(),
			),
			'propal_signed_trigger' => array(
				'label' => 'LmdbReferralCompatibilityFeaturePropalTrigger',
				'description' => 'LmdbReferralCompatibilityFeaturePropalTriggerDesc',
				'min_dolibarr' => '20.0.0',
				'min_php' => self::MIN_PHP_VERSION,
				'checks' => array('Propal'),
			),
			'invoice_referral_banner' => array(
				'label' => 'LmdbReferralCompatibilityFeatureInvoiceBanner',
				'description' => 'LmdbReferralCompatibilityFeatureInvoiceBannerDesc',
				'min_dolibarr' => '20.0.0',
				'min_php' => self::MIN_PHP_VERSION,
				'checks' => array('Facture'),
			),
			'multicompany_sharing' => array(
				'label' => 'LmdbReferralCompatibilityFeatureMulticompany',
				'description' => 'LmdbReferralCompatibilityFeatureMulticompanyDesc',
				'min_dolibarr' => '20.0.0',
				'min_php' => self::MIN_PHP_VERSION,
				'checks' => array('getEntity'),
			),
		);
	}

	/**
	 * @param string $feature Feature code
	 * @return bool
	 */
	public static function isFeatureAvailable($feature)
	{
		$features = self::getFeatures();
		if (empty($features[$feature])) {
			return false;
		}

		return !empty(self::getFeatureStatus($feature, $features[$feature])['available']);
	}

	/**
	 * @return array<string,array<string,mixed>>
	 */
	public static function getUnavailableFeatures()
	{
		$out = array();
		foreach (self::getFeatures() as $code => $feature) {
			$status = self::getFeatureStatus($code, $feature);
			if (empty($status['available'])) {
				$out[$code] = $status;
			}
		}

		return $out;
	}

	/**
	 * @param string              $code    Feature code
	 * @param array<string,mixed> $feature Feature definition
	 * @return array<string,mixed>
	 */
	public static function getFeatureStatus($code, $feature)
	{
		$available = true;
		$reason = 'LmdbReferralCompatibilityReasonAvailable';

		if (!self::isDolibarrVersionAtLeast($feature['min_dolibarr'])) {
			$available = false;
			$reason = 'LmdbReferralCompatibilityReasonDolibarr';
		}
		if ($available && !self::isPhpVersionAtLeast($feature['min_php'])) {
			$available = false;
			$reason = 'LmdbReferralCompatibilityReasonPhp';
		}
		if ($available && !empty($feature['checks']) && is_array($feature['checks'])) {
			foreach ($feature['checks'] as $check) {
				if ($check === 'getEntity' && !function_exists('getEntity')) {
					$available = false;
					$reason = 'LmdbReferralCompatibilityReasonFunctionMissing';
					break;
				}
				if ($check === 'Propal' && !class_exists('Propal')) {
					$available = false;
					$reason = 'LmdbReferralCompatibilityReasonClassMissing';
					break;
				}
				if ($check === 'Facture' && !class_exists('Facture')) {
					$available = false;
					$reason = 'LmdbReferralCompatibilityReasonClassMissing';
					break;
				}
			}
		}

		$feature['code'] = $code;
		$feature['available'] = $available;
		$feature['reason'] = $reason;

		return $feature;
	}
}
