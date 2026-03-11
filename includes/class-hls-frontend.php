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
        // Enqueue ReactPlayer standalone which includes React and player logic
        wp_enqueue_script('react-player-standalone', 'https://cdn.jsdelivr.net/npm/react-player/dist/ReactPlayer.standalone.js', array(), null, true);

        // Output initialization script for ReactPlayer
        $inline_script = "
            document.addEventListener('DOMContentLoaded', function() {
                if (typeof renderReactPlayer === 'undefined') return;

                // Function to mount ReactPlayer on a given container
                function mountPlayer(container, url, width, height, controls) {
                    renderReactPlayer(container, {
                        url: url,
                        width: width || '100%',
                        height: height || 'auto',
                        controls: controls !== false && controls !== 'false',
                        playing: false
                    });
                }

                // 1. Initialize explicitly declared ReactPlayer containers (from shortcode)
                var containers = document.querySelectorAll('.react-player-container');
                containers.forEach(function(container) {
                    if (container.classList.contains('react-player-initialized')) return;
                    
                    var url = container.getAttribute('data-url');
                    var width = container.getAttribute('data-width');
                    var height = container.getAttribute('data-height');
                    var controls = container.getAttribute('data-controls');
                    
                    if (url) {
                        container.classList.add('react-player-initialized');
                        mountPlayer(container, url, width, height, controls);
                    }
                });

                // 2. Seamless integration: Find existing <video> tags with .m3u8 sources and replace them
                // This maintains compatibility with exiting slider components or raw video tags
                var videos = document.querySelectorAll('video');
                videos.forEach(function(video) {
                    if (video.classList.contains('react-player-initialized')) return;
                    
                    var source = video.getAttribute('src');
                    if (!source) {
                        var sourceTag = video.querySelector('source');
                        if (sourceTag) {
                            source = sourceTag.getAttribute('src');
                        }
                    }

                    if (source && source.indexOf('.m3u8') !== -1) {
                        video.classList.add('react-player-initialized');
                        
                        // Create a wrapper to replace the video tag
                        var wrapper = document.createElement('div');
                        wrapper.className = 'react-player-wrapper react-player-initialized';
                        wrapper.style.width = video.style.width || '100%';
                        wrapper.style.height = video.style.height || '100%';
                        
                        // Inherit dimensions/controls if available
                        var width = video.getAttribute('width') ? (video.getAttribute('width') + 'px') : '100%';
                        var height = video.getAttribute('height') ? (video.getAttribute('height') + 'px') : '100%';
                        var controls = video.hasAttribute('controls');
                        
                        // Replace video element with wrapper in the DOM
                        video.parentNode.replaceChild(wrapper, video);
                        
                        // Mount ReactPlayer on the new wrapper
                        mountPlayer(wrapper, source, width, height, controls);
                    }
                });
            });
        ";

        wp_add_inline_script('react-player-standalone', $inline_script);
    }

    public function render_shortcode($atts)
    {
        $atts = shortcode_atts(array(
            'url' => '',
            'width' => '100%',
            'height' => 'auto',
            'controls' => 'true'
        ), $atts, 'hls_player');

        if (empty($atts['url'])) {
            return '<p>Please provide a valid HLS stream URL.</p>';
        }

        $container_id = 'hls-video-' . uniqid();
        $controls_val = $atts['controls'] === 'false' ? 'false' : 'true';

        // Output a wrapper div. The inline JS will pick this up and call renderReactPlayer()
        ob_start();
        ?>
        <div class="hls-video-container" style="width: <?php echo esc_attr($atts['width']); ?>; height: <?php echo esc_attr($atts['height']); ?>;">
            <div id="<?php echo esc_attr($container_id); ?>" 
                 class="react-player-container"
                 data-url="<?php echo esc_url($atts['url']); ?>"
                 data-width="100%"
                 data-height="100%"
                 data-controls="<?php echo esc_attr($controls_val); ?>"
                 style="width: 100%; height: 100%;">
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
