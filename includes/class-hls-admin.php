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
            if (!empty($jobs)) {
                ?>
                <h2>Active Status & History</h2>
                <table class="widefat fixed striped" style="margin-bottom: 20px; border-left: 4px solid #2271b1;">
                    <thead>
                        <tr>
                            <th>Original File</th>
                            <th>Status</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($jobs as $job_id => $job): ?>
                            <tr>
                                <td><strong><?php echo esc_html($job['filename']); ?></strong></td>
                                <td>
                                    <?php if ($job['status'] === 'processing'): ?>
                                        <span style="color: #2271b1; font-weight: bold;"><span class="dashicons dashicons-update"
                                                style="animation: rotation 2s infinite linear;"></span> Processing (WP-Cron)</span>
                                    <?php else: ?>
                                        <span style="color: #d63638; font-weight: bold;"><span class="dashicons dashicons-warning"></span>
                                            Failed</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($job['status'] === 'failed'): ?>
                                        <p style="color: #d63638;"><?php echo esc_html($job['error']); ?></p>
                                        <a href="<?php echo esc_url(admin_url('admin.php?page=hls-converter&dismiss_job=' . urlencode($job_id))); ?>"
                                            class="button button-small">Dismiss Error & Clear Temp File</a>
                                    <?php else: ?>
                                        <p><em>Check back soon. Task might take several minutes depending on file size and server
                                                configuration.</em></p>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
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
                <?php
            }
            ?>

            <h2>Previously Converted Videos</h2>
            <p>Once converted, use the shortcode inside your posts/pages, or extract the URL to use in sliders.</p>
            <table class="widefat fixed striped">
                <thead>
                    <tr>
                        <th>Video Name</th>
                        <th>Shortcode</th>
                        <th>HLS Stream URL (.m3u8)</th>
                    </tr>
                </thead>
                <tbody>
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
        <?php
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
