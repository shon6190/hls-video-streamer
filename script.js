

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

      } else {
        // Normal video file
        html = `<video controls autoplay width="100%">
                      <source src="${videoUrl}" type="video/mp4">
                      Your browser does not support the video tag.
                  </video>`;
      }

      $('#galleryVideoModal .gallery-modal_body').html(html);
      $('#galleryVideoModal').modal('show');
    }
  });