<?php
/**
 * Admin users list view.
 *
 * @package CTA_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap cta-admin-wrap">
	<h1><?php esc_html_e( 'CTA Users', 'cta-lms' ); ?></h1>

	<div class="cta-admin-tabs">
		<?php
		$tabs = array(
			'all'           => __( 'All', 'cta-lms' ),
			'licensed'      => __( 'Licensed Professionals', 'cta-lms' ),
			'associate'     => __( 'Associates', 'cta-lms' ),
			'administrator' => __( 'Administrators', 'cta-lms' ),
		);
		foreach ( $tabs as $key => $label ) :
			$url = add_query_arg(
				array(
					'page' => 'cta-lms-users',
					'role' => $key,
					's'    => $search,
				),
				admin_url( 'admin.php' )
			);
			?>
			<a class="cta-admin-tab <?php echo $role_filter === $key ? 'cta-admin-tab--active' : ''; ?>" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $label ); ?></a>
		<?php endforeach; ?>
	</div>

	<form method="get" class="cta-admin-filters">
		<input type="hidden" name="page" value="cta-lms-users">
		<input type="hidden" name="role" value="<?php echo esc_attr( $role_filter ); ?>">
		<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search name or email', 'cta-lms' ); ?>">
		<button type="submit" class="button"><?php esc_html_e( 'Search', 'cta-lms' ); ?></button>
	</form>

	<table class="widefat striped cta-admin-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Name', 'cta-lms' ); ?></th>
				<th><?php esc_html_e( 'Email', 'cta-lms' ); ?></th>
				<th><?php esc_html_e( 'Role', 'cta-lms' ); ?></th>
				<th><?php esc_html_e( 'Joined', 'cta-lms' ); ?></th>
				<th><?php esc_html_e( 'Enrolled', 'cta-lms' ); ?></th>
				<th><?php esc_html_e( 'Supervision', 'cta-lms' ); ?></th>
				<th><?php esc_html_e( 'Actions', 'cta-lms' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $users ) ) : ?>
				<tr><td colspan="7"><?php esc_html_e( 'No users found.', 'cta-lms' ); ?></td></tr>
			<?php else : ?>
				<?php
				global $wpdb;
				foreach ( $users as $user ) :
					$roles              = (array) $user->roles;
					$role_label         = ! empty( $roles ) ? ucwords( str_replace( array( 'cta_', '_' ), array( '', ' ' ), $roles[0] ) ) : '';
					$enrolled_count     = (int) $wpdb->get_var(
						$wpdb->prepare(
							"SELECT COUNT(*) FROM {$wpdb->prefix}cta_enrollments WHERE user_id = %d",
							$user->ID
						)
					);
					$supervision_status = (string) get_user_meta( $user->ID, 'cta_supervision_status', true );
					?>
					<tr>
						<td><strong><?php echo esc_html( $user->display_name ); ?></strong></td>
						<td><?php echo esc_html( $user->user_email ); ?></td>
						<td><?php echo esc_html( $role_label ); ?></td>
						<td><?php echo esc_html( wp_date( 'M j, Y', strtotime( $user->user_registered ) ) ); ?></td>
						<td><?php echo esc_html( (string) $enrolled_count ); ?></td>
						<td><?php echo esc_html( $supervision_status ? ucfirst( $supervision_status ) : '—' ); ?></td>
						<td class="cta-table-actions">
							<a class="button button-small" href="<?php echo esc_url( get_edit_user_link( $user->ID ) ); ?>"><?php esc_html_e( 'View Profile', 'cta-lms' ); ?></a>
							<button type="button" class="button button-small cta-view-user-stats" data-user-id="<?php echo esc_attr( $user->ID ); ?>"><?php esc_html_e( 'Stats', 'cta-lms' ); ?></button>
							<a class="button button-small" href="<?php echo esc_url( admin_url( 'user-edit.php?user_id=' . $user->ID ) ); ?>"><?php esc_html_e( 'Manage User', 'cta-lms' ); ?></a>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>

	<div id="cta-user-stats-modal" class="cta-admin-modal" hidden>
		<div class="cta-admin-modal__content">
			<button type="button" class="cta-admin-modal__close" aria-label="<?php esc_attr_e( 'Close', 'cta-lms' ); ?>">&times;</button>
			<h2><?php esc_html_e( 'User Stats', 'cta-lms' ); ?></h2>
			<div id="cta-user-stats-body"></div>
		</div>
	</div>
</div>
