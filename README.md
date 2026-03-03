# hls-video-streamer
Upload MP4 videos, convert them to HLS streams using FFmpeg, and display them securely via shortcode or existing video components.

## Recent Updates & Enhancements

* **Robust Background Processing:** Integrated PHP `set_time_limit(0)` natively to prevent server timeout drops when converting significantly larger MP4 videos (e.g., 50MB-100MB+).
* **Live Dynamic Progress Bar:** Replaced the infinite loading spinner with a responsive progress bar in the "Active Status" UI. It dynamically monitors and calculations the completion percentage of WP-Cron background jobs natively from FFmpeg's stream output properties (`out_time_us`).
* **HLS Stream Cleanup:** Implemented a new AJAX-powered "Delete" button alongside previously converted videos. It features a user confirmation prompt and seamlessly cleans up and removes the physical HLS stream folder and all its contents right from the uploads directory to free up space, automatically removing the row without requiring a page reload.
