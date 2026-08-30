/**
 * Passwordless sign-in via WebAuthn passkeys, backed by Laravel Fortify's
 * passkey routes (see routes registered by laravel/passkeys). Uses the
 * browser's native PublicKeyCredential JSON helpers, so no manual
 * base64url <-> ArrayBuffer conversion is needed.
 */

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

async function fetchJson(url, options = {}) {
    const response = await fetch(url, {
        ...options,
        headers: {
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            ...(options.body ? { 'Content-Type': 'application/json' } : {}),
            ...options.headers,
        },
    });

    if (response.status === 423) {
        window.location.href = `/user/confirm-password?intended=${encodeURIComponent(window.location.pathname)}`;

        return new Promise(() => {});
    }

    const data = await response.json().catch(() => ({}));

    if (!response.ok) {
        throw new Error(data.message ?? 'Something went wrong.');
    }

    return data;
}

export async function registerPasskey(name) {
    const { options } = await fetchJson('/user/passkeys/options');

    const credential = await navigator.credentials.create({
        publicKey: PublicKeyCredential.parseCreationOptionsFromJSON(options),
    });

    return fetchJson('/user/passkeys', {
        method: 'POST',
        body: JSON.stringify({ name, credential: credential.toJSON() }),
    });
}

export async function deletePasskey(id) {
    return fetchJson(`/user/passkeys/${id}`, { method: 'DELETE' });
}

export async function loginWithPasskey() {
    const { options } = await fetchJson('/passkeys/login/options');

    const credential = await navigator.credentials.get({
        publicKey: PublicKeyCredential.parseRequestOptionsFromJSON(options),
    });

    const { redirect } = await fetchJson('/passkeys/login', {
        method: 'POST',
        body: JSON.stringify({ credential: credential.toJSON() }),
    });

    window.location.href = redirect;
}

window.Passkeys = { registerPasskey, deletePasskey, loginWithPasskey };
