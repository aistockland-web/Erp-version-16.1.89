<?php
/**
 * REST routes for the workforce-planning replacement slice.
 *
 * Source candidate only. It is not loaded by the production bootstrap yet.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! defined( 'STOCKLAND_PLUGIN_DIR' ) || ! class_exists( 'Stockland_REST_Base' ) || ! class_exists( 'Stockland_Authority' ) ) {
	return;
}

require_once STOCKLAND_PLUGIN_DIR . 'includes/Stockland_HR_Workflow_State_Machine.php';
require_once STOCKLAND_PLUGIN_DIR . 'includes/Stockland_HR_Replacement_Workforce_Contract.php';
require_once STOCKLAND_PLUGIN_DIR . 'includes/Stockland_HR_Replacement_Workforce_Repository.php';
require_once STOCKLAND_PLUGIN_DIR . 'includes/Stockland_HR_Replacement_Workforce_Service.php';
require_once STOCKLAND_PLUGIN_DIR . 'includes/Stockland_HR_Replacement_Workforce_WPDB_Repository.php';
require_once STOCKLAND_PLUGIN_DIR . 'includes/Stockland_HR_Replacement_Workforce_WP_Access.php';
require_once STOCKLAND_PLUGIN_DIR . 'includes/Stockland_HR_Replacement_Workforce_Schema.php';

final class Stockland_REST_HR_Replacement_Workforce {
	private $service = null;
	private $access = null;

	public function __construct() {
		add_action( 'stockland_register_rest_routes', array( $this, 'register_routes' ) );
	}

	private function access() {
		if ( null === $this->access ) {
			$this->access = new Stockland_HR_Replacement_Workforce_WP_Access();
		}
		return $this->access;
	}

	private function service() {
		if ( null === $this->service ) {
			$this->service = new Stockland_HR_Replacement_Workforce_Service(
				new Stockland_HR_Replacement_Workforce_WPDB_Repository(),
				$this->access()
			);
		}
		return $this->service;
	}

	public function can_view() {
		return Stockland_Authority::can_view_hr();
	}
	public function can_prepare() {
		return Stockland_Authority::can_prepare_hr();
	}
	public function can_approve() {
		return Stockland_Authority::can_approve_payroll();
	}
	public function can_manage_structure() {
		return $this->access()->can_manage_org_structure();
	}

	private function payload( $request ) {
		$payload = $request->get_json_params();
		if ( ! is_array( $payload ) ) {
			throw new InvalidArgumentException( 'invalid_payload: A JSON object is required.' );
		}
		return $payload;
	}

	private function idempotency_key( $request ) {
		return (string) $request->get_header( 'Idempotency-Key' );
	}

	private function filters( $request, $allowed ) {
		$out = array();
		foreach ( $allowed as $field ) {
			$value = $request->get_param( $field );
			if ( null !== $value && '' !== $value ) {
				$out[ $field ] = $value;
			}
		}
		$limit = $request->get_param( 'limit' );
		if ( null !== $limit && '' !== $limit ) {
			$out['limit'] = max( 1, min( 100, (int) $limit ) );
		}
		return $out;
	}

	private function invoke( callable $operation ) {
		try {
			return rest_ensure_response( $operation() );
		} catch ( DomainException $e ) {
			$parts = explode( ':', $e->getMessage(), 2 );
			$code = strtolower( preg_replace( '/[^A-Za-z0-9_]/', '_', (string) $parts[0] ) );
			$status = (int) $e->getCode();
			if ( $status < 400 || $status > 599 ) {
				$status = 409;
			}
			return new WP_Error(
				$code,
				isset( $parts[1] ) ? trim( $parts[1] ) : 'The requested HR operation was rejected.',
				array( 'status' => $status )
			);
		} catch ( InvalidArgumentException $e ) {
			$parts = explode( ':', $e->getMessage(), 2 );
			$code = strtolower( preg_replace( '/[^A-Za-z0-9_]/', '_', (string) $parts[0] ) );
			return new WP_Error(
				$code,
				isset( $parts[1] ) ? trim( $parts[1] ) : 'The request did not meet the required input contract.',
				array( 'status' => 422 )
			);
		} catch ( Throwable $e ) {
			// Avoid returning SQL details, table names or private HR content to the caller.
			if ( function_exists( 'error_log' ) ) {
				error_log( '[Stockland HRX] operation failed (' . get_class( $e ) . ').' );
			}
			return new WP_Error(
				'hrx_operation_failed',
				'The HR replacement operation could not be completed. Refresh and contact an authorized ERP administrator; do not assume the action succeeded.',
				array( 'status' => 500 )
			);
		}
	}

	public function register_routes() {
		$namespace = Stockland_REST_Base::NAMESPACE_V1;
		register_rest_route(
			$namespace,
			'/hr/replacement/workforce/org-units',
			array(
				array(
					'methods' => WP_REST_Server::READABLE,
					'callback' => function ( $request ) {
						return $this->invoke( function () use ( $request ) {
							return $this->service()->list_org_units(
								$this->filters( $request, array( 'parent_id', 'status' ) ),
								$request->get_param( 'limit' ) ?: 50
							);
						} );
					},
					'permission_callback' => array( $this, 'can_view' ),
				),
				array(
					'methods' => WP_REST_Server::CREATABLE,
					'callback' => function ( $request ) {
						return $this->invoke( function () use ( $request ) {
							return $this->service()->create_org_unit( $this->payload( $request ), $this->idempotency_key( $request ) );
						} );
					},
					'permission_callback' => array( $this, 'can_manage_structure' ),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/hr/replacement/workforce/plans',
			array(
				array(
					'methods' => WP_REST_Server::READABLE,
					'callback' => function ( $request ) {
						return $this->invoke( function () use ( $request ) {
							return $this->service()->list_plans(
								$this->filters( $request, array( 'org_unit_id', 'location_id', 'status', 'after_id' ) ),
								$request->get_param( 'limit' ) ?: 50
							);
						} );
					},
					'permission_callback' => array( $this, 'can_view' ),
				),
				array(
					'methods' => WP_REST_Server::CREATABLE,
					'callback' => function ( $request ) {
						return $this->invoke( function () use ( $request ) {
							return $this->service()->create_plan( $this->payload( $request ), $this->idempotency_key( $request ) );
						} );
					},
					'permission_callback' => array( $this, 'can_prepare' ),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/hr/replacement/workforce/plans/(?P<id>\d+)',
			array(
				'methods' => WP_REST_Server::READABLE,
				'callback' => function ( $request ) {
					return $this->invoke( function () use ( $request ) {
						return $this->service()->get_plan( (int) $request['id'] );
					} );
				},
				'permission_callback' => array( $this, 'can_view' ),
			)
		);

		register_rest_route(
			$namespace,
			'/hr/replacement/workforce/plans/(?P<id>\d+)/lines',
			array(
				array(
					'methods' => WP_REST_Server::CREATABLE,
					'callback' => function ( $request ) {
						return $this->invoke( function () use ( $request ) {
							return $this->service()->add_plan_line( (int) $request['id'], $this->payload( $request ), $this->idempotency_key( $request ) );
						} );
					},
					'permission_callback' => array( $this, 'can_prepare' ),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/hr/replacement/workforce/plans/(?P<id>\d+)/lines/(?P<line_id>\d+)',
			array(
				'methods' => 'PATCH',
				'callback' => function ( $request ) {
					return $this->invoke( function () use ( $request ) {
						$payload = $this->payload( $request );
						return $this->service()->update_plan_line(
							(int) $request['id'],
							(int) $request['line_id'],
							$payload,
							(int) ( $payload['version_no'] ?? 0 ),
							$this->idempotency_key( $request )
						);
					} );
				},
				'permission_callback' => array( $this, 'can_prepare' ),
			)
		);

		register_rest_route(
			$namespace,
			'/hr/replacement/workforce/plans/(?P<id>\d+)/submit',
			array(
				'methods' => WP_REST_Server::CREATABLE,
				'callback' => function ( $request ) {
					return $this->invoke( function () use ( $request ) {
						$payload = $this->payload( $request );
						return $this->service()->submit_plan( (int) $request['id'], (int) ( $payload['version_no'] ?? 0 ), $this->idempotency_key( $request ) );
					} );
				},
				'permission_callback' => array( $this, 'can_prepare' ),
			)
		);

		register_rest_route(
			$namespace,
			'/hr/replacement/workforce/plans/(?P<id>\d+)/decision',
			array(
				'methods' => WP_REST_Server::CREATABLE,
				'callback' => function ( $request ) {
					return $this->invoke( function () use ( $request ) {
						$payload = $this->payload( $request );
						return $this->service()->decide_plan(
							(int) $request['id'],
							(int) ( $payload['version_no'] ?? 0 ),
							$payload['decision'] ?? '',
							$payload['reason'] ?? '',
							$payload['budget_reference'] ?? null,
							$this->idempotency_key( $request )
						);
					} );
				},
				'permission_callback' => array( $this, 'can_approve' ),
			)
		);

		register_rest_route(
			$namespace,
			'/hr/replacement/workforce/positions',
			array(
				array(
					'methods' => WP_REST_Server::READABLE,
					'callback' => function ( $request ) {
						return $this->invoke( function () use ( $request ) {
							return $this->service()->list_positions(
								$this->filters( $request, array( 'org_unit_id', 'location_id', 'status', 'after_id' ) ),
								$request->get_param( 'limit' ) ?: 50
							);
						} );
					},
					'permission_callback' => array( $this, 'can_view' ),
				),
				array(
					'methods' => WP_REST_Server::CREATABLE,
					'callback' => function ( $request ) {
						return $this->invoke( function () use ( $request ) {
							return $this->service()->create_position( $this->payload( $request ), $this->idempotency_key( $request ) );
						} );
					},
					'permission_callback' => array( $this, 'can_prepare' ),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/hr/replacement/workforce/positions/(?P<id>\d+)/submit',
			array(
				'methods' => WP_REST_Server::CREATABLE,
				'callback' => function ( $request ) {
					return $this->invoke( function () use ( $request ) {
						$payload = $this->payload( $request );
						return $this->service()->submit_position( (int) $request['id'], (int) ( $payload['version_no'] ?? 0 ), $this->idempotency_key( $request ) );
					} );
				},
				'permission_callback' => array( $this, 'can_prepare' ),
			)
		);

		register_rest_route(
			$namespace,
			'/hr/replacement/workforce/positions/(?P<id>\d+)/decision',
			array(
				'methods' => WP_REST_Server::CREATABLE,
				'callback' => function ( $request ) {
					return $this->invoke( function () use ( $request ) {
						$payload = $this->payload( $request );
						return $this->service()->decide_position(
							(int) $request['id'],
							(int) ( $payload['version_no'] ?? 0 ),
							$payload['decision'] ?? '',
							$payload['reason'] ?? '',
							$this->idempotency_key( $request )
						);
					} );
				},
				'permission_callback' => array( $this, 'can_approve' ),
			)
		);

		register_rest_route(
			$namespace,
			'/hr/replacement/workforce/positions/(?P<id>\d+)/activate',
			array(
				'methods' => WP_REST_Server::CREATABLE,
				'callback' => function ( $request ) {
					return $this->invoke( function () use ( $request ) {
						$payload = $this->payload( $request );
						return $this->service()->activate_position(
							(int) $request['id'],
							(int) ( $payload['version_no'] ?? 0 ),
							$payload['reason'] ?? '',
							$this->idempotency_key( $request )
						);
					} );
				},
				'permission_callback' => array( $this, 'can_approve' ),
			)
		);

		register_rest_route(
			$namespace,
			'/hr/replacement/workforce/requisitions',
			array(
				array(
					'methods' => WP_REST_Server::READABLE,
					'callback' => function ( $request ) {
						return $this->invoke( function () use ( $request ) {
							return $this->service()->list_requisitions(
								$this->filters( $request, array( 'plan_line_id', 'location_id', 'status', 'after_id' ) ),
								$request->get_param( 'limit' ) ?: 50
							);
						} );
					},
					'permission_callback' => array( $this, 'can_view' ),
				),
				array(
					'methods' => WP_REST_Server::CREATABLE,
					'callback' => function ( $request ) {
						return $this->invoke( function () use ( $request ) {
							return $this->service()->create_requisition( $this->payload( $request ), $this->idempotency_key( $request ) );
						} );
					},
					'permission_callback' => array( $this, 'can_prepare' ),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/hr/replacement/workforce/requisitions/(?P<id>\d+)/submit',
			array(
				'methods' => WP_REST_Server::CREATABLE,
				'callback' => function ( $request ) {
					return $this->invoke( function () use ( $request ) {
						$payload = $this->payload( $request );
						return $this->service()->submit_requisition( (int) $request['id'], (int) ( $payload['version_no'] ?? 0 ), $this->idempotency_key( $request ) );
					} );
				},
				'permission_callback' => array( $this, 'can_prepare' ),
			)
		);

		register_rest_route(
			$namespace,
			'/hr/replacement/workforce/requisitions/(?P<id>\d+)/decision',
			array(
				'methods' => WP_REST_Server::CREATABLE,
				'callback' => function ( $request ) {
					return $this->invoke( function () use ( $request ) {
						$payload = $this->payload( $request );
						return $this->service()->decide_requisition(
							(int) $request['id'],
							(int) ( $payload['version_no'] ?? 0 ),
							$payload['decision'] ?? '',
							$payload['reason'] ?? '',
							$payload['budget_reference'] ?? null,
							$this->idempotency_key( $request )
						);
					} );
				},
				'permission_callback' => array( $this, 'can_approve' ),
			)
		);

		register_rest_route(
			$namespace,
			'/hr/replacement/workforce/requisitions/(?P<id>\d+)/open',
			array(
				'methods' => WP_REST_Server::CREATABLE,
				'callback' => function ( $request ) {
					return $this->invoke( function () use ( $request ) {
						$payload = $this->payload( $request );
						return $this->service()->open_requisition( (int) $request['id'], (int) ( $payload['version_no'] ?? 0 ), $this->idempotency_key( $request ) );
					} );
				},
				'permission_callback' => array( $this, 'can_prepare' ),
			)
		);
	}
}
