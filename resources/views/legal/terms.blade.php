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
                <h1 class="mt-2 font-display text-3xl font-bold tracking-tight">Terms of Service</h1>
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
                        Welcome to valueAFRIK. valueAFRIK is a product of ZeelotWeb ("ZeelotWeb," "valueAFRIK," "we,"
                        "us," or "our"), based in Columbus, Ohio. These Terms of Service ("Terms") govern your access
                        to and use of valueAFRIK's website, apps, and related services (together, the "Service"). By
                        creating an account or using the Service, you agree to these Terms. If you don't agree,
                        please don't use the Service.
                    </p>
                    <p>
                        Please also read our <a href="{{ route('legal.privacy') }}" wire:navigate class="font-medium text-cyan-600 hover:underline dark:text-cyan-400">Privacy Policy</a>, which explains how we
                        collect and use information and is incorporated into these Terms by reference.
                    </p>

                    <div>
                        <h2 class="font-display text-xl font-semibold text-stone-900 dark:text-white">1. Eligibility</h2>
                        <p class="mt-2">
                            You must be at least 13 years old to use valueAFRIK. By creating an account, you confirm
                            that you meet this requirement and that all information you provide is accurate. One
                            account per person — you may not create an account on behalf of someone else, or maintain
                            more than one account, without our permission.
                        </p>
                        <p class="mt-2 rounded-lg bg-stone-100 px-3 py-2 font-mono text-xs text-stone-500 dark:bg-stone-900 dark:text-stone-400">
                            [ STILL NEEDS BUILDING ] 13 is confirmed as the intended minimum (matching the
                            Facebook/Instagram standard), but nothing at registration actually asks for or checks a
                            date of birth yet — this clause is an honor-system promise until an age field and check
                            exist at signup.
                        </p>
                    </div>

                    <div>
                        <h2 class="font-display text-xl font-semibold text-stone-900 dark:text-white">2. Your account</h2>
                        <p class="mt-2">
                            You're responsible for the activity on your account and for keeping your password
                            confidential. Let us know immediately if you suspect unauthorized access. We strongly
                            recommend turning on two-factor authentication in Settings &rarr; Security. We may
                            suspend or terminate your account as described in Section 10.
                        </p>
                    </div>

                    <div>
                        <h2 class="font-display text-xl font-semibold text-stone-900 dark:text-white">3. The Service</h2>
                        <p class="mt-2">valueAFRIK lets you:</p>
                        <ul class="mt-2 list-disc space-y-1.5 ps-5">
                            <li>Build a profile ("Roots") describing your languages, heritage, and interests.</li>
                            <li>Post to your Wall, join and post in Communities, and co-author Bridge Posts with another member.</li>
                            <li>Follow other members, message them, and start audio or video calls.</li>
                            <li>Join live sessions and Culture Sprint activities.</li>
                            <li>Earn Bridge Score points for cross-cultural engagement on the platform.</li>
                        </ul>
                        <p class="mt-2">
                            valueAFRIK is currently in an active-development / early-access phase. Features may
                            change, and we may add, modify, or remove functionality as the platform evolves.
                        </p>
                    </div>

                    <div>
                        <h2 class="font-display text-xl font-semibold text-stone-900 dark:text-white">4. Your content</h2>
                        <p class="mt-2">
                            You own the content you post to valueAFRIK — your Wall posts, comments, Bridge Posts,
                            messages, photos, and anything else you create or upload ("Your Content"). By posting
                            Your Content, you grant valueAFRIK a worldwide, non-exclusive, royalty-free license to
                            host, store, reproduce, and display it solely to operate and provide the Service — for
                            example, showing your post to your followers, or a message to its recipient. This license
                            ends when you delete the specific content or your account, except where a copy needs to
                            persist for someone else's own record (like the other side of a message thread or a
                            Bridge Post) or where we're required to retain it.
                        </p>
                        <p class="mt-2">
                            You're solely responsible for what you post and for having the rights to post it. Don't
                            post anything you don't have the right to share, or that violates someone else's
                            intellectual property or privacy.
                        </p>
                    </div>

                    <div>
                        <h2 class="font-display text-xl font-semibold text-stone-900 dark:text-white">5. Acceptable use</h2>
                        <p class="mt-2">valueAFRIK exists to connect people across cultures in good faith. When using the Service, you agree not to:</p>
                        <ul class="mt-2 list-disc space-y-1.5 ps-5">
                            <li>Harass, bully, threaten, or demean anyone, including on the basis of heritage, ethnicity, nationality, religion, or any other protected characteristic — this cuts directly against what the platform is for.</li>
                            <li>Post hate speech, incite violence, or share illegal content.</li>
                            <li>Impersonate any person or organization, or misrepresent your affiliation with one.</li>
                            <li>Spam, or use the Service for unsolicited advertising or bulk messaging.</li>
                            <li>Harvest or scrape data from the Service, or use automated means (bots, scrapers) to access it without our permission.</li>
                            <li>Circumvent another member's block of you, or attempt to contact someone who has blocked you through another account.</li>
                            <li>Upload malware, or attempt to disrupt, overload, or gain unauthorized access to the Service or other members' accounts.</li>
                            <li>Infringe anyone's intellectual property, privacy, or other legal rights.</li>
                        </ul>
                        <p class="mt-2">
                            Violating this section may result in content removal, warnings, suspension, or permanent
                            termination of your account, at our discretion and depending on severity.
                        </p>
                    </div>

                    <div>
                        <h2 class="font-display text-xl font-semibold text-stone-900 dark:text-white">6. Reporting and moderation</h2>
                        <p class="mt-2">
                            You can block another member at any time, which stops them from messaging or calling you.
                            You can report content or a community for review. Our moderators (and, within a
                            Community, that Community's own owners and monitors) may review reported content and
                            remove content or restrict access that violates these Terms. Community owners and
                            monitors have moderation authority within their own Community, on top of — not instead
                            of — the platform-wide rules in these Terms.
                        </p>
                    </div>

                    <div>
                        <h2 class="font-display text-xl font-semibold text-stone-900 dark:text-white">7. Bridge Posts and shared content</h2>
                        <p class="mt-2">
                            A Bridge Post is co-authored with another named member — each of you writes your own half.
                            Because it's inherently a shared piece of content, deleting your account doesn't remove
                            your half from the other participant's copy of the post; it remains visible to them as
                            part of their own record of that exchange, consistent with Section 4.
                        </p>
                    </div>

                    <div>
                        <h2 class="font-display text-xl font-semibold text-stone-900 dark:text-white">8. Live sessions and calls</h2>
                        <p class="mt-2">
                            Audio and video for calls and live sessions is transmitted through LiveKit, a third-party
                            real-time media service, while a session is active. valueAFRIK does not record or store
                            call or live-session audio/video. The same conduct rules in Section 5 apply during a live
                            or call session as anywhere else on the Service.
                        </p>
                    </div>

                    <div>
                        <h2 class="font-display text-xl font-semibold text-stone-900 dark:text-white">9. Third-party services</h2>
                        <p class="mt-2">
                            Some features depend on third-party services we don't control — signing in with Google or
                            Facebook, and LiveKit for calls and live sessions. Your use of those features is also
                            subject to that provider's own terms, and we're not responsible for their availability or
                            conduct.
                        </p>
                    </div>

                    <div>
                        <h2 class="font-display text-xl font-semibold text-stone-900 dark:text-white">10. Termination</h2>
                        <p class="mt-2">
                            You can delete your account at any time in Settings &rarr; Danger zone — this is
                            immediate and permanent, and can't be undone. We may suspend or terminate your account if
                            you violate these Terms, if required by law, or if we discontinue the Service. Where
                            practical, we'll try to give you notice first, except where immediate action is needed to
                            protect the Service or its members.
                        </p>
                    </div>

                    <div>
                        <h2 class="font-display text-xl font-semibold text-stone-900 dark:text-white">11. Disclaimers</h2>
                        <p class="mt-2">
                            The Service is provided "as is" and "as available," without warranties of any kind,
                            whether express or implied, including implied warranties of merchantability, fitness for
                            a particular purpose, and non-infringement. We don't warrant that the Service will be
                            uninterrupted, secure, or error-free, or that content posted by other members is
                            accurate, safe, or lawful. valueAFRIK is not a substitute for professional advice of any
                            kind.
                        </p>
                    </div>

                    <div>
                        <h2 class="font-display text-xl font-semibold text-stone-900 dark:text-white">12. Limitation of liability</h2>
                        <p class="mt-2">
                            To the fullest extent permitted by law, valueAFRIK and its team won't be liable for any
                            indirect, incidental, special, consequential, or punitive damages, or any loss of
                            profits, data, or goodwill, arising from your use of the Service, even if we've been
                            advised of the possibility of such damages. Our total liability for any claim relating to
                            the Service is limited to the greater of the amount you've paid us in the past twelve
                            months (currently nothing — the Service is free) or a nominal amount.
                        </p>
                        <p class="mt-2 rounded-lg bg-stone-100 px-3 py-2 font-mono text-xs text-stone-500 dark:bg-stone-900 dark:text-stone-400">
                            [ NEEDS INPUT ] A lawyer should confirm this section is enforceable in whatever
                            jurisdiction you settle on in Section 15 — liability limitations are treated very
                            differently place to place, and some jurisdictions don't allow certain limitations at all.
                        </p>
                    </div>

                    <div>
                        <h2 class="font-display text-xl font-semibold text-stone-900 dark:text-white">13. Indemnification</h2>
                        <p class="mt-2">
                            You agree to defend, indemnify, and hold harmless valueAFRIK and its team from any claims,
                            damages, losses, and expenses (including reasonable legal fees) arising from your use of
                            the Service, Your Content, or your violation of these Terms.
                        </p>
                    </div>

                    <div>
                        <h2 class="font-display text-xl font-semibold text-stone-900 dark:text-white">14. Intellectual property</h2>
                        <p class="mt-2">
                            The valueAFRIK name, logo, and the design and code of the Service are owned by ZeelotWeb
                            and protected by intellectual property law. These Terms don't grant you any right to use
                            our branding except as necessary to use the Service normally. If you believe content on
                            valueAFRIK infringes your copyright, contact us using the details in Section 17 with
                            enough detail for us to locate and review it.
                        </p>
                    </div>

                    <div>
                        <h2 class="font-display text-xl font-semibold text-stone-900 dark:text-white">15. Governing law and disputes</h2>
                        <p class="mt-2">
                            These Terms are governed by the laws of the State of Ohio, USA, without regard to its
                            conflict-of-laws principles. Any dispute arising from these Terms or the Service will be
                            subject to the exclusive jurisdiction of the state and federal courts located in Franklin
                            County, Ohio.
                        </p>
                        <p class="mt-2 rounded-lg bg-stone-100 px-3 py-2 font-mono text-xs text-stone-500 dark:bg-stone-900 dark:text-stone-400">
                            [ NEEDS REVIEW ] This reflects Ohio as ZeelotWeb's current base, but a lawyer should still
                            confirm it — including whether mandatory arbitration or a class-action waiver should be
                            added, and whether Ohio venue holds up given that valueAFRIK's users are international.
                        </p>
                    </div>

                    <div>
                        <h2 class="font-display text-xl font-semibold text-stone-900 dark:text-white">16. Changes to these Terms</h2>
                        <p class="mt-2">
                            We may update these Terms from time to time. If we make material changes, we'll let you
                            know — for example, by email or an in-app notice — before they take effect. Continuing to
                            use valueAFRIK after a change takes effect means you accept the updated Terms.
                        </p>
                    </div>

                    <div>
                        <h2 class="font-display text-xl font-semibold text-stone-900 dark:text-white">17. Contact us</h2>
                        <p class="mt-2">
                            Questions about these Terms? <a href="https://zeelot.net/contact?source=valueafrik" target="_blank" rel="noopener" class="font-medium text-cyan-600 hover:underline dark:text-cyan-400">Contact ZeelotWeb</a>, the company behind valueAFRIK — this opens in a new tab, so valueAFRIK stays right where you left it.
                        </p>
                        <p class="mt-2">valueAFRIK is a product of ZeelotWeb, based in Columbus, Ohio, USA.</p>
                        <p class="mt-2 rounded-lg bg-stone-100 px-3 py-2 font-mono text-xs text-stone-500 dark:bg-stone-900 dark:text-stone-400">
                            [ NEEDS INPUT ] A full registered street address (not just city/state) if you want a
                            complete formal notice here — some businesses intentionally keep this at city/state level
                            for privacy and use a registered agent's address for anything requiring a full mailing
                            address; that's a call for you to make either way.
                        </p>
                    </div>
                </div>
            </section>
        </main>

        @include('partials.marketing-footer')

        @fluxScripts
    </body>
</html>
