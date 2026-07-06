<?php
/**
 * Admin courses list view.
 *
 * @package CTA_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$notice = sanitize_text_field( wp_unslash( $_GET['cta_notice'] ?? '' ) );
?>
<div class="wrap cta-admin-wrap">
	<div class="cta-admin-header-row">
		<h1><?php esc_html_e( 'Courses', 'cta-lms' ); ?></h1>
		<a class="page-title-action" href="<?php echo esc_url( admin_url( 'admin.php?page=cta-lms-course-edit' ) ); ?>"><?php esc_html_e( 'Add New Course', 'cta-lms' ); ?></a>
	</div>

	<?php if ( in_array( $notice, array( 'course_deleted', 'status_updated' ), true ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Course updated.', 'cta-lms' ); ?></p></div>
	<?php endif; ?>

	<form method="get" class="cta-admin-filters">
		<input type="hidden" name="page" value="cta-lms-courses">
		<select name="status">
			<option value="all" <?php selected( $status_filter, 'all' ); ?>><?php esc_html_e( 'All Statuses', 'cta-lms' ); ?></option>
			<option value="published" <?php selected( $status_filter, 'published' ); ?>><?php esc_html_e( 'Published', 'cta-lms' ); ?></option>
			<option value="draft" <?php selected( $status_filter, 'draft' ); ?>><?php esc_html_e( 'Draft', 'cta-lms' ); ?></option>
		</select>
		<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search by title', 'cta-lms' ); ?>">
		<button type="submit" class="button"><?php esc_html_e( 'Filter', 'cta-lms' ); ?></button>
	</form>

	<table class="widefat striped cta-admin-table">
		<thead>
			<tr>
				<th>#</th>
				<th><?php esc_html_e( 'Title', 'cta-lms' ); ?></th>
				<th><?php esc_html_e( 'CE Hours', 'cta-lms' ); ?></th>
				<th><?php esc_html_e( 'Price', 'cta-lms' ); ?></th>
				<th><?php esc_html_e( 'Category', 'cta-lms' ); ?></th>
				<th><?php esc_html_e( 'Status', 'cta-lms' ); ?></th>
				<th><?php esc_html_e( 'Enrollments', 'cta-lms' ); ?></th>
				<th><?php esc_html_e( 'Actions', 'cta-lms' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $courses ) ) : ?>
				<tr><td colspan="8"><?php esc_html_e( 'No courses found.', 'cta-lms' ); ?></td></tr>
			<?php else : ?>
				<?php foreach ( $courses as $course ) : ?>
					<tr>
						<td><?php echo esc_html( (string) $course->id ); ?></td>
						<td><strong><?php echo esc_html( $course->title ); ?></strong></td>
						<td><?php echo esc_html( rtrim( rtrim( number_format( (float) $course->ce_hours, 1, '.', '' ), '0' ), '.' ) ); ?></td>
						<td>$<?php echo esc_html( number_format( (float) $course->price, 2 ) ); ?></td>
						<td><?php echo esc_html( $course->category ? $course->category : '—' ); ?></td>
						<td><span class="cta-status-badge cta-status-badge--<?php echo esc_attr( $course->status ); ?>"><?php echo esc_html( ucfirst( $course->status ) ); ?></span></td>
						<td><?php echo esc_html( (string) ( $enrollment_counts[ (int) $course->id ] ?? 0 ) ); ?></td>
						<td class="cta-table-actions">
							<a class="button button-small" href="<?php echo esc_url( admin_url( 'admin.php?page=cta-lms-course-edit&course_id=' . (int) $course->id ) ); ?>"><?php esc_html_e( 'Edit', 'cta-lms' ); ?></a>
							<a class="button button-small" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=cta_toggle_course&course_id=' . (int) $course->id ), 'cta_toggle_course' ) ); ?>"><?php echo 'published' === $course->status ? esc_html__( 'Draft', 'cta-lms' ) : esc_html__( 'Publish', 'cta-lms' ); ?></a>
							<a class="button button-small button-link-delete cta-delete-course" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=cta_delete_course&course_id=' . (int) $course->id ), 'cta_delete_course' ) ); ?>"><?php esc_html_e( 'Delete', 'cta-lms' ); ?></a>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
</div>
