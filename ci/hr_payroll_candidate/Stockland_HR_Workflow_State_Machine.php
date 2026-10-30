<?php
/**
 * Side-effect-free workflow contract for the Stockland HR/payroll replacement.
 *
 * This class validates state transitions but does not check a WordPress user
 * itself, persist rows, post accounting, send payments, or infer legal outcomes.
 * The caller must build context from trusted server-side authority and locked DB rows.
 */
final class Stockland_HR_Workflow_State_Machine {
	private const TRANSITIONS = array(
		'vacancy' => array(
			'DRAFT' => array( 'SUBMITTED', 'CANCELLED' ),
			'SUBMITTED' => array( 'APPROVED', 'REJECTED', 'DRAFT' ),
			'APPROVED' => array( 'OPEN', 'CANCELLED' ),
			'OPEN' => array( 'ON_HOLD', 'CLOSED', 'CANCELLED' ),
			'ON_HOLD' => array( 'OPEN', 'CLOSED', 'CANCELLED' ),
			'REJECTED' => array(),
			'CLOSED' => array(),
			'CANCELLED' => array(),
		),
		'application' => array(
			'DRAFT' => array( 'SUBMITTED', 'WITHDRAWN' ),
			'SUBMITTED' => array( 'SCREENED', 'REJECTED', 'WITHDRAWN' ),
			'SCREENED' => array( 'INTERVIEW', 'RECOMMENDED', 'REJECTED', 'WITHDRAWN' ),
			'INTERVIEW' => array( 'RECOMMENDED', 'REJECTED', 'WITHDRAWN' ),
			'RECOMMENDED' => array( 'SELECTED', 'REJECTED' ),
			'SELECTED' => array( 'OFFER_PENDING', 'WITHDRAWN' ),
			'OFFER_PENDING' => array( 'OFFER_ACCEPTED', 'REJECTED', 'WITHDRAWN' ),
			'OFFER_ACCEPTED' => array( 'CONTRACT_PENDING' ),
			'CONTRACT_PENDING' => array( 'ONBOARDING', 'WITHDRAWN' ),
			'ONBOARDING' => array( 'PENDING_ACTIVATION', 'WITHDRAWN' ),
			'PENDING_ACTIVATION' => array( 'ACTIVE', 'WITHDRAWN' ),
			'ACTIVE' => array(),
			'REJECTED' => array(),
			'WITHDRAWN' => array(),
		),
		'employment' => array(
			'PENDING_ACTIVATION' => array( 'ACTIVE', 'CANCELLED' ),
			'ACTIVE' => array( 'ON_LEAVE', 'NOTICE', 'SUSPENDED' ),
			'ON_LEAVE' => array( 'ACTIVE', 'NOTICE' ),
			'SUSPENDED' => array( 'ACTIVE', 'NOTICE' ),
			'NOTICE' => array( 'EXIT_PROCESSING', 'ACTIVE' ),
			'EXIT_PROCESSING' => array( 'SEPARATED' ),
			'SEPARATED' => array( 'ARCHIVED' ),
			'ARCHIVED' => array(),
			'CANCELLED' => array(),
		),
		'compensation' => array(
			'DRAFT' => array( 'SUBMITTED', 'CANCELLED' ),
			'SUBMITTED' => array( 'APPROVED', 'REJECTED', 'DRAFT' ),
			'APPROVED' => array( 'EFFECTIVE', 'CANCELLED' ),
			'EFFECTIVE' => array( 'SUPERSEDED' ),
			'SUPERSEDED' => array(),
			'REJECTED' => array(),
			'CANCELLED' => array(),
		),
		'legal_rule' => array(
			'DRAFT' => array( 'LEGAL_REVIEW', 'CANCELLED' ),
			'LEGAL_REVIEW' => array( 'APPROVED', 'REJECTED', 'DRAFT' ),
			'APPROVED' => array( 'EFFECTIVE', 'RETIRED' ),
			'EFFECTIVE' => array( 'SUPERSEDED', 'RETIRED' ),
			'SUPERSEDED' => array(),
			'RETIRED' => array(),
			'REJECTED' => array(),
			'CANCELLED' => array(),
		),
		'payroll_run' => array(
			'DRAFT' => array( 'PREFLIGHT_BLOCKED', 'READY', 'CANCELLED' ),
			'PREFLIGHT_BLOCKED' => array( 'DRAFT', 'CANCELLED' ),
			'READY' => array( 'CALCULATED', 'PREFLIGHT_BLOCKED', 'CANCELLED' ),
			'CALCULATED' => array( 'SUBMITTED', 'DRAFT', 'PREFLIGHT_BLOCKED' ),
			'SUBMITTED' => array( 'APPROVED', 'REJECTED', 'CALCULATED' ),
			'APPROVED' => array( 'POSTED' ),
			'POSTED' => array( 'SETTLEMENT_PENDING' ),
			'SETTLEMENT_PENDING' => array( 'PARTIALLY_SETTLED', 'SETTLED' ),
			'PARTIALLY_SETTLED' => array( 'SETTLEMENT_PENDING', 'SETTLED' ),
			'SETTLED' => array( 'RECONCILED' ),
			'RECONCILED' => array( 'CLOSED' ),
			'CLOSED' => array(),
			'REJECTED' => array( 'CALCULATED', 'CANCELLED' ),
			'CANCELLED' => array(),
		),
		'payout_instruction' => array(
			'DRAFT' => array( 'APPROVED', 'CANCELLED' ),
			'APPROVED' => array( 'READY_FOR_PAYMENT', 'CANCELLED' ),
			'READY_FOR_PAYMENT' => array( 'SENT', 'PAYMENT_FAILED' ),
			'SENT' => array( 'SETTLED', 'PAYMENT_FAILED', 'RETURNED' ),
			'PAYMENT_FAILED' => array( 'READY_FOR_PAYMENT', 'CANCELLED' ),
			'RETURNED' => array( 'READY_FOR_PAYMENT', 'CANCELLED' ),
			'SETTLED' => array( 'RECONCILED' ),
			'RECONCILED' => array(),
			'CANCELLED' => array(),
		),
		'payee_request' => array(
			'DRAFT' => array( 'SUBMITTED', 'CANCELLED' ),
			'SUBMITTED' => array( 'AUTO_CHECK', 'NEEDS_INFORMATION', 'LEGAL_TAX_REVIEW' ),
			'AUTO_CHECK' => array( 'APPROVED', 'NEEDS_INFORMATION', 'LEGAL_TAX_REVIEW', 'REJECTED' ),
			'NEEDS_INFORMATION' => array( 'SUBMITTED', 'CANCELLED' ),
			'LEGAL_TAX_REVIEW' => array( 'APPROVED', 'NEEDS_INFORMATION', 'REJECTED' ),
			'APPROVED' => array( 'READY_FOR_PAYMENT', 'CANCELLED' ),
			'READY_FOR_PAYMENT' => array( 'PARTIALLY_PAID', 'PAID', 'PAYMENT_FAILED' ),
			'PARTIALLY_PAID' => array( 'PAID', 'PAYMENT_FAILED', 'RETURNED' ),
			'PAYMENT_FAILED' => array( 'READY_FOR_PAYMENT', 'CANCELLED' ),
			'RETURNED' => array( 'READY_FOR_PAYMENT', 'CANCELLED' ),
			'PAID' => array( 'RECONCILED' ),
			'RECONCILED' => array( 'CLOSED' ),
			'CLOSED' => array(),
			'REJECTED' => array(),
			'CANCELLED' => array(),
		),
		'exit_settlement' => array(
			'DRAFT' => array( 'CALCULATED', 'CANCELLED' ),
			'CALCULATED' => array( 'SUBMITTED', 'DRAFT' ),
			'SUBMITTED' => array( 'APPROVED', 'REJECTED' ),
			'APPROVED' => array( 'READY_FOR_PAYMENT' ),
			'READY_FOR_PAYMENT' => array( 'PAID', 'PAYMENT_FAILED' ),
			'PAYMENT_FAILED' => array( 'READY_FOR_PAYMENT' ),
			'PAID' => array( 'RECONCILED' ),
			'RECONCILED' => array( 'CLOSED' ),
			'CLOSED' => array(),
			'REJECTED' => array( 'CALCULATED' ),
			'CANCELLED' => array(),
		),
	);

	private const APPROVER_TARGETS = array(
		'APPROVED', 'SELECTED', 'ACTIVE', 'EFFECTIVE', 'POSTED',
		'SETTLED', 'RECONCILED', 'CLOSED', 'PAID', 'READY_FOR_PAYMENT',
	);

	/**
	 * Return legal next states for a resource type/current state.
	 *
	 * @param string $entity Resource category.
	 * @param string $current Current state.
	 * @return array
	 */
	public static function allowed_next( $entity, $current ) {
		$entity = strtolower( trim( (string) $entity ) );
		$current = strtoupper( trim( (string) $current ) );
		if ( ! isset( self::TRANSITIONS[ $entity ][ $current ] ) ) {
			throw new DomainException( 'Unknown workflow resource or state.' );
		}
		return self::TRANSITIONS[ $entity ][ $current ];
	}

	/**
	 * Validate a transition, trusted permissions and transition-specific evidence.
	 *
	 * Context must be generated from server-side role checks and a locked database row.
	 * Never pass user-supplied booleans such as can_approve from a REST request.
	 *
	 * @param string $entity Resource category.
	 * @param string $from Current state under lock.
	 * @param string $to Requested next state.
	 * @param array  $context Trusted actor/object data from the application service.
	 * @return array Validated transition/audit envelope.
	 * @throws DomainException If the transition is unauthorized or lacks evidence.
	 */
	public static function assert_transition( $entity, $from, $to, array $context ) {
		$entity = strtolower( trim( (string) $entity ) );
		$from = strtoupper( trim( (string) $from ) );
		$to = strtoupper( trim( (string) $to ) );
		$allowed = self::allowed_next( $entity, $from );
		if ( ! in_array( $to, $allowed, true ) ) {
			throw new DomainException( 'Illegal workflow transition: ' . $entity . ' ' . $from . ' → ' . $to . '.' );
		}
		$actor = (int) ( $context['actor_id'] ?? 0 );
		$creator = (int) ( $context['created_by'] ?? 0 );
		$approver = (int) ( $context['approver_id'] ?? 0 );
		$current_version = (int) ( $context['current_version'] ?? 0 );
		$expected_version = (int) ( $context['expected_version'] ?? -1 );
		if ( $actor <= 0 ) {
			throw new DomainException( 'Authenticated actor is required.' );
		}
		if ( $current_version <= 0 || $expected_version !== $current_version ) {
			throw new DomainException( 'Stale workflow version. Refresh the record before retrying.' );
		}
		$approval_transition = in_array( $to, self::APPROVER_TARGETS, true );
		if ( $approval_transition ) {
			if ( $creator <= 0 ) {
				throw new DomainException( 'Trusted record creator identity is required before an approval transition.' );
			}
			if ( empty( $context['can_approve'] ) ) {
				throw new DomainException( 'Independent approval authority is required for this transition.' );
			}
			if ( $approver <= 0 || $approver !== $actor ) {
				throw new DomainException( 'Approver identity must match the authenticated actor.' );
			}
			if ( $creator > 0 && $creator === $actor ) {
				throw new DomainException( 'The record maker cannot approve the same high-impact record.' );
			}
		} elseif ( empty( $context['can_manage'] ) ) {
			throw new DomainException( 'Manage authority is required for non-approval workflow transitions; approval authority alone is insufficient.' );
		}

		self::validate_evidence( $entity, $from, $to, $context );
		return array(
			'entity' => $entity,
			'from_status' => $from,
			'to_status' => $to,
			'actor_id' => $actor,
			'approver_id' => $approval_transition ? $approver : null,
			'previous_version' => $current_version,
			'next_version' => $current_version + 1,
			'event_key' => strtoupper( $entity ) . '_' . $from . '_TO_' . $to,
			'event_time_required' => true,
		);
	}

	private static function validate_evidence( $entity, $from, $to, array $context ) {
		if ( 'legal_rule' === $entity && 'APPROVED' === $to ) {
			if ( empty( $context['qualified_legal_reviewer'] ) || empty( $context['test_vectors_passed'] ) || empty( $context['source_hash_valid'] ) || empty( $context['effective_dates_verified'] ) ) {
				throw new DomainException( 'Legal rule approval requires qualified review, verified official source/hash, effective dates and passing test vectors.' );
			}
		}
		if ( 'application' === $entity && 'SELECTED' === $to ) {
			if ( empty( $context['human_decision'] ) || strlen( trim( (string) ( $context['selection_rationale'] ?? '' ) ) ) < 20 ) {
				throw new DomainException( 'Selection requires an explicit human decision and a documented rationale of at least 20 characters.' );
			}
		}
		if ( 'employment' === $entity && 'ACTIVE' === $to ) {
			foreach ( array( 'contract_signed', 'private_documents_verified', 'onboarding_complete', 'compensation_approved', 'payroll_setup_complete' ) as $required ) {
				if ( empty( $context[ $required ] ) ) {
					throw new DomainException( 'Employee activation blocked: ' . str_replace( '_', ' ', $required ) . ' is incomplete.' );
				}
			}
		}
		if ( 'payroll_run' === $entity && 'READY' === $to ) {
			foreach ( array( 'preflight_complete', 'period_valid', 'inputs_frozen', 'rule_versions_pinned' ) as $required ) {
				if ( empty( $context[ $required ] ) ) {
					throw new DomainException( 'Payroll preflight failed: ' . str_replace( '_', ' ', $required ) . ' is required.' );
				}
			}
		}
		if ( 'payroll_run' === $entity && 'APPROVED' === $to ) {
			foreach ( array( 'all_lines_calculated', 'rules_approved', 'exceptions_clear', 'totals_reconcile', 'audit_ready' ) as $required ) {
				if ( empty( $context[ $required ] ) ) {
					throw new DomainException( 'Payroll approval blocked: ' . str_replace( '_', ' ', $required ) . ' is required.' );
				}
			}
		}
		if ( 'payroll_run' === $entity && 'SETTLED' === $to ) {
			$required_count = $context['required_payout_count'] ?? null;
			$settled_count = $context['settled_payout_count'] ?? null;
			$unresolved_count = $context['unresolved_payout_count'] ?? null;
			$net_payable_minor = $context['net_payable_minor'] ?? null;
			$settled_amount_minor = $context['settled_amount_minor'] ?? null;
			$reconciliation_reference = trim( (string) ( $context['settlement_reconciliation_reference'] ?? '' ) );
			$evidence_hash = strtolower( (string) ( $context['settlement_evidence_sha256'] ?? '' ) );
			if ( ! is_int( $required_count ) || $required_count <= 0 || ! is_int( $settled_count ) || $settled_count !== $required_count || ! is_int( $unresolved_count ) || 0 !== $unresolved_count ) {
				throw new DomainException( 'Payroll run settlement requires every expected payout to be individually settled with no failed, returned or unresolved payment.' );
			}
			if ( ! is_int( $net_payable_minor ) || ! is_int( $settled_amount_minor ) || $net_payable_minor < 0 || $settled_amount_minor !== $net_payable_minor ) {
				throw new DomainException( 'Aggregate settled payout amount must exactly equal the approved net-payable amount in minor currency units.' );
			}
			if ( '' === $reconciliation_reference || ! preg_match( '/^[a-f0-9]{64}$/', $evidence_hash ) ) {
				throw new DomainException( 'Payroll settlement requires a reconciliation reference and a SHA-256 evidence bundle.' );
			}
		}
		if ( 'payout_instruction' === $entity && 'SETTLED' === $to ) {
			if ( '' === trim( (string) ( $context['external_confirmation_reference'] ?? '' ) ) || empty( $context['confirmed_amount'] ) || empty( $context['source_payment_unique'] ) ) {
				throw new DomainException( 'Settlement requires external confirmation, a confirmed amount and unique payment identity.' );
			}
		}
		if ( 'exit_settlement' === $entity && 'APPROVED' === $to ) {
			foreach ( array( 'legal_exit_basis_verified', 'leave_balance_reconciled', 'permitted_deductions_verified', 'final_calculation_reconciled', 'clearance_complete' ) as $required ) {
				if ( empty( $context[ $required ] ) ) {
					throw new DomainException( 'Final settlement approval blocked: ' . str_replace( '_', ' ', $required ) . ' is required.' );
				}
			}
		}
		if ( 'payee_request' === $entity && 'APPROVED' === $to && empty( $context['classification_decision_approved'] ) ) {
			throw new DomainException( 'Individual payee approval requires a documented classification/withholding decision.' );
		}
	}

}
