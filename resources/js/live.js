import { Room, RoomEvent } from 'livekit-client';

/**
 * A minimal room connector: joins, renders local + remote video/audio
 * tracks into a given grid element, and cleans up on disconnect. This is
 * the base primitive — call vs. stream UX, layout, and controls beyond
 * mute/camera get built later on top of this.
 */
function createLiveRoom({ wsUrl, token, canPublish }) {
    const room = new Room();

    function attach(track, participantIdentity, gridEl) {
        const el = track.attach();
        el.dataset.participant = participantIdentity;
        el.classList.add('size-full', 'rounded-lg', 'bg-zinc-900', 'object-cover');
        gridEl.appendChild(el);
    }

    function detach(track) {
        track.detach().forEach((el) => el.remove());
    }

    return {
        room,

        async connect(gridEl) {
            room.on(RoomEvent.TrackSubscribed, (track, _publication, participant) => {
                attach(track, participant.identity, gridEl);
            });

            room.on(RoomEvent.TrackUnsubscribed, (track) => {
                detach(track);
            });

            room.on(RoomEvent.LocalTrackPublished, (publication) => {
                if (publication.track) {
                    attach(publication.track, 'you', gridEl);
                }
            });

            await room.connect(wsUrl, token);

            if (canPublish) {
                await room.localParticipant.setCameraEnabled(true);
                await room.localParticipant.setMicrophoneEnabled(true);
            }
        },

        async setCameraEnabled(enabled) {
            await room.localParticipant.setCameraEnabled(enabled);
        },

        async setMicrophoneEnabled(enabled) {
            await room.localParticipant.setMicrophoneEnabled(enabled);
        },

        async disconnect() {
            await room.disconnect();
        },
    };
}

window.createLiveRoom = createLiveRoom;
