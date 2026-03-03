<?php

if (!defined('ABSPATH')) {
    exit;
}

class HLS_Processor
{

    public function __construct()
    {
        add_action('hls_process_video_conversion', array($this, 'process_video'), 10, 2);
    }

    /**
     * Update the conversion job status in the database
     *
     * @param string $job_id
     * @param string $status
     * @param string $error
     */
    private function update_job_status($job_id, $status, $error = '')
    {
        $jobs = get_option('hls_conversion_jobs', array());
        if (isset($jobs[$job_id])) {
            if ($status === 'completed') {
                unset($jobs[$job_id]); // Remove from list on success
            } else {
                $jobs[$job_id]['status'] = $status;
                $jobs[$job_id]['error'] = $error;
            }
            update_option('hls_conversion_jobs', $jobs);
        }
    }

    /**
     * Verify FFmpeg installation
     * 
     * @return bool
     */
    private function check_ffmpeg()
    {
        $output = array();
        $return_var = -1;
        // On Windows typically just ffmpeg if in PATH. 
        // We'll execute ffmpeg -version and check the return var.
        exec('C:\\ffmpeg\\bin\\ffmpeg.exe -version 2>&1', $output, $return_var);
        return ($return_var === 0);
    }

    /**
     * Process the video conversion in the background
     *
     * @param string $tmp_file_path Absolute path to the uploaded MP4 in temp folder.
     * @param string $sanitized_filename The base name of the video.
     */
    public function process_video($tmp_file_path, $sanitized_filename)
    {

        $job_id = basename($tmp_file_path);

        // Attempt to give the script more execution time
        if (function_exists('set_time_limit')) {
            set_time_limit(0);
        }

        // Ensure the source file still exists
        if (!file_exists($tmp_file_path)) {
            error_log('HLS Converter: Temp file ' . $tmp_file_path . ' does not exist.');
            $this->update_job_status($job_id, 'failed', 'Temporary file missing before processing.');
            return;
        }

        // Check if FFmpeg is installed
        if (!$this->check_ffmpeg()) {
            error_log('HLS Converter Error: FFmpeg is not installed or not accessible via exec().');
            $this->update_job_status($job_id, 'failed', 'FFmpeg is not installed or not accessible via exec().');
            return;
        }

        $upload_dir = wp_upload_dir();

        // Create the specific folder for this video stream
        $hls_dir = trailingslashit($upload_dir['basedir']) . HLS_UPLOAD_DIR_NAME . '/' . $sanitized_filename;

        // If directory already exists, let's append a timestamp to the dir to avoid overwriting unless intended
        if (file_exists($hls_dir)) {
            $hls_dir .= '-' . time();
        }

        if (!wp_mkdir_p($hls_dir)) {
            error_log('HLS Converter Error: Could not create output directory ' . $hls_dir);
            $this->update_job_status($job_id, 'failed', 'Could not create output directory.');
            return;
        }

        // Define the output master playlist file path
        $output_m3u8 = trailingslashit($hls_dir) . 'stream.m3u8';

        // Build the FFmpeg command
        // Simple Adaptive Bitrate approach: Convert to standard 720p or similar.
        // A full ABR script entails rendering multiple resolutions and a master playlist. 
        // For simplicity and to ensure it runs without timing out easily, we generate a single profile HLS baseline, which is standard.
        // If ABR is strictly required, we can expand it here, but a single stream (e.g., 720p 2M bitrate) is generally what most simple "HLS conversions" mean in basic plugins.

        // The command below takes the input MP4, encodes video with H.264, audio with AAC, segments it into 10s chunks.
        // Define a progress log file specifically for this job
        $progress_file = trailingslashit($upload_dir['basedir']) . HLS_TMP_DIR_NAME . '/' . $job_id . '_progress.txt';

        // Add to ffmpeg command to output progress file
        $ffmpeg_cmd = sprintf(
            'C:\\ffmpeg\\bin\\ffmpeg.exe -y -i %s -profile:v baseline -level 3.0 -s 1280x720 -start_number 0 -hls_time 10 -hls_list_size 0 -progress %s -f hls %s 2>&1',
            escapeshellarg($tmp_file_path),
            escapeshellarg($progress_file),
            escapeshellarg($output_m3u8)
        );

        // Before starting, attempt to figure out video's total duration for progress parsing
        $duration_cmd = sprintf('C:\\ffmpeg\\bin\\ffmpeg.exe -i %s 2>&1', escapeshellarg($tmp_file_path));
        $duration_output = array();
        exec($duration_cmd, $duration_output);
        $total_duration_us = 0; // Total duration in microseconds as outputted by FFmpeg
        foreach ($duration_output as $line) {
            if (preg_match('/Duration: (\d{2}):(\d{2}):(\d{2})\.(\d{2})/', $line, $matches)) {
                $hours = intval($matches[1]);
                $mins = intval($matches[2]);
                $secs = intval($matches[3]);
                $frac = intval($matches[4]); // Hundredths of a second

                $total_seconds = ($hours * 3600) + ($mins * 60) + $secs;
                $total_duration_us = ($total_seconds * 1000000) + ($frac * 10000);
                break;
            }
        }

        // Save total duration and progress file path temporarily in job details
        $jobs = get_option('hls_conversion_jobs', array());
        if (isset($jobs[$job_id])) {
            $jobs[$job_id]['total_duration_us'] = $total_duration_us;
            $jobs[$job_id]['progress_file'] = $progress_file;
            update_option('hls_conversion_jobs', $jobs);
        }

        // Execute the command
        $output = array();
        $return_var = -1;
        exec($ffmpeg_cmd, $output, $return_var);

        if ($return_var !== 0) {
            error_log('HLS Converter FFmpeg Error: ' . print_r($output, true));
            $error_message = 'FFmpeg error code ' . $return_var . '. ' . substr(implode(' ', $output), -200); // Save last 200 chars of error
            $this->update_job_status($job_id, 'failed', $error_message);
        } else {
            error_log('HLS Converter: Successfully processed video ' . $sanitized_filename);
            $this->update_job_status($job_id, 'completed');
        }

        // Clean up the temporary MP4 file and progress log
        if (file_exists($tmp_file_path)) {
            unlink($tmp_file_path);
        }
        if (isset($progress_file) && file_exists($progress_file)) {
            unlink($progress_file);
        }
    }
}
