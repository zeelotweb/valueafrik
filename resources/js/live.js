import { Room, RoomEvent } from 'livekit-client';

/**
 * Lets the self-inset tile (the small floating "you" box in a 1:1 call) be
 * dragged anywhere within the stage instead of being pinned to one corner.
 * Pointer Events cover mouse and touch with one code path. Position is kept
 * in inline left/top pixels once dragged, which naturally overrides the
 * tile's default bottom/right Tailwind classes (inline style always wins),
 * clamped so it can't be dragged off the visible stage.
 */
function makeDraggable(tile, boundsEl) {
    let dragging = false;
    let startX = 0;
    let startY = 0;
    let startLeft = 0;
    let startTop = 0;

    tile.addEventListener('pointerdown', (e) => {
        dragging = true;
        tile.setPointerCapture(e.pointerId);

        const tileRect = tile.getBoundingClientRect();
        const boundsRect = boundsEl.getBoundingClientRect();

        startX = e.clientX;
        startY = e.clientY;
        startLeft = tileRect.left - boundsRect.left;
        startTop = tileRect.top - boundsRect.top;

        // Switch to left/top positioning the first time it's dragged —
        // until then it's just sitting at its default bottom-right spot.
        tile.style.left = `${startLeft}px`;
        tile.style.top = `${startTop}px`;
        tile.style.right = 'auto';
        tile.style.bottom = 'auto';
    });

    tile.addEventListener('pointermove', (e) => {
        if (!dragging) {
            return;
        }

        const boundsRect = boundsEl.getBoundingClientRect();
        const maxLeft = boundsRect.width - tile.offsetWidth;
        const maxTop = boundsRect.height - tile.offsetHeight;

        const left = Math.min(Math.max(0, startLeft + (e.clientX - startX)), Math.max(0, maxLeft));
        const top = Math.min(Math.max(0, startTop + (e.clientY - startY)), Math.max(0, maxTop));

        tile.style.left = `${left}px`;
        tile.style.top = `${top}px`;
    });

    const stopDragging = (e) => {
        if (dragging) {
            dragging = false;
            tile.releasePointerCapture(e.pointerId);
        }
    };

    tile.addEventListener('pointerup', stopDragging);
    tile.addEventListener('pointercancel', stopDragging);
}

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
    // LiveKit can route remote audio through a WebAudio gain node instead of
    // playing the <audio> element directly (e.g. once any audio processing
    // plugin is active) — when it does, that element's own .muted property
    // stops being connected to what's actually audible. track.setVolume()
    // is the one API that works either way, so deafening needs the actual
    // RemoteAudioTrack objects, not just their attached elements.
    const remoteAudioTracks = new Set();
    let deafened = false;

    function tileFor(identity, label, gridEl) {
        let tile = gridEl.querySelector(`[data-tile="${identity}"]`);

        if (tile) {
            return tile;
        }

        tile = document.createElement('div');
        tile.dataset.tile = identity;

        // In spotlight mode (1:1 calls) your own tile floats as a small
        // inset over whoever else is in the call, who fills the whole
        // stage — everywhere else keeps the equal-size tile grid.
        const spotlight = gridEl.dataset.layout === 'spotlight';
        const isSelfInset = spotlight && identity === 'you';

        if (isSelfInset) {
            // bottom-20 clears the floating control bar (bottom-4, ~56px
            // tall) instead of sitting on top of it — on a narrow screen
            // the old bottom-3 placement covered its rightmost buttons.
            // touch-none stops the browser treating a drag on this tile as
            // a page scroll gesture on mobile.
            tile.className = 'absolute bottom-20 right-3 z-20 flex aspect-[3/4] w-24 touch-none items-center justify-center overflow-hidden rounded-lg bg-zinc-800 shadow-lg ring-2 ring-white/70 sm:w-32';
            makeDraggable(tile, gridEl);
        } else if (spotlight) {
            tile.className = 'absolute inset-0 flex items-center justify-center overflow-hidden bg-zinc-800';
        } else {
            tile.className = 'relative isolate flex aspect-video items-center justify-center overflow-hidden rounded-xl bg-zinc-800';
        }

        const placeholder = document.createElement('div');
        placeholder.dataset.placeholder = 'true';
        placeholder.className = isSelfInset
            ? 'flex size-8 items-center justify-center rounded-full bg-zinc-700 text-zinc-400'
            : 'flex size-16 items-center justify-center rounded-full bg-zinc-700 text-zinc-400';
        placeholder.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="${isSelfInset ? 'size-4' : 'size-8'}"><path fill-rule="evenodd" d="M18.685 19.097A9.723 9.723 0 0 0 21.75 12c0-5.385-4.365-9.75-9.75-9.75S2.25 6.615 2.25 12a9.723 9.723 0 0 0 3.065 7.097A9.716 9.716 0 0 0 12 21.75a9.716 9.716 0 0 0 6.685-2.653Zm-12.54-1.285A7.486 7.486 0 0 1 12 15a7.486 7.486 0 0 1 5.855 2.812A8.224 8.224 0 0 1 12 20.25a8.224 8.224 0 0 1-5.855-2.438ZM15.75 9a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z" clip-rule="evenodd" /></svg>`;
        tile.appendChild(placeholder);

        const labelEl = document.createElement('div');
        labelEl.dataset.label = 'true';
        // Top-left, not bottom-left — the bottom of the stage is where the
        // floating control bar sits, and a bottom-anchored label on the
        // last row of tiles would end up hidden behind it.
        labelEl.className = isSelfInset
            ? 'absolute top-1 left-1 z-10 rounded bg-black/60 px-1.5 py-0.5 text-[10px] font-medium text-white backdrop-blur-sm'
            : 'absolute top-2 left-2 z-10 rounded-md bg-black/60 px-2 py-1 text-xs font-medium text-white backdrop-blur-sm';
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

                if (typeof track.setVolume === 'function') {
                    remoteAudioTracks.add(track);
                    track.setVolume(deafened ? 0 : 1);
                }
            }
        }
    }

    function detach(track, identity, gridEl) {
        remoteAudioTracks.delete(track);

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
        // showPlaceholderTiles is on by default — right for a call or sprint,
        // always exactly 2 people, where seeing an empty tile for someone
        // whose camera/mic are both off is the point. A stream is one host
        // broadcasting to potentially many viewers who never publish
        // anything; giving every viewer a placeholder tile the moment they
        // join would fill the grid with empty boxes as the audience grows.
        // Callers pass false there — a tile only ever appears for someone
        // who actually has a track to show.
        async connect(gridEl, { onReaction, showPlaceholderTiles = true, onParticipantCountChanged } = {}) {
            // +1 for yourself — LiveKit's own roster is remote participants
            // only. This is genuinely how many people are in the room right
            // now (a stream viewer counts here even though they never
            // publish anything), derived straight from the roster rather
            // than a separate server-tracked counter that could drift.
            const reportCount = () => onParticipantCountChanged?.(room.remoteParticipants.size + 1);

            // A participant gets a tile the moment they join — waiting for
            // their first published track would leave anyone with camera
            // and mic both off invisible in the grid, as if they weren't
            // there at all.
            room.on(RoomEvent.ParticipantConnected, (participant) => {
                if (showPlaceholderTiles) {
                    tileFor(participant.identity, participant.name || 'Someone', gridEl);
                }
                reportCount();
            });

            room.on(RoomEvent.ParticipantDisconnected, (participant) => {
                removeTile(participant.identity, gridEl);
                reportCount();
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
            // camera-off participant still shows up in the grid — only
            // relevant if we're actually going to publish something into
            // it; a viewer with nothing to show doesn't need a self tile.
            if (canPublish) {
                tileFor('you', 'You', gridEl);
            }

            // ParticipantConnected only fires for people who join after us —
            // anyone already in the room needs to be added from the roster
            // we get back the moment we connect.
            if (showPlaceholderTiles) {
                room.remoteParticipants.forEach((participant) => {
                    tileFor(participant.identity, participant.name || 'Someone', gridEl);
                });
            }

            reportCount();

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
            remoteAudioTracks.forEach((track) => {
                track.setVolume(enabled ? 0 : 1);
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
