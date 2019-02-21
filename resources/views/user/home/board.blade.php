<!DOCTYPE html>
<html>
<head>
    <style type="text/css">
        #local-media video {
            max-width: 100%;
        }

        #remote-media video {
            max-width: 100%;
        }
        .camera{
            display:flex;
            right: 20px;
            top: 20px;
        }
        .camera__item + .camera__item{
            margin-left: 25px;
        }
        .aww{
            border: 1px solid #333;
            width: 100%;
            height: 100vh;
        }
        @media screen and (max-width: 981px) {
            .aww{
                height: 70vh;
                width: calc(100% - 100px);
            }
        }
        @media screen and (max-width: 981px) and (orientation: landscape)  {
            .aww{
                height: 670px;
                width: calc(100% - 100px);
            }
        }
        
        

    </style>
    <script src="https://code.jquery.com/jquery-3.3.1.min.js"></script>
    <link rel="stylesheet" href="https://awwapp.com/static/widget/css/toolbar_style.css">
    {{--Twilio--}}
    <script src="//media.twiliocdn.com/sdk/js/video/v1/twilio-video.min.js"></script>
    {{--<script type="text/javascript" src="//media.twiliocdn.com/sdk/js/client/v1.6/twilio.min.js"></script>--}}
    {{--<script src="https://stage.twiliocdn.com/sdk/js/video/releases/2.0.0-rc1/twilio-video.js"></script>--}}


    <script>

        var setCamera = false;
        var setMic = false;
        var activeRoom;
        var previewTracks;


        function attachTracks(tracks, container) {
            tracks.forEach(function (track) {
                container.appendChild(track.attach());
            });
        }

        // Attach the Participant's Tracks to the DOM.
        function attachParticipantTracks(participant, container) {
            var tracks = Array.from(participant.tracks.values());
            attachTracks(tracks, container);
        }

        // Detach the Tracks from the DOM.
        function detachTracks(tracks) {
            tracks.forEach(function (track) {
                track.detach().forEach(function (detachedElement) {
                    detachedElement.remove();
                });
            });
        }

        // Detach the Participant's Tracks from the DOM.
        function detachParticipantTracks(participant) {
            var tracks = Array.from(participant.tracks.values());
            detachTracks(tracks);
        }

        // Successfully connected!
        function roomJoined(room) {
            window.room = activeRoom = room;

            room.localParticipant.audioTracks.forEach(function (track) {
                track.disable();
            });

            room.localParticipant.videoTracks.forEach(function (track) {
                track.disable();
            });

            // console.log("Joined as: " + clientId);
            console.log("+ activeRoom join: " + activeRoom);

            // Attach LocalParticipant's Tracks, if not already attached.
            var previewContainer = document.getElementById('local-media');
            if (!previewContainer.querySelector('video')) {
                attachParticipantTracks(room.localParticipant, previewContainer);
            }
            // Attach the Tracks of the Room's Participants.
            room.participants.forEach(function (participant) {
                console.log("Already in Room: " + participant.identity);
                var previewContainer = document.getElementById('remote-media');
                attachParticipantTracks(participant, previewContainer);
            });
            // When a Participant joins the Room, log the event.
            room.on('participantConnected', function (participant) {
                console.log("Joining: " + participant.identity);
            });
            // When a Participant adds a Track, attach it to the DOM.
            room.on('trackAdded', function (track, participant) {
                console.log(participant.identity + " added track: " + track.kind);
                var previewContainer = document.getElementById('remote-media');
                attachTracks([track], previewContainer);
            });
            // When a Participant removes a Track, detach it from the DOM.
            room.on('trackRemoved', function (track, participant) {
                console.log(participant.identity + " removed track: " + track.kind);
                detachTracks([track]);
            });
            // When a Participant leaves the Room, detach its Tracks.
            room.on('participantDisconnected', function (participant) {
                console.log("Participant '" + participant.identity + "' left the room");
                detachParticipantTracks(participant);
            });
            // Once the LocalParticipant leaves the room, detach the Tracks
            // of all Participants, including that of the LocalParticipant.
            room.on('disconnected', function () {
                console.log('You have left the room: ' + roomName);
                if (previewTracks) {
                    previewTracks.forEach(function (track) {
                        track.stop();
                    });
                }
                detachParticipantTracks(room.localParticipant);
                room.participants.forEach(detachParticipantTracks);
                activeRoom = null;

            });

            room.on('trackDisabled', function (track) {
                if (track.kind == "video") {
                    detachTracks([track]);
                }
            });

            room.on('trackEnabled', function (track) {
                if (track.kind == "video") {
                    detachTracks([track]);
                    var previewContainer = document.getElementById('remote-media');
                    attachTracks([track], previewContainer);
                }
            });
        }

        window.onload = function () {


            var connectOptions = {
                name: '{{ $roomName }}',
                video: true,
                audio: true,
                preferredVideoCodecs: ['H264'],
                logLevel: 'debug'
            };
            if (previewTracks) {
                connectOptions.tracks = previewTracks;
            }

            Twilio.Video.connect('{{ $accessToken }}', connectOptions).then(roomJoined, function (error) {
                console.log('Could not connect to Twilio: ' + error.message);
                alert('error ');
            });


            updateDeviceStatus();

            document.getElementById('hide-camera').onclick = function () {

                room.localParticipant.videoTracks.forEach(function (track) {
                    track.disable();
                });

                setCamera = false;
                updateDeviceStatus();
            };

            document.getElementById('show-camera').onclick = function () {
                room.localParticipant.videoTracks.forEach(function (track) {
                    track.enable();
                });

                setCamera = true;
                updateDeviceStatus();
            };

            document.getElementById('mute').onclick = function () {

                room.localParticipant.audioTracks.forEach(function (track) {
                    track.disable();
                });

                setMic = false;

                updateDeviceStatus();
            };

            document.getElementById('un-mute').onclick = function () {

                room.localParticipant.audioTracks.forEach(function (track) {
                    track.enable();
                });

                setMic = true;

                updateDeviceStatus();

            };

            function updateDeviceStatus() {
                var camera = document.getElementById('camera-status');
                var microphone = document.getElementById('microphone-status');

                if (setCamera == true) {
                    camera.innerText = "Your camera is enabled"
                }
                else {
                    camera.innerText = "Your camera is disabled"
                }

                if (setMic == true) {
                    microphone.innerText = "Your microphone is enabled"
                }
                else {
                    microphone.innerText = "Your microphone is disabled"
                }
            }
        };


    </script>

</head>
<body>
<div>
    <?php $currentUser = \App\User::where('id', Sentinel::getUser()->id)->firstOrFail() ?>

    @if($currentUser->inRole('student'))
        <form method="post" action="/finish-lesson">
            {{ csrf_field() }}
            <input type="hidden" name="lesson_id" value="{{$lesson->id}}">
            <input type="submit" value="End of lesson">
        </form>
    @endif
</div>

<div class="camera">
    <div class="camera__item">
        <p>Your camera</p>
        <div id="local-media" style="width: 320px;"></div>
        <div>
            <button id="mute">Mute</button>
            <button id="un-mute">Un Mute</button>
            <button id="hide-camera">Hide camera</button>
            <button id="show-camera">Show camera</button>
            <p id="microphone-status"></p>
            <p id="camera-status"></p>
        </div>
    </div>
    <div class="camera__item">
        <p>Remote camera</p>
        <div id="remote-media" style="width: 320px;"></div>
    </div>
</div>




<div id="aww-wrapper" class="aww"></div>
<script src="https://awwapp.com/static/widget/js/aww3.min.js"></script>
<script type="text/javascript">
    var aww = new AwwBoard('#aww-wrapper', {
        /* make sure you're using your own key here */
        apiKey: '{{ env('AWW_API_KEY') }}',
        /* put a unique text here */
        boardLink: '{{ $board->link }}',
        multiPage: true,
        sendUserPointer: true,
        showUserPointers: true,
        enableZoom: false
    });

    aww.setUserName('{{$userName}}');

    $.ajax({
        'method': 'GET',
        'url': 'https://awwapp.com/static/widget/sample_toolbar.html'
    }).done(function (res, status) {
        $('#aww-wrapper').append(res);
        initToolbar();
    });
</script>
<script src="https://awwapp.com/static/widget/sample_toolbar.js"></script>
</body>
</html>
