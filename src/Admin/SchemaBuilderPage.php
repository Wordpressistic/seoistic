<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\Admin;

use Wpistic\Seoistic\Addon\SchemaBlockRepository;

final class SchemaBuilderPage {

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'menu' ), 30 );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_post_seoistic_save_schema_block', array( $this, 'save' ) );
		add_action( 'admin_post_seoistic_delete_schema_block', array( $this, 'delete' ) );
		add_action( 'admin_post_seoistic_toggle_schema_block', array( $this, 'toggle' ) );
		add_action( 'admin_post_seoistic_export_schema_blocks', array( $this, 'export' ) );
		add_action( 'admin_post_seoistic_import_schema_blocks', array( $this, 'import' ) );
	}

	public function menu(): void {
		add_submenu_page( 'seoistic', __( 'Schema Builder', 'seoistic' ), __( 'Schema Builder', 'seoistic' ), 'manage_options', 'seoistic-schema-builder', array( $this, 'render' ) );
	}

	public function assets( string $hook ): void {
		if ( false === strpos( $hook, 'seoistic-schema-builder' ) ) {
			return;
		}
		wp_enqueue_style( 'seoistic-admin' );
		wp_enqueue_style( 'seoistic-aurora', SEOISTIC_URL . 'assets/css/aurora.css', array( 'seoistic-admin' ), SEOISTIC_VERSION );
		wp_enqueue_script( 'seoistic-aurora', SEOISTIC_URL . 'assets/js/aurora.js', array( 'seoistic-admin' ), SEOISTIC_VERSION, true );
		wp_enqueue_script( 'seoistic-schema-builder', SEOISTIC_URL . 'assets/js/schema-builder.js', array( 'seoistic-aurora' ), SEOISTIC_VERSION, true );
		wp_localize_script(
			'seoistic-schema-builder',
			'SeoisticSchemaBuilder',
			array(
				'variables' => ( new SchemaBlockRepository() )->variables(),
				'i18n'      => array(
					'invalidJson' => __( 'The JSON is invalid.', 'seoistic' ),
					'missingType' => __( 'The generated JSON-LD must include an @type.', 'seoistic' ),
					'previewError' => __( 'Preview could not be generated. Fix the highlighted fields.', 'seoistic' ),
					'required' => __( 'Required', 'seoistic' ),
					'recommended' => __( 'Recommended', 'seoistic' ),
					'validationFailed' => __( 'Validation failed. Correct the issues below and try again.', 'seoistic' ),
					'insertVariable' => __( 'Insert variable', 'seoistic' ),
					'confirmDelete' => __( 'Delete this schema block permanently?', 'seoistic' ),
				),
				'requirements' => array(
					'Article' => array( 'headline' ),
					'FAQPage' => array( 'mainEntity' ),
					'HowTo' => array( 'name', 'step' ),
					'Product' => array( 'name' ),
					'LocalBusiness' => array( 'name', 'address' ),
					'Event' => array( 'name', 'startDate', 'location' ),
					'Review' => array( 'itemReviewed', 'reviewRating', 'author' ),
					'Course' => array( 'name', 'provider' ),
					'Recipe' => array( 'name', 'image', 'recipeIngredient', 'recipeInstructions' ),
					'TravelAgency' => array( 'name' ),
					'SoftwareApplication' => array( 'name' ),
				),
				'fieldTypes' => $this->field_types(),
				'typeLabels' => $this->type_labels(),
				'typeFields' => $this->type_fields(),
			)
		);
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'seoistic' ) );
		}

		$repository = new SchemaBlockRepository();
		$this->notice();
		?>
		<div class="wrap seoistic-page">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<div class="seoistic-schema-layout">
				<?php $this->render_builder(); ?>
				<?php $this->render_sidebar( $repository->all() ); ?>
			</div>
		</div>
		<?php
	}

	private function render_builder(): void {
		?>
		<form class="seoistic-schema-editor" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="seoistic_save_schema_block">
			<input type="hidden" name="id" value="">
			<input type="hidden" name="rules" id="seoistic-schema-rules-json" value="<?php echo esc_attr( wp_json_encode( array( 'post_types' => array(), 'taxonomies' => array(), 'templates' => array(), 'url_patterns' => array() ) ) ); ?>">
			<?php wp_nonce_field( 'seoistic_schema_block' ); ?>
			<div class="seoistic-field">
				<label class="seoistic-field-label" for="seoistic-schema-title"><?php echo esc_html__( 'Block title', 'seoistic' ); ?></label>
				<input type="text" id="seoistic-schema-title" name="title" required>
			</div>
			<div class="seoistic-field">
				<label class="seoistic-field-label" for="seoistic-schema-type"><?php echo esc_html__( 'Schema type', 'seoistic' ); ?></label>
				<select id="seoistic-schema-type" name="type"></select>
			</div>
			<div id="seoistic-schema-fields"></div>
			<?php $this->render_rules(); ?>
			<div class="seoistic-field">
				<label><input type="checkbox" name="active" value="1" checked> <?php echo esc_html__( 'Active', 'seoistic' ); ?></label>
			</div>
			<button type="submit" class="seoistic-btn seoistic-btn-primary"><?php echo esc_html__( 'Validate and save block', 'seoistic' ); ?></button>
		</form>
		<?php
	}

	private function render_rules(): void {
		$post_types = get_post_types( array( 'public' => true ), 'objects' );
		$taxonomies = get_taxonomies( array( 'public' => true ), 'objects' );
		?>
		<div class="seoistic-schema-rules">
			<h2><?php echo esc_html__( 'Display rules', 'seoistic' ); ?></h2>
			<div class="seoistic-field">
				<label class="seoistic-field-label"><?php echo esc_html__( 'Post types', 'seoistic' ); ?></label>
				<?php foreach ( $post_types as $post_type ) : ?>
					<label><input type="checkbox" name="rules[post_types][]" value="<?php echo esc_attr( $post_type->name ); ?>"> <?php echo esc_html( $post_type->labels->singular_name ); ?></label>
				<?php endforeach; ?>
			</div>
			<div class="seoistic-field">
				<label class="seoistic-field-label"><?php echo esc_html__( 'Taxonomies', 'seoistic' ); ?></label>
				<?php foreach ( $taxonomies as $taxonomy ) : ?>
					<label><input type="checkbox" name="rules[taxonomies][]" value="<?php echo esc_attr( $taxonomy->name ); ?>"> <?php echo esc_html( $taxonomy->labels->singular_name ); ?></label>
				<?php endforeach; ?>
			</div>
			<div class="seoistic-field">
				<label class="seoistic-field-label"><?php echo esc_html__( 'Templates', 'seoistic' ); ?></label>
				<?php
				$templates = array(
					'front_page' => __( 'Front page', 'seoistic' ),
					'page' => __( 'Page', 'seoistic' ),
					'search' => __( 'Search results', 'seoistic' ),
					'author_archive' => __( 'Author archive', 'seoistic' ),
					'date_archive' => __( 'Date archive', 'seoistic' ),
					'other' => __( 'Other', 'seoistic' ),
				);
				foreach ( $post_types as $post_type ) {
					$templates[ $post_type->name . '_single' ] = sprintf( /* translators: %s: post type singular name. */ __( '%s single', 'seoistic' ), $post_type->labels->singular_name );
					if ( $post_type->has_archive ) {
						$templates[ $post_type->name . '_archive' ] = sprintf( /* translators: %s: post type singular name. */ __( '%s archive', 'seoistic' ), $post_type->labels->singular_name );
					}
				}
				foreach ( $taxonomies as $taxonomy ) {
					$templates[ $taxonomy->name . '_archive' ] = sprintf( /* translators: %s: taxonomy singular name. */ __( '%s archive', 'seoistic' ), $taxonomy->labels->singular_name );
				}
				foreach ( $templates as $value => $text ) :
					?>
					<label><input type="checkbox" name="rules[templates][]" value="<?php echo esc_attr( $value ); ?>"> <?php echo esc_html( $text ); ?></label>
				<?php endforeach; ?>
			</div>
			<div class="seoistic-field">
				<label class="seoistic-field-label" for="seoistic-schema-url-patterns"><?php echo esc_html__( 'URL patterns', 'seoistic' ); ?></label>
				<textarea id="seoistic-schema-url-patterns" rows="2" placeholder="/courses/*"></textarea>
				<p class="description"><?php echo esc_html__( 'One pattern per line. Use * as a wildcard.', 'seoistic' ); ?></p>
			</div>
		</div>
		<?php
	}

	private function render_sidebar( array $blocks ): void {
		?>
		<div class="seoistic-schema-preview">
			<div class="seoistic-preview-toggle">
				<button type="button" class="is-active" data-seoistic-preview-tab="preview"><?php echo esc_html__( 'Live preview', 'seoistic' ); ?></button>
				<button type="button" data-seoistic-preview-tab="issues"><?php echo esc_html__( 'Issues', 'seoistic' ); ?></button>
			</div>
			<pre id="seoistic-schema-preview" class="seoistic-json-preview seoistic-preview-pane is-active">{}</pre>
			<div id="seoistic-schema-issues" class="seoistic-preview-pane"></div>
		</div>
		<div class="seoistic-schema-list">
			<h2><?php echo esc_html__( 'Saved blocks', 'seoistic' ); ?></h2>
			<?php if ( array() === $blocks ) : ?>
				<p><?php echo esc_html__( 'No schema blocks yet.', 'seoistic' ); ?></p>
			<?php else : ?>
				<?php foreach ( $blocks as $block ) : ?>
					<article class="seoistic-schema-item">
						<h3><?php echo esc_html( $block['title'] ); ?></h3>
						<p><?php echo esc_html( $block['type'] ); ?></p>
						<div>
							<button type="button" class="button" data-seoistic-edit="<?php echo esc_attr( wp_json_encode( $block ) ); ?>"><?php echo esc_html__( 'Edit', 'seoistic' ); ?></button>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
								<input type="hidden" name="action" value="seoistic_toggle_schema_block">
								<input type="hidden" name="id" value="<?php echo esc_attr( (string) $block['id'] ); ?>">
								<?php wp_nonce_field( 'seoistic_schema_block' ); ?>
								<button type="submit" class="button"><?php echo esc_html( empty( $block['active'] ) ? __( 'Activate', 'seoistic' ) : __( 'Deactivate', 'seoistic' ) ); ?></button>
							</form>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-seoistic-confirm="<?php echo esc_attr__( 'Delete this schema block permanently?', 'seoistic' ); ?>">
								<input type="hidden" name="action" value="seoistic_delete_schema_block">
								<input type="hidden" name="id" value="<?php echo esc_attr( (string) $block['id'] ); ?>">
								<?php wp_nonce_field( 'seoistic_schema_block' ); ?>
								<button type="submit" class="button button-link-delete"><?php echo esc_html__( 'Delete', 'seoistic' ); ?></button>
							</form>
						</div>
					</article>
				<?php endforeach; ?>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="seoistic_export_schema_blocks">
				<?php wp_nonce_field( 'seoistic_schema_blocks_transfer' ); ?>
				<button type="submit" class="button"><?php echo esc_html__( 'Export blocks', 'seoistic' ); ?></button>
			</form>
			<form class="seoistic-import-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
				<input type="hidden" name="action" value="seoistic_import_schema_blocks">
				<?php wp_nonce_field( 'seoistic_schema_blocks_transfer' ); ?>
				<input type="file" name="json" accept="application/json,.json" required>
				<button type="submit" class="button"><?php echo esc_html__( 'Import blocks', 'seoistic' ); ?></button>
			</form>
		</div>
		<?php
	}

	private function notice(): void {
		$message = isset( $_GET['message'] ) ? sanitize_key( wp_unslash( $_GET['message'] ) ) : '';
		if ( '' === $message ) {
			return;
		}
		$messages = array(
			'saved' => __( 'Schema block saved.', 'seoistic' ),
			'deleted' => __( 'Schema block deleted.', 'seoistic' ),
			'imported' => __( 'Schema blocks imported.', 'seoistic' ),
		);
		if ( isset( $messages[ $message ] ) ) {
			printf( '<div class="notice notice-success is-dismissible seoistic-notice" data-aurora-toast="success" data-aurora-toast-message="%s"><p>%s</p></div>', esc_attr( $messages[ $message ] ), esc_html( $messages[ $message ] ) );
		} elseif ( 'error' === $message || 'import-error' === $message ) {
			$issues = isset( $_GET['issues'] ) ? json_decode( sanitize_text_field( wp_unslash( $_GET['issues'] ) ), true ) : array();
			echo '<div class="notice notice-error is-dismissible seoistic-notice" data-aurora-toast="error" data-aurora-toast-message="' . esc_attr__( 'Validation failed. Please correct the block and try again.', 'seoistic' ) . '"><p>' . esc_html__( 'Validation failed. Please correct the block and try again.', 'seoistic' ) . '</p>';
			if ( is_array( $issues ) && array() !== $issues ) {
				echo '<ul>';
				foreach ( $issues as $issue ) {
					echo '<li>' . esc_html( (string) $issue ) . '</li>';
				}
				echo '</ul>';
			}
			echo '</div>';
		}
	}

	public function save(): void {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'seoistic_schema_block' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'seoistic' ) );
		}
		$repository = new SchemaBlockRepository();
		$result = $repository->save_from_request( $_POST );
		$status = (string) $result[0];
		$args = array( 'message' => 'success' === $status ? 'saved' : 'error' );
		if ( 'error' === $status ) {
			$args['issues'] = wp_json_encode( $result[1] );
		}
		wp_safe_redirect( add_query_arg( array_map( 'rawurlencode', $args ), admin_url( 'admin.php?page=seoistic-schema-builder' ) ) );
		exit;
	}

	public function delete(): void {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'seoistic_schema_block' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'seoistic' ) );
		}
		( new SchemaBlockRepository() )->delete( absint( $_POST['id'] ?? 0 ) );
		wp_safe_redirect( admin_url( 'admin.php?page=seoistic-schema-builder&message=deleted' ) );
		exit;
	}

	public function toggle(): void {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'seoistic_schema_block' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'seoistic' ) );
		}
		( new SchemaBlockRepository() )->toggle( absint( $_POST['id'] ?? 0 ) );
		wp_safe_redirect( admin_url( 'admin.php?page=seoistic-schema-builder' ) );
		exit;
	}

	public function export(): void {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'seoistic_schema_blocks_transfer' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'seoistic' ) );
		}
		$payload = array( 'version' => 1, 'blocks' => ( new SchemaBlockRepository() )->all() );
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=seoistic-schema-blocks.json' );
		echo wp_json_encode( $payload ); // phpcs:ignore WordPress.Security.EscapeOutput
		exit;
	}

	public function import(): void {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'seoistic_schema_blocks_transfer' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'seoistic' ) );
		}
		$path = (string) ( $_FILES['json']['tmp_name'] ?? '' );
		$decoded = '' !== $path && is_uploaded_file( $path ) ? json_decode( (string) file_get_contents( $path ), true ) : null; // phpcs:ignore WordPress.WP.AlternativeFunctions
		$blocks = is_array( $decoded ) && is_array( $decoded['blocks'] ?? null ) ? $decoded['blocks'] : array();
		$repository = new SchemaBlockRepository();
		$valid = true;
		$sanitized_blocks = array();
		foreach ( $blocks as $block ) {
			$sanitized = $repository->sanitize( array(
				'title' => $block['title'] ?? '',
				'type' => $block['type'] ?? '',
				'rules' => $block['rules'] ?? array(),
				'mapping' => $block['mapping'] ?? array(),
			) );
			if ( array() !== $repository->validate( $sanitized ) ) {
				$valid = false;
				break;
			}
			$sanitized_blocks[] = array( $block, $sanitized );
		}
		if ( $valid ) {
			foreach ( $sanitized_blocks as $entry ) {
				list( $block, $sanitized ) = $entry;
				$repository->save_from_request( array_merge( $block, array( 'title' => $sanitized['title'], 'type' => $sanitized['type'], 'rules' => wp_json_encode( $sanitized['rules'] ), 'mapping' => is_array( $block['mapping'] ) ? wp_json_encode( $block['mapping'] ) : (string) ( $block['mapping'] ?? '' ), 'active' => empty( $block['active'] ) ? 0 : 1 ) ) );
			}
		}
		wp_safe_redirect( admin_url( 'admin.php?page=seoistic-schema-builder&message=' . ( $valid && array() !== $blocks ? 'imported' : 'import-error' ) ) );
		exit;
	}

	private function field_types(): array {
		$textarea = array( 'description', 'address', 'openingHoursSpecification', 'geo', 'location', 'offers', 'aggregateRating', 'mainEntity', 'step', 'recipeIngredient', 'recipeInstructions', 'hasCourseInstance' );
		$result = array();
		foreach ( $textarea as $field ) {
			$result[ $field ] = 'structured';
		}
		return $result;
	}

	private function type_labels(): array {
		return array(
			'Article' => __( 'Article', 'seoistic' ),
			'FAQPage' => __( 'FAQ page', 'seoistic' ),
			'HowTo' => __( 'How-to', 'seoistic' ),
			'Product' => __( 'Product', 'seoistic' ),
			'LocalBusiness' => __( 'Local business', 'seoistic' ),
			'Event' => __( 'Event', 'seoistic' ),
			'Review' => __( 'Review', 'seoistic' ),
			'Course' => __( 'Course', 'seoistic' ),
			'Recipe' => __( 'Recipe', 'seoistic' ),
			'TravelAgency' => __( 'Travel agency', 'seoistic' ),
			'SoftwareApplication' => __( 'Software application', 'seoistic' ),
			'custom' => __( 'Custom JSON-LD', 'seoistic' ),
		);
	}

	private function type_fields(): array {
		$fields = array();
		foreach ( ( new SchemaBlockRepository() )->types() as $type => $type_fields ) {
			$fields[ $type ] = $type_fields;
		}
		return $fields;
	}
}
