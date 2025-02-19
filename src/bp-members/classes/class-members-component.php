<?php
/**
 * BP Rewrites Members Component.
 *
 * @package bp-rewrites\src\bp-members\classes
 * @since 1.0.0
 */

namespace BP\Rewrites;

/**
 * Defines the BuddyPress Members Component.
 *
 * @since 1.0.0
 */
class Members_Component extends \BP_Members_Component {
	/**
	 * Start the members component setup process.
	 *
	 * @since 1.0.0
	 */
	public function __construct() { /* phpcs:ignore */
		parent::__construct();
	}

	/**
	 * Set up component global variables.
	 *
	 * @since 1.0.0
	 *
	 * @see BP_Component::setup_globals() for a description of arguments.
	 *
	 * @param array $args See BP_Component::setup_globals() for a description.
	 */
	public function setup_globals( $args = array() ) {
		/*
		 * As the `\BP_Members_Component` is calling `parent::setup_globals()` before setting some
		 * additional globals, let's make sure the `rewrite_ids` property is available for these.
		 */
		add_action( 'bp_' . $this->id . '_setup_globals', array( $this, 'setup_additional_globals' ), 1 );
		parent::setup_globals( $args );

		/*
		 * NB: Setting the displayed user at this stage in `parent::setup_globals()` is too early.
		 * The displayed user has to be reset in `Members_Component::parse_query()`.
		 */
	}

	/**
	 * Set up the additional rewrite globals before `\BP_Members_Component` sets the
	 * Logged in user.
	 */
	public function setup_additional_globals() {
		remove_action( 'bp_' . $this->id . '_setup_globals', array( $this, 'setup_additional_globals' ), 1 );

		bp_component_setup_globals(
			array(
				'rewrite_ids' => array(
					'directory'                    => 'bp_members',
					'directory_type'               => 'bp_members_type',
					'single_item'                  => 'bp_member',
					'single_item_component'        => 'bp_member_component',
					'single_item_action'           => 'bp_member_action',
					'single_item_action_variables' => 'bp_member_action_variables',
					'member_register'              => 'bp_register',
					'member_activate'              => 'bp_activate',
					'member_activate_key'          => 'bp_activate_key',
				),
			),
			$this
		);

		// Set-up Extra permastructs for the register and activate pages.
		$this->register_permastruct = bp_get_signup_slug() . '/%' . $this->rewrite_ids['member_register'] . '%';
		$this->activate_permastruct = bp_get_activate_slug() . '/%' . $this->rewrite_ids['member_activate'] . '%';
	}

	/**
	 * Set up canonical stack for this component.
	 *
	 * @since 1.0.0
	 */
	public function setup_canonical_stack() {
		parent::setup_canonical_stack();

		$bp = buddypress();

		if ( bp_displayed_user_id() ) {
			$bp->canonical_stack['base_url'] = bp_member_rewrites_get_url( bp_displayed_user_id() );
			$item_component                  = bp_current_component();

			if ( $item_component ) {
				$bp->canonical_stack['component'] = bp_rewrites_get_slug( 'members', 'bp_member_' . $item_component, $item_component );

				if ( isset( $bp->default_component ) && bp_is_current_component( $bp->default_component ) && ! bp_current_action() ) {
					unset( $bp->canonical_stack['component'] );
				}
			}
		}
	}

	/**
	 * Set up component navigation.
	 *
	 * @since 1.0.0
	 *
	 * @see BP_Component::setup_nav() for a description of arguments.
	 *
	 * @param array $main_nav Optional. See BP_Component::setup_nav() for description.
	 * @param array $sub_nav  Optional. See BP_Component::setup_nav() for description.
	 */
	public function setup_nav( $main_nav = array(), $sub_nav = array() ) { /* phpcs:ignore */
		// The `$main_nav` needs to be reset.
		add_action( 'bp_' . $this->id . '_setup_nav', array( $this, 'reset_nav' ), 20 );

		parent::setup_nav( $main_nav, $sub_nav );
	}

	/**
	 * Reset the component's navigation using BP Rewrites.
	 *
	 * @since 1.0.0
	 */
	public function reset_nav() {
		remove_action( 'bp_' . $this->id . '_setup_nav', array( $this, 'reset_nav' ), 20 );

		// Get the main nav.
		$main_nav = buddypress()->members->nav->get_primary( array( 'component_id' => $this->id ), false );

		// Set the main nav slug.
		$main_nav = reset( $main_nav );
		$slug     = $main_nav['slug'];

		if ( bp_get_profile_slug() === $slug ) {
			$main_nav['rewrite_id'] = 'bp_member_profile';
		} elseif ( 'front' === $slug ) {
			$main_nav['rewrite_id'] = 'bp_member_front';
		}

		// Set the main nav `rewrite_id` property.
		$rewrite_id = $main_nav['rewrite_id'];

		// Reset the link using BP Rewrites.
		$main_nav['link'] = bp_members_rewrites_get_nav_url(
			array(
				'rewrite_id'     => $rewrite_id,
				'item_component' => $slug,
			)
		);

		// Update the primary nav item.
		buddypress()->members->nav->edit_nav( $main_nav, $slug );

		// Get the sub nav items for this main nav.
		$sub_nav_items = buddypress()->members->nav->get_secondary( array( 'parent_slug' => $slug ), false );

		// Loop inside it to reset the link using BP Rewrites before updating it.
		foreach ( $sub_nav_items as $sub_nav_item ) {
			$sub_nav_item['link'] = bp_members_rewrites_get_nav_url(
				array(
					'rewrite_id'     => $rewrite_id,
					'item_component' => $slug,
					'item_action'    => $sub_nav_item['slug'],
				)
			);

			// Update the secondary nav item.
			buddypress()->members->nav->edit_nav( $sub_nav_item, $sub_nav_item['slug'], $slug );
		}

		// In this case a new navigation is created for a fake profile component id.
		if ( bp_displayed_user_has_front_template() && ! bp_is_active( 'xprofile' ) ) {
			$profile_slug = bp_get_profile_slug();

			// Get the profile main nav.
			$profile_nav               = buddypress()->members->nav->get_primary( array( 'slug' => $profile_slug ), false );
			$profile_nav['rewrite_id'] = 'bp_member_profile';

			// Reset the link using BP Rewrites.
			$profile_nav['link'] = bp_members_rewrites_get_nav_url(
				array(
					'rewrite_id'     => 'bp_member_profile',
					'item_component' => $profile_slug,
				)
			);

			// Update the primary nav item.
			buddypress()->members->nav->edit_nav( $profile_nav, $profile_slug );

			// Get the sub nav items for this main nav.
			$profile_nav_sub_nav_items = buddypress()->members->nav->get_secondary( array( 'parent_slug' => $profile_slug ), false );

			// Loop inside it to reset the link using BP Rewrites before updating it.
			foreach ( $profile_nav_sub_nav_items as $profile_sub_nav_item ) {
				$profile_sub_nav_item['link'] = bp_members_rewrites_get_nav_url(
					array(
						'rewrite_id'     => 'bp_member_profile',
						'item_component' => $profile_slug,
						'item_action'    => $profile_sub_nav_item['slug'],
					)
				);

				// Update the secondary nav item.
				buddypress()->members->nav->edit_nav( $profile_sub_nav_item, $profile_sub_nav_item['slug'], $profile_slug );
			}
		}
	}

	/**
	 * Set up bp-members integration with the WordPress admin bar.
	 *
	 * @since 1.5.0
	 *
	 * @see BP_Component::setup_admin_bar() for a description of arguments.
	 *
	 * @param array $wp_admin_nav See BP_Component::setup_admin_bar()
	 *                            for description.
	 */
	public function setup_admin_bar( $wp_admin_nav = array() ) {
		add_filter( 'bp_' . $this->id . '_admin_nav', array( $this, 'reset_admin_nav' ), 10, 1 );

		parent::setup_admin_bar( $wp_admin_nav );
	}

	/**
	 * Reset WordPress admin bar nav items for the component.
	 *
	 * This should be done inside `BP_Members_Component::setup_admin_bar()`.
	 *
	 * @since 1.0.0
	 *
	 * @param array $wp_admin_nav The Admin Bar items.
	 * @return array The Admin Bar items.
	 */
	public function reset_admin_nav( $wp_admin_nav = array() ) {
		remove_filter( 'bp_' . $this->id . '_admin_nav', array( $this, 'reset_admin_nav' ), 10, 1 );

		if ( $wp_admin_nav ) {
			$parent_slug     = bp_get_profile_slug();
			$rewrite_id      = 'bp_member_profile';
			$root_nav_parent = buddypress()->my_account_menu_id;
			$user_id         = bp_loggedin_user_id();

			// NB: these slugs should probably be customizable.
			$viewes_slugs = array(
				'my-account-' . $this->id . '-public' => 'public',
				'my-account-' . $this->id . '-change-avatar' => 'change-avatar',
				'my-account-' . $this->id . '-change-cover-image' => 'change-cover-image',
			);

			foreach ( $wp_admin_nav as $key_item_nav => $item_nav ) {
				$item_nav_id = $item_nav['id'];
				$url_params  = array(
					'user_id'        => $user_id,
					'rewrite_id'     => $rewrite_id,
					'item_component' => $parent_slug,
				);

				if ( $root_nav_parent !== $item_nav['parent'] && isset( $viewes_slugs[ $item_nav_id ] ) ) {
					$url_params['item_action'] = $viewes_slugs[ $item_nav_id ];
				}

				$wp_admin_nav[ $key_item_nav ]['href'] = bp_members_rewrites_get_nav_url( $url_params );
			}
		}

		return $wp_admin_nav;
	}

	/**
	 * Add the component's rewrite tags.
	 *
	 * @since ?.0.0
	 *
	 * @param array $rewrite_tags Optional. See BP_Component::add_rewrite_tags() for
	 *                            description.
	 */
	public function add_rewrite_tags( $rewrite_tags = array() ) {
		$rewrite_tags = array(
			'directory'                    => array(
				'id'    => '%' . $this->rewrite_ids['directory'] . '%',
				'regex' => '([1]{1,})',
			),
			'directory-type'               => array(
				'id'    => '%' . $this->rewrite_ids['directory_type'] . '%',
				'regex' => '([^/]+)',
			),
			'member-register'              => array(
				'id'    => '%' . $this->rewrite_ids['member_register'] . '%',
				'regex' => '([1]{1,})',
			),
			'member-activate'              => array(
				'id'    => '%' . $this->rewrite_ids['member_activate'] . '%',
				'regex' => '([1]{1,})',
			),
			'member-activate-key'          => array(
				'id'    => '%' . $this->rewrite_ids['member_activate_key'] . '%',
				'regex' => '([^/]+)',
			),
			'single-item'                  => array(
				'id'    => '%' . $this->rewrite_ids['single_item'] . '%',
				'regex' => '([^/]+)',
			),
			'single-item-component'        => array(
				'id'    => '%' . $this->rewrite_ids['single_item_component'] . '%',
				'regex' => '([^/]+)',
			),
			'single-item-action'           => array(
				'id'    => '%' . $this->rewrite_ids['single_item_action'] . '%',
				'regex' => '([^/]+)',
			),
			'single-item-action-variables' => array(
				'id'    => '%' . $this->rewrite_ids['single_item_action_variables'] . '%',
				'regex' => '(.+?)',
			),
		);

		bp_component_add_rewrite_tags( $rewrite_tags );

		\BP_Component::add_rewrite_tags( $rewrite_tags );
	}

	/**
	 * Add the component's rewrite rules.
	 *
	 * @since ?.0.0
	 *
	 * @param array $rewrite_rules Optional. See BP_Component::add_rewrite_rules() for
	 *                             description.
	 */
	public function add_rewrite_rules( $rewrite_rules = array() ) {
		$rewrite_rules = array(
			'member-register'              => array(
				'regex' => bp_get_signup_slug(),
				'query' => 'index.php?' . $this->rewrite_ids['member_register'] . '=1',
			),
			'member-activate-key'          => array(
				'regex' => bp_get_activate_slug() . '/([^/]+)/?$',
				'query' => 'index.php?' . $this->rewrite_ids['member_activate'] . '=1&' . $this->rewrite_ids['member_activate_key'] . '=$matches[1]',
			),
			'member-activate'              => array(
				'regex' => bp_get_activate_slug(),
				'query' => 'index.php?' . $this->rewrite_ids['member_activate'] . '=1',
			),
			'paged-directory-type'         => array(
				'regex' => $this->root_slug . '/' . bp_get_members_member_type_base() . '/([^/]+)/page/?([0-9]{1,})/?$',
				'query' => 'index.php?' . $this->rewrite_ids['directory'] . '=1&' . $this->rewrite_ids['directory_type'] . '=$matches[1]&paged=$matches[2]',
			),
			'directory-type'               => array(
				'regex' => $this->root_slug . '/' . bp_get_members_member_type_base() . '/([^/]+)/?$',
				'query' => 'index.php?' . $this->rewrite_ids['directory'] . '=1&' . $this->rewrite_ids['directory_type'] . '=$matches[1]',
			),
			'paged-directory'              => array(
				'regex' => $this->root_slug . '/page/?([0-9]{1,})/?$',
				'query' => 'index.php?' . $this->rewrite_ids['directory'] . '=1&paged=$matches[1]',
			),
			'single-item-action-variables' => array(
				'regex' => $this->root_slug . '/([^/]+)/([^/]+)/([^/]+)/(.+?)/?$',
				'query' => 'index.php?' . $this->rewrite_ids['directory'] . '=1&' . $this->rewrite_ids['single_item'] . '=$matches[1]&' . $this->rewrite_ids['single_item_component'] . '=$matches[2]&' . $this->rewrite_ids['single_item_action'] . '=$matches[3]&' . $this->rewrite_ids['single_item_action_variables'] . '=$matches[4]',
			),
			'single-item-action'           => array(
				'regex' => $this->root_slug . '/([^/]+)/([^/]+)/([^/]+)/?$',
				'query' => 'index.php?' . $this->rewrite_ids['directory'] . '=1&' . $this->rewrite_ids['single_item'] . '=$matches[1]&' . $this->rewrite_ids['single_item_component'] . '=$matches[2]&' . $this->rewrite_ids['single_item_action'] . '=$matches[3]',
			),
			'single-item-component'        => array(
				'regex' => $this->root_slug . '/([^/]+)/([^/]+)/?$',
				'query' => 'index.php?' . $this->rewrite_ids['directory'] . '=1&' . $this->rewrite_ids['single_item'] . '=$matches[1]&' . $this->rewrite_ids['single_item_component'] . '=$matches[2]',
			),
			'single-item'                  => array(
				'regex' => $this->root_slug . '/([^/]+)/?$',
				'query' => 'index.php?' . $this->rewrite_ids['directory'] . '=1&' . $this->rewrite_ids['single_item'] . '=$matches[1]',
			),
			'directory'                    => array(
				'regex' => $this->root_slug,
				'query' => 'index.php?' . $this->rewrite_ids['directory'] . '=1',
			),
		);

		bp_component_add_rewrite_rules( $rewrite_rules );

		\BP_Component::add_rewrite_rules( $rewrite_rules );
	}

	/**
	 * Add the component's directory permastructs.
	 *
	 * @since ?.0.0
	 *
	 * @param array $permastructs Optional. See BP_Component::add_permastructs() for
	 *                            description.
	 */
	public function add_permastructs( $permastructs = array() ) {
		$permastructs = array(
			// Directory permastruct.
			$this->rewrite_ids['directory']       => array(
				'permastruct' => $this->directory_permastruct,
				'args'        => array(),
			),
			// Register permastruct.
			$this->rewrite_ids['member_register'] => array(
				'permastruct' => $this->register_permastruct,
				'args'        => array(),
			),
			// Activate permastruct.
			$this->rewrite_ids['member_activate'] => array(
				'permastruct' => $this->activate_permastruct,
				'args'        => array(),
			),
		);

		bp_component_add_permastructs( $permastructs );

		\BP_Component::add_permastructs( $permastructs );
	}

	/**
	 * Parse the WP_Query and eventually display the component's directory or single item.
	 *
	 * @since ?.0.0
	 *
	 * @param WP_Query $query Required. See BP_Component::parse_query() for
	 *                        description.
	 */
	public function parse_query( $query ) {
		// Init the current member and member type.
		$member      = false;
		$member_type = false;
		$member_data = bp_rewrites_get_member_data();

		if ( isset( $member_data['object'] ) && $member_data['object'] ) {
			bp_reset_query( trailingslashit( $this->root_slug ) . $GLOBALS['wp']->request, $query );
			$member = $member_data['object'];

			// Make sure the Member's screen is fired.
			add_action( 'bp_screens', 'bp_members_screen_display_profile', 3 );
		}

		// Which component are we displaying?
		$is_members_component  = 1 === (int) $query->get( $this->rewrite_ids['directory'] );
		$is_register_component = 1 === (int) $query->get( $this->rewrite_ids['member_register'] );
		$is_activate_component = 1 === (int) $query->get( $this->rewrite_ids['member_activate'] );

		// Get BuddyPress main instance.
		$bp = buddypress();

		if ( $is_members_component ) {
			$bp->current_component = 'members';
			$member_slug           = $query->get( $this->rewrite_ids['single_item'] );
			$member_type_slug      = $query->get( $this->rewrite_ids['directory_type'] );

			if ( $member_slug ) {
				$bp->current_component = '';

				// Unless root profiles are on, the member shouldn't be set yet.
				if ( ! $member ) {
					$member = get_user_by( $member_data['field'], $member_slug );

					if ( ! $member ) {
						bp_do_404();
						return;
					}
				}

				// If the member is marked as a spammer, 404 (unless logged-in user is a super admin).
				if ( bp_is_user_spammer( $member->ID ) ) {
					if ( bp_current_user_can( 'bp_moderate' ) ) {
						bp_core_add_message( __( 'This user has been marked as a spammer. Only site admins can view this profile.', 'buddypress' ), 'warning' );
					} else {
						bp_do_404();
						return;
					}
				}

				// Set the displayed user and the current item.
				$bp->displayed_user->id = $member->ID;
				$bp->current_item       = $member_slug;

				// The core userdata of the user who is currently being displayed.
				if ( ! isset( $bp->displayed_user->userdata ) || ! $bp->displayed_user->userdata ) {
					$bp->displayed_user->userdata = bp_core_get_core_userdata( bp_displayed_user_id() );
				}

				// Fetch the full name displayed user.
				if ( ! isset( $bp->displayed_user->fullname ) || ! $bp->displayed_user->fullname ) {
					$bp->displayed_user->fullname = '';
					if ( isset( $bp->displayed_user->userdata->display_name ) ) {
						$bp->displayed_user->fullname = $bp->displayed_user->userdata->display_name;
					}
				}

				// The domain for the user currently being displayed.
				if ( ! isset( $bp->displayed_user->domain ) || ! $bp->displayed_user->domain ) {
					$bp->displayed_user->domain = bp_member_rewrites_get_url( bp_displayed_user_id() );
				}

				// If A user is displayed, check if there is a front template.
				if ( bp_get_displayed_user() ) {
					$bp->displayed_user->front_template = bp_displayed_user_get_front_template();
				}

				$member_component = $query->get( $this->rewrite_ids['single_item_component'] );
				if ( $member_component ) {
					// Check if the member's component slug has been customized.
					$item_component_rewrite_id = bp_rewrites_get_custom_slug_rewrite_id( 'members', $member_component );
					if ( $item_component_rewrite_id ) {
						$member_component = str_replace( 'bp_member_', '', $item_component_rewrite_id );
					}

					$bp->current_component = $member_component;
				}

				$current_action = $query->get( $this->rewrite_ids['single_item_action'] );
				if ( $current_action ) {
					$bp->current_action = $current_action;
				}

				$action_variables = $query->get( $this->rewrite_ids['single_item_action_variables'] );
				if ( $action_variables ) {
					if ( ! is_array( $action_variables ) ) {
						$bp->action_variables = explode( '/', ltrim( $action_variables, '/' ) );
					} else {
						$bp->action_variables = $action_variables;
					}
				}

				// Is this a member type query?
			} elseif ( $member_type_slug ) {
				$member_type = bp_get_member_types(
					array(
						'has_directory'  => true,
						'directory_slug' => $member_type_slug,
					)
				);

				if ( $member_type ) {
					$member_type             = reset( $member_type );
					$bp->current_member_type = $member_type;
				} else {
					$bp->current_component = '';
					bp_do_404();
					return;
				}
			}

			/**
			 * Set the BuddyPress queried object.
			 */
			$query->queried_object    = get_post( $bp->pages->members->id );
			$query->queried_object_id = $query->queried_object->ID;

			if ( $member ) {
				$query->queried_object->single_item_name = $member->display_name;
			} elseif ( $member_type ) {
				$query->queried_object->directory_type_name = $member_type;
			}

			// Handle the custom registration page.
		} elseif ( $is_register_component ) {
			$bp->current_component = 'register';

			// Handle the custom activation page.
		} elseif ( $is_activate_component ) {
			$bp->current_component = 'activate';

			$current_action = $query->get( $this->rewrite_ids['member_activate_key'] );
			if ( $current_action ) {
				$bp->current_action = $current_action;
			}
		}

		bp_component_parse_query( $query );

		\BP_Component::parse_query( $query );
	}
}
