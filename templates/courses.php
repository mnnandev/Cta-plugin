<?php
/**
 * Course catalog template for [cta_course_catalog] shortcode.
 *
 * @package CTA_LMS
 *
 * @var array  $courses         Course objects from the database.
 * @var array  $categories      Unique category names.
 * @var string $active_category Active category filter.
 * @var string $search          Current search term.
 * @var int    $columns         Grid column count.
 * @var int    $limit           Course limit (-1 for all).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$course_count = is_array( $courses ) ? count( $courses ) : 0;
$grid_class   = 'cta-courses-grid cta-courses-grid--cols-' . absint( $columns );
?>
<div class="cta-plugin-wrapper">
<div class="cta-lms cta-course-catalog" data-limit="<?php echo esc_attr( (int) $limit ); ?>">
	<div class="cta-catalog-inner">
	<?php if ( empty( $courses ) ) : ?>
		<div class="cta-empty-state">
			<div class="cta-empty-state__icon" aria-hidden="true">&#128218;</div>
			<h3><?php echo esc_html__( 'No courses available yet', 'cta-lms' ); ?></h3>
			<p><?php echo esc_html__( 'Check back soon — courses are being added.', 'cta-lms' ); ?></p>
		</div>
	<?php else : ?>
		<div class="cta-filter-bar">
			<div class="cta-filter-bar__row">
				<input
					type="text"
					id="cta-course-search"
					class="cta-filter-bar__search form-input"
					placeholder="<?php echo esc_attr__( 'Search courses...', 'cta-lms' ); ?>"
					value="<?php echo esc_attr( $search ); ?>"
					aria-label="<?php echo esc_attr__( 'Search courses', 'cta-lms' ); ?>"
				>

				<div class="cta-filter-bar__pills" role="group" aria-label="<?php echo esc_attr__( 'Filter by category', 'cta-lms' ); ?>">
					<button
						type="button"
						class="cta-pill <?php echo empty( $active_category ) ? 'cta-pill--active' : ''; ?>"
						data-category=""
					>
						<?php echo esc_html__( 'All Courses', 'cta-lms' ); ?>
					</button>

					<?php foreach ( $categories as $cat ) : ?>
						<button
							type="button"
							class="cta-pill <?php echo ( $active_category === $cat ) ? 'cta-pill--active' : ''; ?>"
							data-category="<?php echo esc_attr( $cat ); ?>"
						>
							<?php echo esc_html( $cat ); ?>
						</button>
					<?php endforeach; ?>
				</div>

				<select id="cta-course-sort" class="cta-filter-bar__sort form-select" aria-label="<?php echo esc_attr__( 'Sort courses', 'cta-lms' ); ?>">
					<option value="default"><?php echo esc_html__( 'Sort by: Default', 'cta-lms' ); ?></option>
					<option value="price_low"><?php echo esc_html__( 'Price: Low to High', 'cta-lms' ); ?></option>
					<option value="price_high"><?php echo esc_html__( 'Price: High to Low', 'cta-lms' ); ?></option>
					<option value="ce_hours"><?php echo esc_html__( 'CE Hours', 'cta-lms' ); ?></option>
				</select>

				<span class="cta-filter-bar__count">
					<?php
					printf(
						/* translators: %d: number of courses */
						esc_html__( 'Showing %d courses', 'cta-lms' ),
						(int) $course_count
					);
					?>
				</span>
			</div>
		</div>

		<div id="cta-courses-loader" class="cta-loader" style="display:none" aria-hidden="true">
			<div class="cta-loader__spinner"></div>
			<p><?php echo esc_html__( 'Loading courses...', 'cta-lms' ); ?></p>
		</div>

		<div id="cta-courses-grid" class="<?php echo esc_attr( $grid_class ); ?>">
			<?php foreach ( $courses as $course ) : ?>
				<?php include CTA_PLUGIN_DIR . 'templates/partials/course-card.php'; ?>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
	</div>
</div>
</div>
