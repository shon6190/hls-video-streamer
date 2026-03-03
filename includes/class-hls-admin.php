<?php

if (!defined('ABSPATH')) {
    exit;
}

class HLS_Admin
{

    public function __construct()
    {
        add_action('admin_menu', array($this, 'add_menu_page'));
        add_action('admin_init', array($this, 'process_form_submission'));
        add_action('wp_ajax_hls_get_status', array($this, 'ajax_get_status'));
        add_action('wp_ajax_hls_delete_video', array($this, 'ajax_delete_video'));
    }

    public function add_menu_page()
    {
        add_menu_page(
            'HLS Converter',
            'HLS Converter',
            'manage_options',
            'hls-converter',
            array($this, 'render_admin_page'),
            'dashicons-video-alt3',
            20
        );
    }

    public function render_admin_page()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        ?>
        <div class="wrap">
            <h1>HLS Video Converter</h1>
            <p>Upload an MP4 video. It will be converted into an Adaptive Bitrate HLS stream automatically in the background.
            </p>

            <?php
            // Handle job dismissal
            if (isset($_GET['dismiss_job']) && current_user_can('manage_options')) {
                $d_job = sanitize_text_field($_GET['dismiss_job']);
                $jobs = get_option('hls_conversion_jobs', array());
                if (isset($jobs[$d_job])) {
                    unset($jobs[$d_job]);
                    update_option('hls_conversion_jobs', $jobs);

                    // Attempt to remove stuck tmp file
                    $upload_dir = wp_upload_dir();
                    $tmp_file = trailingslashit($upload_dir['basedir']) . HLS_TMP_DIR_NAME . '/' . $d_job;
                    if (file_exists($tmp_file)) {
                        unlink($tmp_file);
                    }
                    echo '<div class="notice notice-success is-dismissible"><p>Job dismissed and temporary file cleared.</p></div>';
                }
            }

            if (isset($_GET['upload']) && $_GET['upload'] == 'success') {
                echo '<div class="notice notice-success is-dismissible"><p>Video uploaded successfully! The conversion process has been scheduled in the background.</p></div>';
            }
            if (isset($_GET['upload']) && $_GET['upload'] == 'failed') {
                echo '<div class="notice notice-error is-dismissible"><p>There was an error uploading the video. Please try again.</p></div>';
            }
            if (isset($_GET['upload']) && $_GET['upload'] == 'invalid_type') {
                echo '<div class="notice notice-error is-dismissible"><p>Invalid file type. Only MP4 videos are allowed.</p></div>';
            }
            ?>

            <form method="post" enctype="multipart/form-data" action="">
                <?php wp_nonce_field('hls_upload_video_nonce', 'hls_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="hls_video_file">Select MP4 Video</label></th>
                        <td>
                            <input type="file" name="hls_video_file" id="hls_video_file" accept="video/mp4" required />
                            <p class="description">Maximum file size is determined by your server settings.</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button('Upload & Convert'); ?>
            </form>

            <hr>

            <?php
            $jobs = get_option('hls_conversion_jobs', array());
            ?>
            <h2 id="hls-active-heading">Active Status & History</h2>
            <table class="widefat fixed striped" id="hls-active-table"
                style="margin-bottom: 20px; border-left: 4px solid #2271b1;">
                <thead>
                    <tr>
                        <th>Original File</th>
                        <th>Status</th>
                        <th>Details</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="hls-active-jobs">
                    <?php if (!empty($jobs)): ?>
                        <?php foreach ($jobs as $job_id => $job): ?>
                            <tr id="job-row-<?php echo esc_attr($job_id); ?>">
                                <td><strong><?php echo esc_html($job['filename']); ?></strong></td>
                                <td class="job-status">
                                    <?php if ($job['status'] === 'processing'): ?>
                                        <span style="color: #2271b1; font-weight: bold;"><span class="dashicons dashicons-update"
                                                style="animation: rotation 2s infinite linear;"></span> Processing (WP-Cron)</span>
                                    <?php else: ?>
                                        <span style="color: #d63638; font-weight: bold;"><span class="dashicons dashicons-warning"></span>
                                            Failed</span>
                                    <?php endif; ?>
                                </td>
                                <td class="job-details">
                                    <?php if ($job['status'] === 'failed'): ?>
                                        <p style="color: #d63638;"><?php echo esc_html($job['error']); ?></p>
                                    <?php else: ?>
                                        <p><em>Check back soon. Task might take several minutes depending on file size and server
                                                configuration.</em></p>
                                    <?php endif; ?>
                                </td>
                                <td class="job-action">
                                    <?php if ($job['status'] === 'failed'): ?>
                                        <a href="<?php echo esc_url(admin_url('admin.php?page=hls-converter&dismiss_job=' . urlencode($job_id))); ?>"
                                            class="button button-small">Dismiss Error & Clear Temp File</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4">No active or failed jobs.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            <style>
                @keyframes rotation {
                    from {
                        transform: rotate(0deg);
                    }

                    to {
                        transform: rotate(359deg);
                    }
                }
            </style>
            <hr>

            <h2>Previously Converted Videos</h2>
            <p>Once converted, use the shortcode inside your posts/pages, or extract the URL to use in sliders.</p>
            <table class="widefat fixed striped">
                <thead>
                    <tr>
                        <th>Video Name</th>
                        <th>Shortcode</th>
                        <th>HLS Stream URL (.m3u8)</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="hls-completed-jobs">
                    <?php
                    $upload_dir = wp_upload_dir();
                    $hls_dir = trailingslashit($upload_dir['basedir']) . HLS_UPLOAD_DIR_NAME;

                    if (is_dir($hls_dir)) {
                        $files = scandir($hls_dir);
                        foreach ($files as $folder) {
                            if ($folder === '.' || $folder === '..')
                                continue;

                            $stream_path = trailingslashit($hls_dir) . $folder . '/stream.m3u8';
                            if (file_exists($stream_path)) {
                                $stream_url = trailingslashit($upload_dir['baseurl']) . HLS_UPLOAD_DIR_NAME . '/' . $folder . '/stream.m3u8';
                                ?>
                                <tr>
                                    <td><strong>
                                            <?php echo esc_html($folder); ?>
                                        </strong></td>
                                    <td><code>[hls_player url="<?php echo esc_url($stream_url); ?>"]</code></td>
                                    <td><input type="text" readonly class="large-text" value="<?php echo esc_url($stream_url); ?>"
                                            onclick="this.select();"></td>
                                    <td><button type="button" class="button hls-delete-video" data-folder="<?php echo esc_attr($folder); ?>">Delete</button></td>
                                </tr>
                                <?php
                            }
                        }
                    } else {
                        echo '<tr><td colspan="3">No videos found.</td></tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>
        </div>

        <script>
            jQuery(document).ready(function ($) {
                function hlsPolling() {
                    $.post(ajaxurl, {
                        action: 'hls_get_status',
                        hls_nonce: '<?php echo wp_create_nonce('hls_status_nonce'); ?>'
                    }, function (response) {
                        if (response.success) {

                            // Update active jobs table
                            var activeJobsHtml = '';
                            if (response.data.active_jobs && Object.keys(response.data.active_jobs).length > 0) {
                                $.each(response.data.active_jobs, function (job_id, job) {
                                    activeJobsHtml += '<tr id="job-row-' + job_id + '">';
                                    activeJobsHtml += '<td><strong>' + job.filename + '</strong></td>';
                                    activeJobsHtml += '<td class="job-status">';
                                    if (job.status === 'processing') {
                                        var progressText = 'Processing (WP-Cron)';
                                        if (job.progress !== undefined) {
                                            var pct = Math.min(100, Math.max(0, job.progress));
                                            progressText = '<div style="margin-top:5px;width:100%;background-color:#f0f0f1;height:10px;border-radius:5px;"><div style="background-color:#2271b1;height:10px;border-radius:5px;width:' + pct + '%;"></div></div><small>' + pct + '% Complete</small>';
                                        }
                                        activeJobsHtml += '<span style="color: #2271b1; font-weight: bold;"><span class="dashicons dashicons-update" style="animation: rotation 2s infinite linear;"></span> Processing (WP-Cron)</span>' + progressText;
                                    } else {
                                        activeJobsHtml += '<span style="color: #d63638; font-weight: bold;"><span class="dashicons dashicons-warning"></span> Failed</span>';
                                    }
                                    activeJobsHtml += '</td>';
                                    activeJobsHtml += '<td class="job-details">';
                                    if (job.status === 'failed') {
                                        activeJobsHtml += '<p style="color: #d63638;">' + job.error + '</p>';
                                    } else {
                                        activeJobsHtml += '<p><em>Check back soon. Task might take several minutes depending on file size and server configuration.</em></p>';
                                    }
                                    activeJobsHtml += '</td>';
                                    activeJobsHtml += '<td class="job-action">';
                                    if (job.status === 'failed') {
                                        activeJobsHtml += '<a href="?page=hls-converter&dismiss_job=' + encodeURIComponent(job_id) + '" class="button button-small">Dismiss Error & Clear Temp File</a>';
                                    }
                                    activeJobsHtml += '</td>';
                                    activeJobsHtml += '</tr>';
                                });
                            } else {
                                activeJobsHtml = '<tr><td colspan="4">No active or failed jobs.</td></tr>';
                            }
                            $('#hls-active-jobs').html(activeJobsHtml);

                            // Update completed jobs table
                            var completedJobsHtml = '';
                            if (response.data.completed_jobs && response.data.completed_jobs.length > 0) {
                                $.each(response.data.completed_jobs, function (i, job) {
                                    completedJobsHtml += '<tr>';
                                    completedJobsHtml += '<td><strong>' + job.folder + '</strong></td>';
                                    completedJobsHtml += '<td><code>[hls_player url="' + job.stream_url + '"]</code></td>';
                                    completedJobsHtml += '<td><input type="text" readonly class="large-text" value="' + job.stream_url + '" onclick="this.select();"></td>';
                                    completedJobsHtml += '<td><button type="button" class="button hls-delete-video" data-folder="' + job.folder + '">Delete</button></td>';
                                    completedJobsHtml += '</tr>';
                                });
                            } else {
                                completedJobsHtml = '<tr><td colspan="4">No videos found.</td></tr>';
                            }
                            $('#hls-completed-jobs').html(completedJobsHtml);

                        }
                    });
                }

                // Poll every 5 seconds
                setInterval(hlsPolling, 5000);

                // Handle Delete button click
                $(document).on('click', '.hls-delete-video', function() {
                    var button = $(this);
                    var folder = button.data('folder');

                    if (confirm('Are you sure you want to delete the video "' + folder + '"? This will remove all generated files.')) {
                        button.prop('disabled', true).text('Deleting...');

                        $.post(ajaxurl, {
                            action: 'hls_delete_video',
                            folder: folder,
                            hls_nonce: '<?php echo wp_create_nonce('hls_status_nonce'); ?>'
                        }, function (response) {
                            if (response.success) {
                                button.closest('tr').fadeOut(300, function() {
                                    $(this).remove();
                                });
                            } else {
                                alert('Error deleting video: ' + (response.data || 'Unknown error'));
                                button.prop('disabled', false).text('Delete');
                            }
                        });
                    }
                });
            });
        </script>
        <?php
    }

    public function ajax_get_status()
    {
        check_ajax_referer('hls_status_nonce', 'hls_nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized request.');
        }

        $jobs = get_option('hls_conversion_jobs', array());

        // Parse progress if possible
        foreach ($jobs as $job_id => &$job) {
            if ($job['status'] === 'processing' && !empty($job['progress_file']) && !empty($job['total_duration_us'])) {
                if (file_exists($job['progress_file'])) {
                    $progress_content = file_get_contents($job['progress_file']);
                    if (preg_match_all('/out_time_us=([\d]+)/', $progress_content, $matches)) {
                        $last_out_time_us = end($matches[1]);
                        if (!empty($job['total_duration_us'])) {
                            $pct = round(($last_out_time_us / $job['total_duration_us']) * 100);
                            $job['progress'] = $pct;
                        }
                    }
                }
            }
        }
        unset($job);

        $upload_dir = wp_upload_dir();
        $hls_dir = trailingslashit($upload_dir['basedir']) . HLS_UPLOAD_DIR_NAME;

        $completed_jobs = array();

        if (is_dir($hls_dir)) {
            $files = scandir($hls_dir);
            foreach ($files as $folder) {
                if ($folder === '.' || $folder === '..')
                    continue;

                $stream_path = trailingslashit($hls_dir) . $folder . '/stream.m3u8';
                if (file_exists($stream_path)) {
                    $stream_url = trailingslashit($upload_dir['baseurl']) . HLS_UPLOAD_DIR_NAME . '/' . $folder . '/stream.m3u8';
                    $completed_jobs[] = array(
                        'folder' => esc_html($folder),
                        'stream_url' => esc_url($stream_url)
                    );
                }
            }
        }

        wp_send_json_success(array(
            'active_jobs' => $jobs,
            'completed_jobs' => $completed_jobs
        ));
    }

    public function ajax_delete_video()
    {
        check_ajax_referer('hls_status_nonce', 'hls_nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized request.');
        }

        $folder = isset($_POST['folder']) ? sanitize_text_field($_POST['folder']) : '';

        if (empty($folder) || strpos($folder, '..') !== false || strpos($folder, '/') !== false || strpos($folder, '\\') !== false) {
             wp_send_json_error('Invalid folder name.');
        }

        $upload_dir = wp_upload_dir();
        $hls_dir = trailingslashit($upload_dir['basedir']) . HLS_UPLOAD_DIR_NAME . '/' . $folder;

        if (is_dir($hls_dir)) {
            // Delete all files in the directory
            $files = array_diff(scandir($hls_dir), array('.','..'));
            foreach ($files as $file) {
                unlink(trailingslashit($hls_dir) . $file);
            }
            // Delete the directory itself
            rmdir($hls_dir);
            wp_send_json_success('Deleted.');
        } else {
            wp_send_json_error('Directory not found.');
        }
    }

    public function process_form_submission()
    {
        if (isset($_POST['hls_nonce']) && wp_verify_nonce($_POST['hls_nonce'], 'hls_upload_video_nonce')) {

            if (!current_user_can('manage_options')) {
                wp_die('Unauthorized request.');
            }

            if (!empty($_FILES['hls_video_file']['name'])) {
                $file = $_FILES['hls_video_file'];

                // Restrict to MP4 only
                $file_type = wp_check_filetype(basename($file['name']), array('mp4' => 'video/mp4'));
                if ($file_type['type'] !== 'video/mp4') {
                    wp_redirect(admin_url('admin.php?page=hls-converter&upload=invalid_type'));
                    exit;
                }

                // Sanitize file name
                $original_filename = pathinfo($file['name'], PATHINFO_FILENAME);
                $sanitized_filename = sanitize_title($original_filename); // e.g. "My Video" -> "my-video"

                if (empty($sanitized_filename)) {
                    $sanitized_filename = 'video-' . time();
                }

                $upload_dir = wp_upload_dir();
                $tmp_dir = trailingslashit($upload_dir['basedir']) . HLS_TMP_DIR_NAME;

                // Ensure tmp dir exists
                if (!file_exists($tmp_dir)) {
                    wp_mkdir_p($tmp_dir);
                }

                $tmp_file_path = trailingslashit($tmp_dir) . $sanitized_filename . '-' . time() . '.mp4';
                $job_id = basename($tmp_file_path);

                if (move_uploaded_file($file['tmp_name'], $tmp_file_path)) {
                    // Register the job for UI tracking
                    $jobs = get_option('hls_conversion_jobs', array());
                    $jobs[$job_id] = array(
                        'time' => time(),
                        'filename' => $original_filename . '.mp4',
                        'status' => 'processing',
                        'error' => ''
                    );
                    update_option('hls_conversion_jobs', $jobs);

                    // Schedule background Event
                    wp_schedule_single_event(time(), 'hls_process_video_conversion', array($tmp_file_path, $sanitized_filename));

                    wp_redirect(admin_url('admin.php?page=hls-converter&upload=success'));
                    exit;
                } else {
                    wp_redirect(admin_url('admin.php?page=hls-converter&upload=failed'));
                    exit;
                }
            }
        }
    }
}
