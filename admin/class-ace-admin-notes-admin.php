<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
class Ace_Admin_Notes_Admin {

	private $plugin_name;
	private $version;
	private $option_key = 'ace_admin_notes_items';
	private $settings_key = 'ace_admin_notes_settings';

	private function get_capability() {
		return apply_filters( 'ace_admin_notes_capability', 'edit_posts' );
	}

	private function current_screen_id() {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return '';
		}
		$screen = get_current_screen();
		return $screen ? $screen->id : '';
	}

	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

	}

	public function enqueue_styles() {

		if ( ! current_user_can( $this->get_capability() ) ) {
			return;
		}
		wp_enqueue_style($this->plugin_name,plugin_dir_url( __FILE__ ) . 'css/ace-admin-notes-admin.css',array(),filemtime( plugin_dir_path( __FILE__ ) . 'css/ace-admin-notes-admin.css' ),'all');
	}

	public function enqueue_scripts() {

		if ( ! current_user_can( $this->get_capability() ) ) {
			return;
		}

		$notes    = $this->get_notes();
		$settings = $this->get_settings();
		$screen   = $this->current_screen_id();
		$current_user  = wp_get_current_user();
		$current_roles = (array) $current_user->roles;

		if ( ! empty( $settings['allowed_roles'] ) && empty( array_intersect( $current_roles, $settings['allowed_roles'] ) ) ) {
			return;
		}

	$js_file = plugin_dir_path( __FILE__ ) . 'js/ace-admin-notes-admin.js';
		wp_enqueue_script(
			$this->plugin_name,
			plugin_dir_url( __FILE__ ) . 'js/ace-admin-notes-admin.js',
			array( 'jquery', 'jquery-ui-draggable', 'jquery-ui-resizable' ),
			file_exists( $js_file ) ? filemtime( $js_file ) : $this->version,
			true
		);

		wp_localize_script(
			$this->plugin_name,
			'AceAdminNotesData',
			array(
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'nonce'     => wp_create_nonce( 'ace_admin_notes_nonce' ),
				'notes'     => array_values( $this->filter_active_notes( $notes ) ),
				'settings'  => $settings,
				'palette'   => $this->get_palette(),
				'screenId'  => $screen,
				'userRoles' => $current_roles,
				'canManage' => current_user_can( $this->get_capability() ),
				'manageUrl' => admin_url( 'admin.php?page=ace-admin-notes' ),
			)
		);

	}

	public function add_menu_pages() {
		$cap = $this->get_capability();

		add_menu_page(
			__( 'Ace Admin Notes', 'ace-admin-notes' ),
			__( 'Ace Admin Notes', 'ace-admin-notes' ),
			$cap,
			'ace-admin-notes',
			array( $this, 'render_notes_page' ),
			'dashicons-sticky',
			65
		);

		add_submenu_page(
			'ace-admin-notes',
			__( 'Notes', 'ace-admin-notes' ),
			__( 'Notes', 'ace-admin-notes' ),
			$cap,
			'ace-admin-notes',
			array( $this, 'render_notes_page' )
		);
	}

	public function render_notes_page() {
		if ( ! current_user_can( $this->get_capability() ) ) {
			return;
		}

		$messages = array();
		// if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['ace_admin_notes_nonce_field'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ace_admin_notes_nonce_field'] ) ), 'ace_admin_notes_nonce' ) ) {
		if (isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['ace_admin_notes_nonce_field'] ) && wp_verify_nonce(sanitize_text_field( wp_unslash( $_POST['ace_admin_notes_nonce_field'] ) ),'ace_admin_notes_nonce')) {
			$action = isset( $_POST['aan_action'] ) ? sanitize_text_field( wp_unslash( $_POST['aan_action'] ) ) : '';
			if ( 'add_note' === $action ) {
				$this->handle_add_note();
				$messages[] = __( 'Note added.', 'ace-admin-notes' );
			} elseif ( 'edit_note' === $action ) {
				$this->handle_edit_note();
				$messages[] = __( 'Note updated.', 'ace-admin-notes' );
			} elseif ( 'delete_note' === $action ) {
				$this->handle_delete_note();
				$messages[] = __( 'Note deleted.', 'ace-admin-notes' );
			} elseif ( 'toggle_active' === $action ) {
				$this->handle_toggle_active();
				$messages[] = __( 'Note updated.', 'ace-admin-notes' );
			}
		}

		$notes      = $this->get_notes();
		$palette    = $this->get_palette();
		$edit_id    = isset( $_GET['edit'] ) ? sanitize_text_field( wp_unslash( $_GET['edit'] ) ) : '';
		$edit_note  = null;
		if ( $edit_id ) {
			foreach ( $notes as $n ) {
				if ( $n['id'] === $edit_id ) {
					$edit_note = $n;
					break;
				}
			}
		}


		
		
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Ace Admin Notes', 'ace-admin-notes' ); ?></h1>
			<?php foreach ( $messages as $msg ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $msg ); ?></p></div>
			<?php endforeach; ?>

			<div class="ace-notes-admin-card">
				<h2><?php echo $edit_note ? esc_html__( 'Edit Note', 'ace-admin-notes' ) : esc_html__( 'Create Note', 'ace-admin-notes' ); ?></h2>
				<form method="post">
					<?php wp_nonce_field( 'ace_admin_notes_nonce', 'ace_admin_notes_nonce_field' ); ?>
					<input type="hidden" name="aan_action" value="<?php echo $edit_note ? 'edit_note' : 'add_note'; ?>" />
					<?php if ( $edit_note ) : ?>
						<input type="hidden" name="id" value="<?php echo esc_attr( $edit_note['id'] ); ?>" />
					<?php endif; ?>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="aan-title"><?php esc_html_e( 'Title', 'ace-admin-notes' ); ?></label></th>
							<td><input type="text" id="aan-title" name="title" class="regular-text" required value="<?php echo esc_attr( $edit_note ? $edit_note['title'] : '' ); ?>" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="aan-content"><?php esc_html_e( 'Content', 'ace-admin-notes' ); ?></label></th>
							<td><textarea id="aan-content" name="content" class="large-text" rows="4" required><?php echo esc_textarea( $edit_note ? $edit_note['content'] : '' ); ?></textarea></td>
						</tr>
						<tr>
							<th scope="row"><label for="aan-color"><?php esc_html_e( 'Color', 'ace-admin-notes' ); ?></label></th>
							<td>
								<select id="aan-color" name="color">
									<?php foreach ( $palette as $key => $color ) : ?>
										<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $edit_note ? $edit_note['color'] : $this->get_settings()['default_color'], $key ); ?>><?php echo esc_html( ucfirst( $key ) ); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Active', 'ace-admin-notes' ); ?></th>
							<td><label><input type="checkbox" name="active" value="1" <?php checked( $edit_note ? $edit_note['active'] : true ); ?> /> <?php esc_html_e( 'Show on admin screens', 'ace-admin-notes' ); ?></label></td>
						</tr>
					</table>
					<?php submit_button( $edit_note ? __( 'Update Note', 'ace-admin-notes' ) : __( 'Add Note', 'ace-admin-notes' ) ); ?>
				</form>
			</div>

			<div class="ace-notes-admin-card">
				<h2><?php esc_html_e( 'Notes', 'ace-admin-notes' ); ?></h2>
				<?php if ( empty( $notes ) ) : ?>
					<p><?php esc_html_e( 'No notes yet.', 'ace-admin-notes' ); ?></p>
				<?php else : ?>
					<table class="widefat striped ace-notes-admin-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Title', 'ace-admin-notes' ); ?></th>
								<th><?php esc_html_e( 'Active', 'ace-admin-notes' ); ?></th>
								<th><?php esc_html_e( 'Color', 'ace-admin-notes' ); ?></th>
								<th><?php esc_html_e( 'Actions', 'ace-admin-notes' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $notes as $note ) : ?>
								<tr>
									<td><?php echo esc_html( $note['title'] ); ?></td>
									<td><?php echo ! empty( $note['active'] ) ? esc_html__( 'Yes', 'ace-admin-notes' ) : esc_html__( 'No', 'ace-admin-notes' ); ?></td>
									<td><span class="aan-color-chip" style="background:<?php echo esc_attr( $this->resolve_color( $note['color'] ) ); ?>"></span> <?php echo esc_html( ucfirst( $note['color'] ) ); ?></td>
									<td class="aan-actions">
										<a class="button" href="<?php echo esc_url( add_query_arg( array( 'page' => 'ace-admin-notes', 'edit' => $note['id'] ), admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Edit', 'ace-admin-notes' ); ?></a>
										
										<form method="post" style="display:inline-block;">
											<?php wp_nonce_field( 'ace_admin_notes_nonce', 'ace_admin_notes_nonce_field' ); ?>
											<input type="hidden" name="aan_action" value="toggle_active" />
											<input type="hidden" name="id" value="<?php echo esc_attr( $note['id'] ); ?>" />
											<input type="hidden" name="active" value="<?php echo empty( $note['active'] ) ? '1' : '0'; ?>" />
											<button class="button button-secondary" type="submit"><?php echo empty( $note['active'] ) ? esc_html__( 'Activate', 'ace-admin-notes' ) : esc_html__( 'Deactivate', 'ace-admin-notes' ); ?></button>
										</form>
										<form method="post" style="display:inline-block;margin-left:6px;">
											<?php wp_nonce_field( 'ace_admin_notes_nonce', 'ace_admin_notes_nonce_field' ); ?>
											<input type="hidden" name="aan_action" value="delete_note" />
											<input type="hidden" name="id" value="<?php echo esc_attr( $note['id'] ); ?>" />
											<button class="button button-link-delete" type="submit" onclick="return confirm('<?php echo esc_js( __( 'Delete this note?', 'ace-admin-notes' ) ); ?>');"><?php esc_html_e( 'Delete', 'ace-admin-notes' ); ?></button>
										</form>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	public function render_canvas() {
		if ( ! current_user_can( $this->get_capability() ) ) {
			return;
		}
		echo '<div id="ace-admin-notes-canvas" class="ace-admin-notes-canvas" aria-live="polite"></div>';
		echo '<button type="button" id="ace-admin-notes-toggle" class="ace-admin-notes-toggle" aria-expanded="true">' . esc_html__( 'Notes', 'ace-admin-notes' ) . '</button>';
		?>
		<div id="aan-modal" class="aan-modal" aria-hidden="true">
			<div class="aan-modal__backdrop"></div>
			<div class="aan-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="aan-modal-title">
				<div class="aan-modal__header">
					<h2 id="aan-modal-title"><?php esc_html_e( 'Add Note', 'ace-admin-notes' ); ?></h2>
					<button type="button" class="aan-modal__close" aria-label="<?php esc_attr_e( 'Close', 'ace-admin-notes' ); ?>">×</button>
				</div>
				<form id="aan-modal-form">
					<p><label><?php esc_html_e( 'Title', 'ace-admin-notes' ); ?><br/><input type="text" name="title" class="regular-text" required></label></p>
					<p><label><?php esc_html_e( 'Content', 'ace-admin-notes' ); ?><br/><textarea name="content" rows="4" class="large-text" required></textarea></label></p>
					<p><label><?php esc_html_e( 'Color', 'ace-admin-notes' ); ?><br/>
						<select name="color">
							<?php foreach ( $this->get_palette() as $key => $color ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( ucfirst( $key ) ); ?></option>
							<?php endforeach; ?>
						</select>
					</label></p>
					<p><label><input type="checkbox" name="active" value="1" checked> <?php esc_html_e( 'Active', 'ace-admin-notes' ); ?></label></p>
					<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Save note', 'ace-admin-notes' ); ?></button></p>
				</form>
			</div>
		</div>
		<?php
	}

	public function ajax_add_note() {
		$this->verify_permissions();
		check_ajax_referer( 'ace_admin_notes_nonce', 'nonce' );

		$note    = $this->sanitize_note_from_request();
		$notes   = $this->get_notes();
		$notes[] = $note;
		$this->save_notes( $notes );

		wp_send_json_success(
			array(
				'note' => $note,
			)
		);
	}

	public function ajax_fetch_notes() {
		$this->verify_permissions();
		check_ajax_referer( 'ace_admin_notes_nonce', 'nonce' );

		$notes    = $this->get_notes();
		$settings = $this->get_settings();

		wp_send_json_success(
			array(
				'notes'    => array_values( $this->filter_active_notes( $notes ) ),
				'settings' => $settings,
				'palette'  => $this->get_palette(),
			)
		);
	}

	public function ajax_update_note() {
		$this->verify_permissions();
		check_ajax_referer( 'ace_admin_notes_nonce', 'nonce' );

		$id    = isset( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';
		$notes = $this->get_notes();
		foreach ( $notes as $index => $note ) {
			if ( $note['id'] === $id ) {
				$notes[ $index ] = $this->merge_note_update( $note, $_POST );
				$this->save_notes( $notes );
				wp_send_json_success( array( 'note' => $notes[ $index ] ) );
			}
		}

		wp_send_json_error( array( 'message' => __( 'Note not found.', 'ace-admin-notes' ) ), 404 );
	}

	public function ajax_delete_note() {
		$this->verify_permissions();
		check_ajax_referer( 'ace_admin_notes_nonce', 'nonce' );

		$id    = isset( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';
		$notes = $this->get_notes();
		$notes = array_values(
			array_filter(
				$notes,
				function ( $note ) use ( $id ) {
					return $note['id'] !== $id;
				}
			)
		);
		$this->save_notes( $notes );

		wp_send_json_success();
	}

	private function verify_permissions() {
		if ( ! current_user_can( $this->get_capability() ) ) {
			wp_send_json_error( array( 'message' => __( 'Not allowed.', 'ace-admin-notes' ) ), 403 );
		}
	}

	private function get_notes() {
		$notes = get_option( $this->option_key, array() );
		return is_array( $notes ) ? $notes : array();
	}

	private function get_settings() {
		$settings = get_option(
			$this->settings_key,
			array(
				'overlay_enabled' => true,
				'default_color'   => 'yellow',
				'excluded_screens' => array(),
				'allowed_roles'    => array(),
			)
		);

		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		$settings = wp_parse_args(
			$settings,
			array(
				'overlay_enabled' => true,
				'default_color'   => 'yellow',
				'excluded_screens' => array(),
				'allowed_roles'    => array(),
			)
		);

		return $settings;
	}

	private function save_notes( $notes ) {
		update_option( $this->option_key, array_values( $notes ) );
	}

	private function filter_active_notes( $notes ) {
		return array_values(
			array_filter(
				$notes,
				function ( $note ) {
					return ! empty( $note['active'] ) && empty( $note['archived'] );
				}
			)
		);
	}

	private function sanitize_note_from_request() {
	if (isset( $_POST['ace_admin_notes_nonce_field'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ace_admin_notes_nonce_field'] ) ),'ace_admin_notes_nonce')) {

		$title   = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		$content = isset( $_POST['content'] ) ? sanitize_textarea_field( wp_unslash( $_POST['content'] ) ) : '';
		$color   = isset( $_POST['color'] ) ? sanitize_text_field( wp_unslash( $_POST['color'] ) ) : $this->get_settings()['default_color'];
		$active  = isset( $_POST['active'] ) ? (bool) $_POST['active'] : false;
		return array(
			'id'        => wp_generate_uuid4(),
			'title'     => $title,
			'content'   => $content,
			'color'     => $color,
			'active'    => $active,
			'hidden'    => false,
			'minimized' => false,
			'archived'  => false,
			'x'         => 40,
			'y'         => 120,
			'width'     => 260,
			'height'    => 200,
			'author'    => get_current_user_id(),
		);
		};
	}

	private function merge_note_update( $note, $payload ) {
		$fields = array( 'title', 'content', 'color', 'hidden', 'minimized', 'active', 'archived' );

		foreach ( $fields as $field ) {
			if ( isset( $payload[ $field ] ) ) {
				$value = wp_unslash( $payload[ $field ] );
				if ( 'content' === $field ) {
					$value = sanitize_textarea_field( $value );
				} elseif ( in_array( $field, array( 'hidden', 'minimized', 'active', 'archived' ), true ) ) {
					$value = (bool) $value;
				} else {
					$value = sanitize_text_field( $value );
				}
				$note[ $field ] = $value;
			}
		}

		$numeric_fields = array(
			'x'      => 'absint',
			'y'      => 'absint',
			'width'  => 'absint',
			'height' => 'absint',
		);

		foreach ( $numeric_fields as $field => $sanitizer ) {
			if ( isset( $payload[ $field ] ) ) {
				$note[ $field ] = call_user_func( $sanitizer, $payload[ $field ] );
			}
		}

		return $note;
	}

	private function get_palette() {
		return array(
			'yellow' => '#fff7a3',
			'blue'   => '#d7ecff',
			'green'  => '#d9f7be',
			'pink'   => '#ffd6e7',
			'gray'   => '#e9ecef',
		);
	}

	private function resolve_color( $key ) {
		$palette = $this->get_palette();
		return isset( $palette[ $key ] ) ? $palette[ $key ] : $key;
	}

	private function handle_add_note() {
		$note    = $this->sanitize_note_from_request();
		$notes   = $this->get_notes();
		$notes[] = $note;
		$this->save_notes( $notes );
	}

	private function handle_edit_note() {
	if (isset( $_POST['ace_admin_notes_nonce_field'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ace_admin_notes_nonce_field'] ) ),'ace_admin_notes_nonce')) {
		$id    = isset( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';
		if ( ! $id ) {
			return;
		}
		$notes = $this->get_notes();
		foreach ( $notes as $index => $note ) {
			if ( $note['id'] === $id ) {
				$notes[ $index ] = $this->merge_note_update(
					$note,
					array(
						'title'   => isset( $_POST['title'] ) ? sanitize_text_field(wp_unslash( $_POST['title'] )) : $note['title'],
						'content' => isset( $_POST['content'] ) ? sanitize_text_field(wp_unslash( $_POST['content']) ) : $note['content'],
						'color'   => isset( $_POST['color'] ) ? sanitize_text_field(wp_unslash( $_POST['color'] )) : $note['color'],
						'active'  => isset( $_POST['active'] ) ? 1 : 0,
					)
				);
				break;
			}
		}
		$this->save_notes( $notes );
	}
	}

	private function handle_delete_note() {
	if (isset( $_POST['ace_admin_notes_nonce_field'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ace_admin_notes_nonce_field'] ) ),'ace_admin_notes_nonce')) {
		$id    = isset( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';
		$notes = $this->get_notes();
		$notes = array_values(
			array_filter(
				$notes,
				function ( $note ) use ( $id ) {
					return isset( $note['id'] ) && $note['id'] !== $id;
				}
			)
		);
	
		$this->save_notes( $notes );
		}
	}

	private function handle_toggle_active() {
		if (isset( $_POST['ace_admin_notes_nonce_field'] ) && wp_verify_nonce(sanitize_text_field( wp_unslash( $_POST['ace_admin_notes_nonce_field'] ) ),'ace_admin_notes_nonce')) {
		$id     = isset( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';
		$active = isset( $_POST['active'] ) ? (bool) $_POST['active'] : false;
		$notes  = $this->get_notes();

		foreach ( $notes as $index => $note ) {
			if ( $note['id'] === $id ) {
				$notes[ $index ]['active'] = $active;
				$notes[ $index ]['hidden'] = false;
				break;
			}
		}
		$this->save_notes( $notes );
	}
	}

	public function add_admin_bar_items( $bar ) {
		if ( ! current_user_can( $this->get_capability() ) ) {
			return;
		}

		$bar->add_node(
			array(
				'id'    => 'ace-notes',
				'title' => __( 'Ace Notes', 'ace-admin-notes' ),
				'href'  => admin_url( 'admin.php?page=ace-admin-notes' ),
			)
		);

		$bar->add_node(
			array(
				'id'     => 'ace-notes-add',
				'parent' => 'ace-notes',
				'title'  => __( 'Add note', 'ace-admin-notes' ),
				'href'   => '#',
				'meta'   => array( 'class' => 'aan-open-modal' ),
			)
		);

		$bar->add_node(
			array(
				'id'     => 'ace-notes-toggle',
				'parent' => 'ace-notes',
				'title'  => __( 'Hide/Show notes', 'ace-admin-notes' ),
				'href'   => '#',
				'meta'   => array( 'class' => 'aan-toggle-overlay' ),
			)
		);
	}

	public function ajax_toggle_overlay() {
		$this->verify_permissions();
		check_ajax_referer( 'ace_admin_notes_nonce', 'nonce' );
		$settings                    = $this->get_settings();
		$settings['overlay_enabled'] = empty( $settings['overlay_enabled'] );
		update_option( $this->settings_key, $settings );
		wp_send_json_success(
			array(
				'overlay_enabled' => (bool) $settings['overlay_enabled'],
			)
		);
	}

}
