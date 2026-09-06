import { Room, RoomEvent } from 'livekit-client';

/**
 * A room connector: joins, renders local + remote video/audio tracks into
 * a tiled grid (one tile per participant, with a name label), and exposes
 * the controls the call screen wraps in its own UI — mute, camera,
 * deafen ("speaker"), and lightweight emoji reactions over LiveKit's data
 * channel. Layout/animation lives in the Blade component; this stays
 * focused on the LiveKit wiring.
 */
function createLiveRoom({ wsUrl, token, canPublish }) {
    const room = new Room();
    const remoteAudioEls = new Set();
    let deafened = false;

    function tileFor(identity, label, gridEl) {
        let tile = gridEl.querySelector(`[data-tile="${identity}"]`);

        if (tile) {
            return tile;
        }

        tile = document.createElement('div');
        tile.dataset.tile = identity;
        tile.className = 'relative isolate flex aspect-video items-center justify-center overflow-hidden rounded-xl bg-zinc-800';

        const placeholder = document.createElement('div');
        placeholder.dataset.placeholder = 'true';
        placeholder.className = 'flex size-16 items-center justify-center rounded-full bg-zinc-700 text-zinc-400';
        placeholder.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-8"><path fill-rule="evenodd" d="M18.685 19.097A9.723 9.723 0 0 0 21.75 12c0-5.385-4.365-9.75-9.75-9.75S2.25 6.615 2.25 12a9.723 9.723 0 0 0 3.065 7.097A9.716 9.716 0 0 0 12 21.75a9.716 9.716 0 0 0 6.685-2.653Zm-12.54-1.285A7.486 7.486 0 0 1 12 15a7.486 7.486 0 0 1 5.855 2.812A8.224 8.224 0 0 1 12 20.25a8.224 8.224 0 0 1-5.855-2.438ZM15.75 9a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z" clip-rule="evenodd" /></svg>';
        tile.appendChild(placeholder);

        const labelEl = document.createElement('div');
        labelEl.dataset.label = 'true';
        // Top-left, not bottom-left — the bottom of the stage is where the
        // floating control bar sits, and a bottom-anchored label on the
        // last row of tiles would end up hidden behind it.
        labelEl.className = 'absolute top-2 left-2 z-10 rounded-md bg-black/60 px-2 py-1 text-xs font-medium text-white backdrop-blur-sm';
        labelEl.textContent = label;
        tile.appendChild(labelEl);

        gridEl.appendChild(tile);

        return tile;
    }

    function attach(track, identity, label, gridEl) {
        const tile = tileFor(identity, label, gridEl);
        const el = track.attach();

        if (track.kind === 'video') {
            el.classList.add('absolute', 'inset-0', 'size-full', 'object-cover');
            tile.querySelector('[data-placeholder]')?.classList.add('hidden');
            tile.prepend(el);
        } else {
            el.classList.add('hidden');
            tile.appendChild(el);

            if (identity !== 'you') {
                remoteAudioEls.add(el);
                el.muted = deafened;
            }
        }
    }

    function detach(track, identity, gridEl) {
        track.detach().forEach((el) => {
            remoteAudioEls.delete(el);
            el.remove();
        });

        const tile = gridEl.querySelector(`[data-tile="${identity}"]`);
        const hasVideoLeft = tile?.querySelector('video');

        if (tile && !hasVideoLeft) {
            tile.querySelector('[data-placeholder]')?.classList.remove('hidden');
        }
    }

    function removeTile(identity, gridEl) {
        gridEl.querySelector(`[data-tile="${identity}"]`)?.remove();
    }

    return {
        room,

        // A rejected camera/mic permission is not a failed connection — the
        // room join can succeed even when the local device grants can't.
        // Callers get both back separately so they can show "connected, but
        // your camera is blocked" instead of a blanket connection error.
        async connect(gridEl, { onReaction } = {}) {
            // A participant gets a tile the moment they join — waiting for
            // their first published track would leave anyone with camera
            // and mic both off invisible in the grid, as if they weren't
            // there at all.
            room.on(RoomEvent.ParticipantConnected, (participant) => {
                tileFor(participant.identity, participant.name || 'Someone', gridEl);
            });

            room.on(RoomEvent.ParticipantDisconnected, (participant) => {
                removeTile(participant.identity, gridEl);
            });

            room.on(RoomEvent.TrackSubscribed, (track, _publication, participant) => {
                attach(track, participant.identity, participant.name || 'Someone', gridEl);
            });

            room.on(RoomEvent.TrackUnsubscribed, (track, _publication, participant) => {
                detach(track, participant.identity, gridEl);
            });

            room.on(RoomEvent.LocalTrackPublished, (publication) => {
                if (publication.track) {
                    attach(publication.track, 'you', 'You', gridEl);
                }
            });

            room.on(RoomEvent.LocalTrackUnpublished, (publication) => {
                if (publication.track) {
                    detach(publication.track, 'you', gridEl);
                }
            });

            if (onReaction) {
                room.on(RoomEvent.DataReceived, (payload) => {
                    try {
                        const message = JSON.parse(new TextDecoder().decode(payload));

                        if (message?.type === 'reaction' && typeof message.emoji === 'string') {
                            onReaction(message.emoji);
                        }
                    } catch {
                        //
                    }
                });
            }

            await room.connect(wsUrl, token);

            // An empty tile with just a name label, so a muted mic or a
            // camera-off participant still shows up in the grid.
            tileFor('you', 'You', gridEl);

            // ParticipantConnected only fires for people who join after us —
            // anyone already in the room needs to be added from the roster
            // we get back the moment we connect.
            room.remoteParticipants.forEach((participant) => {
                tileFor(participant.identity, participant.name || 'Someone', gridEl);
            });

            let mediaError = null;

            if (canPublish) {
                try {
                    await room.localParticipant.setCameraEnabled(true);
                    await room.localParticipant.setMicrophoneEnabled(true);
                } catch (e) {
                    mediaError = e.message;
                }
            }

            return { mediaError };
        },

        async setCameraEnabled(enabled) {
            await room.localParticipant.setCameraEnabled(enabled);
        },

        async setMicrophoneEnabled(enabled) {
            await room.localParticipant.setMicrophoneEnabled(enabled);
        },

        setDeafened(enabled) {
            deafened = enabled;
            remoteAudioEls.forEach((el) => {
                el.muted = enabled;
            });
        },

        sendReaction(emoji) {
            room.localParticipant.publishData(
                new TextEncoder().encode(JSON.stringify({ type: 'reaction', emoji })),
                { reliable: false },
            );
        },

        async disconnect() {
            await room.disconnect();
        },
    };
}

window.createLiveRoom = createLiveRoom;
