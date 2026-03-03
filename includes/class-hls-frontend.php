<?php

if (!defined('ABSPATH')) {
    exit;
}

class HLS_Frontend
{

    public function __construct()
    {
        // Register shortcode
        add_shortcode('hls_player', array($this, 'render_shortcode'));

        // Enqueue scripts
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    public function enqueue_scripts()
    {
        // We enqueue HLS.js on the frontend
        wp_enqueue_script('hls-js', 'https://cdn.jsdelivr.net/npm/hls.js@latest', array(), null, true);

        // We also output a global initialization script that automatically finds ANY <video> tag 
        // with an .m3u8 source (e.g., inside an existing slider) and initializes HLS.js on it.
        // This makes integration with existing slider components completely seamless.
        $inline_script = "
            document.addEventListener('DOMContentLoaded', function() {
                if (typeof Hls === 'undefined') return;

                var videos = document.querySelectorAll('video');
                videos.forEach(function(video) {
                    var source = video.getAttribute('src');
                    if (!source) {
                        var sourceTag = video.querySelector('source');
                        if (sourceTag) {
                            source = sourceTag.getAttribute('src');
                        }
                    }

                    if (source && source.indexOf('.m3u8') !== -1 && !video.classList.contains('hls-initialized')) {
                        video.classList.add('hls-initialized');
                        if (Hls.isSupported()) {
                            var hls = new Hls();
                            hls.loadSource(source);
                            hls.attachMedia(video);
                        } else if (video.canPlayType('application/vnd.apple.mpegurl')) {
                            video.src = source;
                        }
                    }
                });
            });
        ";

        wp_add_inline_script('hls-js', $inline_script);
    }

    public function render_shortcode($atts)
    {
        $atts = shortcode_atts(array(
            'url' => '',
            'width' => '100%',
            'height' => 'auto',
            'controls' => 'controls'
        ), $atts, 'hls_player');

        if (empty($atts['url'])) {
            return '<p>Please provide a valid HLS stream URL.</p>';
        }

        $video_id = 'hls-video-' . uniqid();
        $controls_attr = $atts['controls'] === 'false' ? '' : 'controls';

        // We just output the normal <video> tag with the .m3u8 source.
        // The global script enqueued above will automatically pick it up and initialize HLS.js!
        ob_start();
        ?>
        <div class="hls-video-container"
            style="width: <?php echo esc_attr($atts['width']); ?>; height: <?php echo esc_attr($atts['height']); ?>;">
            <video id="<?php echo esc_attr($video_id); ?>" class="hls-video-element"
                src="<?php echo esc_url($atts['url']); ?>" style="width: 100%; height: 100%;" <?php echo esc_attr($controls_attr); ?>></video>
        </div>
        <?php
        return ob_get_clean();
    }
}
