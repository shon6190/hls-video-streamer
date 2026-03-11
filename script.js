$(document).on("click", "button.gallery-video-btn", function (e) {
    e.preventDefault();
    var videoUrl = $(this).attr('data-video_url');
    var html = '';
    if (videoUrl != '') {
        // Check if YouTube URL
        if (videoUrl.includes("youtube.com") || videoUrl.includes("youtu.be")) {

            // Convert youtube watch URL to embed URL
            var embedUrl = videoUrl.replace("watch?v=", "embed/");
            embedUrl = embedUrl.replace("youtu.be/", "youtube.com/embed/");

            html = `<iframe width="100%" height="400"
                      src="${embedUrl}?autoplay=1"
                      frameborder="0"
                      allow="autoplay; encrypted-media"
                      allowfullscreen>
                  </iframe>`;

        } else if (videoUrl.includes(".m3u8")) {
            // HLS video file
            html = '<div id="gallery-react-player-container" style="width: 100%; height: 400px; background: #000;"></div>';

        } else {
            // Normal video file
            html = `<video controls autoplay width="100%">
                      <source src="${videoUrl}" type="video/mp4">
                      Your browser does not support the video tag.
                  </video>`;
        }

        $('#galleryVideoModal .gallery-modal_body').html(html);
        $('#galleryVideoModal').modal('show');

        // Initialize ReactPlayer if it's an HLS stream
        if (videoUrl.includes(".m3u8") && typeof renderReactPlayer !== 'undefined') {
            var container = document.getElementById('gallery-react-player-container');
            if (container) {
                renderReactPlayer(container, {
                    url: videoUrl,
                    width: '100%',
                    height: '100%',
                    controls: true,
                    playing: true,
                    playsinline: true,
                    config: {
                        file: {
                            attributes: {
                                playsInline: true,
                                'webkit-playsinline': true
                            }
                        }
                    }
                });
            }
        }
    }
});

// Cleanup player when modal is closed so audio doesn't keep playing in the background
$('#galleryVideoModal').on('hidden.bs.modal', function () {
    var container = document.getElementById('gallery-react-player-container');
    if (container && typeof renderReactPlayer !== 'undefined') {
        // Re-rendering with an empty URL cleanly unmounts the player and stops playback
        renderReactPlayer(container, {
            url: ''
        });
    }
    $('#galleryVideoModal .gallery-modal_body').html('');
});