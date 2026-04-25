<?php
require_once('../../config.php');

$id = required_param('id', PARAM_INT);
$cm = get_coursemodule_from_id('ytvideo', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$ytvideo = $DB->get_record('ytvideo', array('id' => $cm->instance), '*', MUST_EXIST);

require_login($course, true, $cm);

$PAGE->set_url('/mod/ytvideo/view.php', array('id' => $cm->id));
$PAGE->set_title($ytvideo->name);
$PAGE->set_heading($course->fullname);

echo $OUTPUT->header();

// Fetch existing progress safely
$progress_record = $DB->get_record('ytvideo_progress', array('ytvideoid' => $ytvideo->id, 'userid' => $USER->id));

$saved_yt_time = $progress_record ? (int)$progress_record->yt_progress : 0;
$saved_local_time = $progress_record ? (int)$progress_record->local_progress : 0;
$sesskey = sesskey();

// ---------------------------------------------------------
// 1. YOUTUBE SECTION
// ---------------------------------------------------------
$has_yt = false;
if (!empty(trim($ytvideo->youtubeurl))) {
    $url = $ytvideo->youtubeurl;
    if (preg_match('%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/\s]{11})%i', $url, $match)) {
        $video_id = $match[1];
        $has_yt = true;
        echo html_writer::tag('h3', 'YouTube Video');
        echo html_writer::tag('div', '', array('id' => 'ytplayer', 'style' => 'margin: 20px 0;'));
    }
}

// ---------------------------------------------------------
// 2. UPLOADED CONTENT SECTION
// ---------------------------------------------------------
echo html_writer::tag('hr', '');
if (!empty($ytvideo->upload)) {
    echo html_writer::tag('h3', 'Uploaded Content');
    $context = context_module::instance($cm->id);
    $text = file_rewrite_pluginfile_urls($ytvideo->upload, 'pluginfile.php', $context->id, 'mod_ytvideo', 'upload', 0);
    
    echo html_writer::start_tag('div', array('id' => 'local-video-container'));
    echo format_text($text, $ytvideo->uploadformat, array('context' => $context));
    echo html_writer::end_tag('div');
}

// ---------------------------------------------------------
// 3. COMBINED JAVASCRIPT TRACKING
// ---------------------------------------------------------
?>
<script>
    var ytPlayer;
    var ytInterval, localInterval;
    var vId = <?php echo $ytvideo->id; ?>;
    var sesskey = '<?php echo $sesskey; ?>';

    // --- SHARED SAVE FUNCTION ---
    function sendProgress(time, type) {
        var formData = new FormData();
        formData.append('ytvideoid', vId);
        formData.append('progress', Math.floor(time));
        formData.append('type', type); // 'yt' or 'local'
        formData.append('sesskey', sesskey);

        fetch('save_progress.php', {
            method: 'POST',
            body: formData,
            keepalive: true
        });
    }

    // --- YOUTUBE LOGIC ---
    <?php if ($has_yt): ?>
    window.onYouTubeIframeAPIReady = function() {
        ytPlayer = new YT.Player('ytplayer', {
            height: '450', width: '800', videoId: '<?php echo $video_id; ?>',
            playerVars: { 'start': <?php echo $saved_yt_time; ?>, 'playsinline': 1 },
            events: { 'onStateChange': onYTStateChange }
        });
    }

    function onYTStateChange(event) {
        if (event.data == YT.PlayerState.PLAYING) {
            ytInterval = setInterval(function() {
                sendProgress(ytPlayer.getCurrentTime(), 'yt');
            }, 5000);
        } else {
            clearInterval(ytInterval);
            sendProgress(ytPlayer.getCurrentTime(), 'yt');
        }
    }

    if (typeof YT === 'undefined') {
        var tag = document.createElement('script');
        tag.src = "https://www.youtube.com/iframe_api";
        document.head.appendChild(tag);
    } else {
        onYouTubeIframeAPIReady();
    }
    <?php endif; ?>

    // --- LOCAL VIDEO LOGIC ---
    document.addEventListener('DOMContentLoaded', function() {
        var container = document.getElementById('local-video-container');
        if (!container) return;

        var videos = container.getElementsByTagName('video');
        for (var i = 0; i < videos.length; i++) {
            var v = videos[i];
            
            // Resume local video
            v.currentTime = <?php echo $saved_local_time; ?>;

            v.addEventListener('play', function() {
                var currentV = this;
                localInterval = setInterval(function() {
                    sendProgress(currentV.currentTime, 'local');
                }, 5000);
            });

            v.addEventListener('pause', function() {
                clearInterval(localInterval);
                sendProgress(this.currentTime, 'local');
            });
        }
    });

    // --- GLOBAL EXIT SAVE ---
    document.addEventListener('visibilitychange', function() {
        if (document.visibilityState === 'hidden') {
            // Save YouTube if exists
            if (ytPlayer && typeof ytPlayer.getCurrentTime === 'function') {
                sendProgress(ytPlayer.getCurrentTime(), 'yt');
            }
            // Save Local if exists
            var container = document.getElementById('local-video-container');
            if (container) {
                var localVideos = container.getElementsByTagName('video');
                for (var j = 0; j < localVideos.length; j++) {
                    sendProgress(localVideos[j].currentTime, 'local');
                }
            }
        }
    });
</script>

<?php
echo $OUTPUT->footer();