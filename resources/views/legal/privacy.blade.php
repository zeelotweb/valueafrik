<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-stone-50 text-stone-900 antialiased dark:bg-stone-950 dark:text-stone-100">

        @include('partials.marketing-header')

        <main>
            <section class="mx-auto max-w-3xl px-6 py-16">
                <p class="text-sm font-medium tracking-widest text-cyan-600 uppercase dark:text-cyan-400">Legal</p>
                <h1 class="mt-2 font-display text-3xl font-bold tracking-tight">Privacy Policy</h1>
                <p class="mt-2 text-sm text-stone-500 dark:text-stone-400">Effective date: {{ now()->format('F j, Y') }} &middot; Last updated: {{ now()->format('F j, Y') }}</p>

                <div class="mt-6 rounded-xl border border-dashed border-amber-400 bg-amber-50 p-5 text-sm text-stone-700 dark:border-amber-700 dark:bg-amber-950/20 dark:text-stone-300">
                    <p class="font-semibold text-amber-800 dark:text-amber-400">Before this goes live</p>
                    <p class="mt-1">
                        This document was drafted directly against how valueAFRIK's code actually behaves today, but it
                        still needs a licensed lawyer's review before you rely on it, plus the handful of items marked
                        <span class="font-mono text-xs">[ NEEDS INPUT ]</span> below filled in with real information —
                        see the summary at the end of this page.
                    </p>
                </div>

                <div class="prose-legal mt-10 space-y-10 text-stone-700 dark:text-stone-300">
                    <p>
                        valueAFRIK is a product of ZeelotWeb ("ZeelotWeb," "valueAFRIK," "we," "us," or "our"), based
                        in Columbus, Ohio — a social platform built to help people across the African diaspora
                        connect across cultures through profiles, posts, communities, messaging, and live
                        conversation. This Privacy Policy explains what information we collect when you use
                        valueAFRIK, why we collect it, who we share it with, and the choices and rights you have over
                        it.
                    </p>
                    <p>
                        By creating an account or otherwise using valueAFRIK, you agree to the collection and use of
                        information as described here. If you don't agree with this policy, please don't use the
                        platform. This policy should be read alongside our <a href="{{ route('legal.terms') }}" wire:navigate class="font-medium text-cyan-600 hover:underline dark:text-cyan-400">Terms of Service</a>.
                    </p>

                    <div>
                        <h2 class="font-display text-xl font-semibold text-stone-900 dark:text-white">1. Information we collect</h2>

                        <h3 class="mt-5 text-base font-semibold text-stone-900 dark:text-white">1.1 Information you give us directly</h3>
                        <ul class="mt-2 list-disc space-y-1.5 ps-5">
                            <li><strong>Account information:</strong> your name, a unique username, email address, and password (we store your password as a one-way hash — we never store or can see it in plain text).</li>
                            <li><strong>Roots (your profile):</strong> anything you choose to add to your profile — a bio, your country, the languages you speak, your heritage(s), and the topics you're curious about. Every field here is optional and editable at any time.</li>
                            <li><strong>Content you post:</strong> Wall posts, Community posts, comments, reactions, and Bridge Posts (a two-sided post you and another named member each write your own half of).</li>
                            <li><strong>Messages:</strong> the content of direct messages you send other members, including any photos attached to them.</li>
                            <li><strong>Photos and media:</strong> anything you upload — a profile photo, a cover photo, or media attached to a post or message.</li>
                            <li><strong>Communications with us:</strong> anything you send us directly, such as a support request.</li>
                        </ul>

                        <h3 class="mt-5 text-base font-semibold text-stone-900 dark:text-white">1.2 Information from connected accounts</h3>
                        <p class="mt-2">
                            If you sign up or log in using Google or Facebook, that provider shares your name, email
                            address, and profile photo with us so we can create or match your account. We never see
                            or store your Google or Facebook password. We do store an access token from that provider
                            on our servers so we can keep your connected login working; that token is never exposed
                            through the app to you or anyone else.
                        </p>

                        <h3 class="mt-5 text-base font-semibold text-stone-900 dark:text-white">1.3 Information we collect automatically</h3>
                        <ul class="mt-2 list-disc space-y-1.5 ps-5">
                            <li><strong>Usage information:</strong> how you interact with valueAFRIK — pages you visit, features you use, actions you take (like following someone, joining a community, or reacting to a post).</li>
                            <li><strong>Device and log information:</strong> your IP address, browser type, and similar technical details. We use IP addresses in particular to enforce rate limits and protect accounts from abuse (for example, to slow down repeated failed login or password-reset attempts).</li>
                            <li><strong>Push notification subscriptions:</strong> if you turn on push notifications, your browser gives us a subscription endpoint we use to deliver notifications to that device. You can turn this off at any time in Settings &rarr; Notifications.</li>
                        </ul>

                        <h3 class="mt-5 text-base font-semibold text-stone-900 dark:text-white">1.4 Information from other members</h3>
                        <p class="mt-2">
                            Other people's use of valueAFRIK can generate information about you too — for example, a
                            message sent to you, a comment tagging you, or the other half of a Bridge Post you're
                            paired on.
                        </p>
                    </div>

                    <div>
                        <h2 class="font-display text-xl font-semibold text-stone-900 dark:text-white">2. How we use your information</h2>
                        <p class="mt-2">We use the information above to:</p>
                        <ul class="mt-2 list-disc space-y-1.5 ps-5">
                            <li>Operate the core features of the platform — your profile, feed, communities, messaging, live calls and streams, and your Bridge Score.</li>
                            <li>Personalize what you see — who we suggest you follow or which communities we surface, based on your Roots and activity.</li>
                            <li>Keep the platform safe — reviewing reported content, enforcing our Terms of Service, detecting and preventing abuse, spam, and fraud.</li>
                            <li>Communicate with you — service notifications (a new message, a follow, a community request), security alerts, and, if you've opted in, push notifications.</li>
                            <li>Maintain and improve the service, including debugging and understanding how features are used in aggregate.</li>
                            <li>Comply with legal obligations and enforce our agreements.</li>
                        </ul>
                        <p class="mt-3">We do not sell your personal information, and we do not use your data for third-party advertising.</p>
                    </div>

                    <div>
                        <h2 class="font-display text-xl font-semibold text-stone-900 dark:text-white">3. How we share your information</h2>
                        <ul class="mt-2 list-disc space-y-1.5 ps-5">
                            <li><strong>With other members, as the platform is designed to work:</strong> your profile, posts, and community activity are visible to other members according to the visibility of the content and, for communities, the community's own settings (public, private, or followers-only). A message you send is visible to its recipient(s). A Bridge Post is visible to whoever your posts are normally visible to, and it displays both participants' names and their own half of the content.</li>
                            <li><strong>Service providers who process data on our behalf:</strong>
                                <ul class="mt-1.5 list-[circle] space-y-1 ps-5">
                                    <li>Our hosting provider, to run and store the platform's servers and database.</li>
                                    <li>LiveKit, which powers real-time audio and video for calls and live sessions — your audio/video stream is routed through this service while a call or live session is active.</li>
                                    <li>Google and Facebook, if you choose to sign in with a connected account.</li>
                                    <li>Browser push notification services (operated by Google, Mozilla, Apple, and similar), if you enable push notifications — they're the delivery mechanism for that feature by design of the Web Push standard.</li>
                                    <li>An email delivery service, to send account, security, and notification emails.</li>
                                </ul>
                            </li>
                            <li><strong>For legal reasons:</strong> if required by law, legal process, or a good-faith belief that it's necessary to protect the rights, safety, or property of valueAFRIK, our members, or the public.</li>
                            <li><strong>In a business transfer:</strong> if valueAFRIK is ever involved in a merger, acquisition, or sale of assets, your information may be transferred as part of that transaction, subject to this policy (or a policy at least as protective).</li>
                        </ul>
                    </div>

                    <div>
                        <h2 class="font-display text-xl font-semibold text-stone-900 dark:text-white">4. How long we keep your information</h2>
                        <p class="mt-2">
                            We keep your information for as long as your account is active, so the platform can work
                            the way it's meant to. When you delete your account, your account record is deleted
                            immediately and permanently. Content that inherently involves someone else — for example,
                            a message thread, or your half of a Bridge Post — may remain visible to the other
                            participant after your account is gone, since it's also part of their own record of the
                            conversation, and to preserve the integrity of moderation and safety records.
                        </p>
                        <p class="mt-2">We may retain limited information beyond account deletion where we're legally required to, or where necessary to resolve disputes or enforce our agreements.</p>
                    </div>

                    <div>
                        <h2 class="font-display text-xl font-semibold text-stone-900 dark:text-white">5. Your choices and rights</h2>
                        <ul class="mt-2 list-disc space-y-1.5 ps-5">
                            <li><strong>Access and correct:</strong> you can view and edit your profile information directly in Settings at any time.</li>
                            <li><strong>Download your data:</strong> Settings &rarr; Privacy lets you download a copy of your account, profile, posts, comments, reactions, Bridge Posts, Bridge Score history, messages, and any photos you've uploaded, as a file.</li>
                            <li><strong>Delete your account:</strong> Settings &rarr; Danger zone permanently deletes your account. This can't be undone.</li>
                            <li><strong>Control notifications:</strong> manage or disable push notifications at any time in Settings &rarr; Notifications.</li>
                            <li><strong>Block and report:</strong> you can block another member (which also prevents them from messaging or calling you) or report content or a community for review, at any time.</li>
                        </ul>
                        <p class="mt-3">
                            Depending on where you live, you may have additional rights under laws like the EU/UK
                            General Data Protection Regulation (GDPR) or the California Consumer Privacy Act (CCPA) —
                            including the right to object to or restrict certain processing, and the right to lodge a
                            complaint with your local data protection authority. To exercise a right not already
                            covered by the Settings tools above, contact us using the details in Section 11.
                        </p>
                    </div>

                    <div>
                        <h2 class="font-display text-xl font-semibold text-stone-900 dark:text-white">6. Children's privacy</h2>
                        <p class="mt-2">
                            valueAFRIK is not directed at children, and you must meet our minimum age requirement (see
                            our <a href="{{ route('legal.terms') }}" wire:navigate class="font-medium text-cyan-600 hover:underline dark:text-cyan-400">Terms of Service</a>)
                            to create an account. We do not knowingly collect personal information from children under
                            that age. If you believe a child has created an account, contact us and we'll take
                            appropriate action, including deleting the account.
                        </p>
                    </div>

                    <div>
                        <h2 class="font-display text-xl font-semibold text-stone-900 dark:text-white">7. Security</h2>
                        <p class="mt-2">
                            We use industry-standard measures to protect your information, including encrypting
                            traffic to and from valueAFRIK, hashing passwords rather than storing them in plain text,
                            and offering optional two-factor authentication (Settings &rarr; Security). No method of
                            transmission or storage is 100% secure, and we can't guarantee absolute security — but we
                            work to protect your information and will notify you as required by law if a breach
                            affects your account.
                        </p>
                    </div>

                    <div>
                        <h2 class="font-display text-xl font-semibold text-stone-900 dark:text-white">8. International data transfers</h2>
                        <p class="mt-2">
                            valueAFRIK connects members across countries by design. Using the platform may mean your
                            information is transferred to and processed in a country other than the one you live in,
                            which may have different data protection laws. Where required, we take steps to make sure
                            your information receives an adequate level of protection wherever it's processed.
                        </p>
                    </div>

                    <div>
                        <h2 class="font-display text-xl font-semibold text-stone-900 dark:text-white">9. Cookies and similar technologies</h2>
                        <p class="mt-2">
                            We use only the cookies strictly necessary to run the platform — keeping you signed in and
                            protecting your account against cross-site request forgery. We do not currently use
                            advertising or analytics cookies, and we don't share cookie data with ad networks. If
                            that ever changes, we'll update this policy and, where required, ask for your consent
                            first.
                        </p>
                    </div>

                    <div>
                        <h2 class="font-display text-xl font-semibold text-stone-900 dark:text-white">10. Changes to this policy</h2>
                        <p class="mt-2">
                            We may update this Privacy Policy from time to time. If we make material changes, we'll
                            let you know — for example, by email or an in-app notice — before they take effect.
                            Continuing to use valueAFRIK after a change takes effect means you accept the updated
                            policy.
                        </p>
                    </div>

                    <div>
                        <h2 class="font-display text-xl font-semibold text-stone-900 dark:text-white">11. Contact us</h2>
                        <p class="mt-2">
                            If you have questions about this Privacy Policy, how we handle your information, or want
                            to exercise a right described in Section 5, <a href="https://zeelotweb.com/contact?source=valueafrik" target="_blank" rel="noopener" class="font-medium text-cyan-600 hover:underline dark:text-cyan-400">contact ZeelotWeb</a>, the company behind valueAFRIK — this opens in a new tab, so valueAFRIK stays right where you left it.
                        </p>
                        <p class="mt-2">valueAFRIK is a product of ZeelotWeb, based in Columbus, Ohio, USA.</p>
                        <p class="mt-2 rounded-lg bg-stone-100 px-3 py-2 font-mono text-xs text-stone-500 dark:bg-stone-900 dark:text-stone-400">
                            [ NEEDS INPUT ] A full registered street address, if you want a complete formal data
                            controller notice here rather than city/state level.
                        </p>
                    </div>
                </div>
            </section>
        </main>

        @include('partials.marketing-footer')

        @fluxScripts
    </body>
</html>
