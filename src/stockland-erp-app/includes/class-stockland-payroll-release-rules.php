<?php
/**
 * Deliberately contains no Ethiopian statutory payroll defaults.
 *
 * A payroll rule must be created as DRAFT from the current official source
 * material, reviewed by a qualified independent Ethiopian professional, and
 * separately approved through the Owner-only maker-checker workflow. An ERP
 * Owner declaration or a WordPress Administrator account is not legal review.
 *
 * @package Stockland_ERP
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Stockland_Payroll_Release_Rules {
	const RULE_CODE = 'et-payroll';
	const VERSION   = 'NO_SHIPPED_STATUTORY_DEFAULT';

	/**
	 * There are no bundled statutory rates, dates, classifications or tax bases.
	 *
	 * @return array<string,mixed>
	 */
	public static function rules() {
		return array();
	}

	/**
	 * Backward-compatible init hook. It must never create or approve a rule.
	 *
	 * @return bool Always false: no rule was inserted or changed.
	 */
	public static function ensure() {
		return false;
	}
}
