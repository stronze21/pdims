<div class="space-y-6">
    <div class="rounded-[2rem] border border-base-300 bg-base-100 p-6 shadow-sm lg:p-8">
        <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
            <div class="max-w-3xl">
                <div class="text-xs font-semibold uppercase tracking-[0.35em] text-base-content/40">
                    Portal Manual
                </div>
                <h1 class="mt-2 text-3xl font-black tracking-tight text-base-content">
                    User Manual and Guided Walkthrough
                </h1>
                <p class="mt-3 max-w-2xl text-sm leading-6 text-base-content/70">
                    Use this guide when you want the exact button-by-button steps for the Portal workspace.
                    It covers what each button does and what to click next.
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <button class="btn btn-primary btn-sm" wire:click="startTour">
                    <x-mary-icon name="o-play" class="h-4 w-4" />
                    Start Guided Tour
                </button>
                <a href="{{ route('settings.portal.users') }}" class="btn btn-ghost btn-sm">
                    <x-mary-icon name="o-user-group" class="h-4 w-4" />
                    Open Portal Users
                </a>
            </div>
        </div>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <div class="rounded-3xl border border-base-300 bg-base-100 p-5 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                    <x-mary-icon name="o-book-open" class="h-5 w-5" />
                </div>
                <div>
                    <h2 class="text-lg font-bold text-base-content">What this Portal is for</h2>
                    <p class="text-sm text-base-content/60">A separate workspace for patient portal administration.</p>
                </div>
            </div>
            <ul class="mt-4 space-y-3 text-sm text-base-content/70">
                <li class="flex gap-3">
                    <span class="mt-1 h-2.5 w-2.5 rounded-full bg-primary"></span>
                    <span>Review patient-linked accounts and verify the right hospital record is attached.</span>
                </li>
                <li class="flex gap-3">
                    <span class="mt-1 h-2.5 w-2.5 rounded-full bg-primary"></span>
                    <span>Process prescription refill requests and track whether they were approved, denied, or completed.</span>
                </li>
                <li class="flex gap-3">
                    <span class="mt-1 h-2.5 w-2.5 rounded-full bg-primary"></span>
                    <span>Reply to portal messages and keep patient conversations organized.</span>
                </li>
                <li class="flex gap-3">
                    <span class="mt-1 h-2.5 w-2.5 rounded-full bg-primary"></span>
                    <span>Start teleconsult sessions from the dedicated Portal teleconsult workspace.</span>
                </li>
            </ul>
        </div>

        <div class="rounded-3xl border border-base-300 bg-base-100 p-5 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-secondary/10 text-secondary">
                    <x-mary-icon name="o-sparkles" class="h-5 w-5" />
                </div>
                <div>
                    <h2 class="text-lg font-bold text-base-content">Quick path</h2>
                    <p class="text-sm text-base-content/60">A simple flow for new users.</p>
                </div>
            </div>

            <div class="mt-4 space-y-3">
                <a href="{{ route('settings.portal.users') }}" class="flex items-center justify-between rounded-2xl border border-base-300 px-4 py-3 hover:bg-base-200">
                    <div>
                        <div class="font-semibold text-base-content">1. Portal Users</div>
                        <div class="text-xs text-base-content/60">Search accounts, open records, and check linked patient details.</div>
                    </div>
                    <x-mary-icon name="o-chevron-right" class="h-5 w-5 text-base-content/40" />
                </a>
                <a href="{{ route('settings.portal.refills') }}" class="flex items-center justify-between rounded-2xl border border-base-300 px-4 py-3 hover:bg-base-200">
                    <div>
                        <div class="font-semibold text-base-content">2. Refill Requests</div>
                        <div class="text-xs text-base-content/60">Review pending refills and mark them approved, denied, or completed.</div>
                    </div>
                    <x-mary-icon name="o-chevron-right" class="h-5 w-5 text-base-content/40" />
                </a>
                <a href="{{ route('settings.portal.chat') }}" class="flex items-center justify-between rounded-2xl border border-base-300 px-4 py-3 hover:bg-base-200">
                    <div>
                        <div class="font-semibold text-base-content">3. Chat Conversations</div>
                        <div class="text-xs text-base-content/60">Reply to portal chat messages and close old threads.</div>
                    </div>
                    <x-mary-icon name="o-chevron-right" class="h-5 w-5 text-base-content/40" />
                </a>
                <a href="{{ route('portal.teleconsult.lobby') }}" class="flex items-center justify-between rounded-2xl border border-base-300 px-4 py-3 hover:bg-base-200">
                    <div>
                        <div class="font-semibold text-base-content">4. Teleconsult</div>
                        <div class="text-xs text-base-content/60">Create or join sessions using the Portal teleconsult workflow.</div>
                    </div>
                    <x-mary-icon name="o-chevron-right" class="h-5 w-5 text-base-content/40" />
                </a>
            </div>
        </div>
    </div>

    <div class="rounded-3xl border border-base-300 bg-base-100 p-5 shadow-sm">
        <div class="flex items-center gap-3">
            <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-info/10 text-info">
                <x-mary-icon name="o-cursor-arrow-ripple" class="h-5 w-5" />
            </div>
            <div>
                <h2 class="text-lg font-bold text-base-content">Action-by-action button guide</h2>
                <p class="text-sm text-base-content/60">Use this when you need the exact clicks for each Portal screen.</p>
            </div>
        </div>

        <div class="mt-4 grid gap-4 lg:grid-cols-2">
            <div class="rounded-2xl border border-base-300 p-4">
                <div class="text-sm font-bold text-base-content">Portal shell buttons</div>
                <ol class="mt-3 space-y-2 text-sm text-base-content/70">
                    <li>1. Click <span class="font-semibold text-base-content">Portal Users</span> to open the account list.</li>
                    <li>2. Click <span class="font-semibold text-base-content">Refill Requests</span> to review refill work.</li>
                    <li>3. Click <span class="font-semibold text-base-content">Chat Conversations</span> to answer patient messages.</li>
                    <li>4. Click <span class="font-semibold text-base-content">Teleconsult</span> to manage teleconsult sessions.</li>
                    <li>5. Click <span class="font-semibold text-base-content">User Manual</span> to reopen this guide.</li>
                    <li>6. Click <span class="font-semibold text-base-content">Back to Main App</span> when you need to leave Portal.</li>
                </ol>
            </div>

            <div class="rounded-2xl border border-base-300 p-4">
                <div class="text-sm font-bold text-base-content">Common header buttons</div>
                <ol class="mt-3 space-y-2 text-sm text-base-content/70">
                    <li>1. Use the <span class="font-semibold text-base-content">theme button</span> to switch light or dark mode.</li>
                    <li>2. Use the <span class="font-semibold text-base-content">Manual</span> button to open the guide from any page.</li>
                    <li>3. Use the <span class="font-semibold text-base-content">Main App</span> button to return to the main system.</li>
                    <li>4. On mobile, open the <span class="font-semibold text-base-content">menu button</span> to reach the same modules.</li>
                </ol>
            </div>
        </div>

        <div class="mt-4 grid gap-4 lg:grid-cols-2">
            <div class="rounded-2xl border border-base-300 p-4">
                <div class="text-sm font-bold text-base-content">Portal Users screen</div>
                <ol class="mt-3 space-y-2 text-sm text-base-content/70">
                    <li>1. Type a name, username, email, or HPerson code in the search box.</li>
                    <li>2. Press Enter or wait for the list to update.</li>
                    <li>3. Click the eye button to view the portal account details.</li>
                    <li>4. Check the patient name, birth date, username, and linked hospital record.</li>
                    <li>5. Click the trash button only if you really need to delete the account.</li>
                </ol>
            </div>

            <div class="rounded-2xl border border-base-300 p-4">
                <div class="text-sm font-bold text-base-content">Refill Requests screen</div>
                <ol class="mt-3 space-y-2 text-sm text-base-content/70">
                    <li>1. Use the search box to find a refill request by drug name or patient.</li>
                    <li>2. Use the status filter to show pending, approved, denied, or completed items.</li>
                    <li>3. Click View to inspect the refill and read the prescription context.</li>
                    <li>4. Click Approve or Deny after reviewing the request.</li>
                    <li>5. Click Complete after an approved refill has been finished.</li>
                </ol>
            </div>

            <div class="rounded-2xl border border-base-300 p-4">
                <div class="text-sm font-bold text-base-content">Chat Conversations screen</div>
                <ol class="mt-3 space-y-2 text-sm text-base-content/70">
                    <li>1. Type a patient name or subject in the search box.</li>
                    <li>2. Click a conversation row to open the thread.</li>
                    <li>3. Read the latest patient message before you reply.</li>
                    <li>4. Type your response in the reply box.</li>
                    <li>5. Click Send Reply to send the message.</li>
                    <li>6. Click Close Conversation when the thread is finished.</li>
                </ol>
            </div>

            <div class="rounded-2xl border border-base-300 p-4">
                <div class="text-sm font-bold text-base-content">Teleconsult screen</div>
                <ol class="mt-3 space-y-2 text-sm text-base-content/70">
                    <li>1. Click New Session to open the appointment drawer.</li>
                    <li>2. Type a search term to find the correct appointment faster.</li>
                    <li>3. Click a row in the appointment table to select it.</li>
                    <li>4. Confirm the date and time fields fill automatically from the appointment.</li>
                    <li>5. Save the session, then click Join when it is ready.</li>
                    <li>6. Stay in the Portal teleconsult route so you do not jump back to the main app.</li>
                </ol>
            </div>
        </div>
    </div>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="rounded-3xl border border-base-300 bg-base-100 p-5 shadow-sm">
            <h3 class="text-base font-bold text-base-content">Best practices</h3>
            <ul class="mt-3 space-y-2 text-sm text-base-content/70">
                <li>- Keep search terms short and specific when finding a patient.</li>
                <li>- Confirm the patient name, HPerson code, and birth date before taking action.</li>
                <li>- Use the manual anytime you need a quick reminder of the workflow.</li>
            </ul>
        </div>

        <div class="rounded-3xl border border-base-300 bg-base-100 p-5 shadow-sm">
            <h3 class="text-base font-bold text-base-content">Need to switch back?</h3>
            <p class="mt-3 text-sm text-base-content/70">
                The Portal is designed as a separate workspace, but you can always return to the main app with one click from the footer or header.
            </p>
        </div>

        <div class="rounded-3xl border border-base-300 bg-base-100 p-5 shadow-sm">
            <h3 class="text-base font-bold text-base-content">Support shortcut</h3>
            <p class="mt-3 text-sm text-base-content/70">
                If a module is not behaving as expected, start the guided tour below and follow the on-screen steps.
            </p>
            <button class="btn btn-primary btn-sm mt-4" wire:click="startTour">
                <x-mary-icon name="o-play" class="h-4 w-4" />
                Launch Walkthrough
            </button>
        </div>
    </div>

    @if ($tourOpen)
        @php
            $steps = [
                1 => [
                    'title' => 'Welcome to Portal',
                    'body' => 'Start on this page, read the module list on the left, then click the section you want to work on. Portal is the staff workspace for accounts, refills, chat, and teleconsult.',
                    'tip' => 'Use the sidebar to move between modules without returning to the main app.',
                ],
                2 => [
                    'title' => 'Check Portal Users',
                    'body' => 'Click Portal Users, type a search term, press Enter if needed, then open the eye button on the right side of a row to view details. Use the trash button only when you really want to remove an account.',
                    'tip' => 'Check the patient name, username, email, and HPerson code before editing or deleting.',
                ],
                3 => [
                    'title' => 'Handle Refill Requests',
                    'body' => 'Open Refill Requests, choose the status filter, review the list, click View to inspect the refill, then use Approve, Deny, or Complete based on the case.',
                    'tip' => 'Use the process buttons in order: view first, decide second, finalize last.',
                ],
                4 => [
                    'title' => 'Manage Conversations',
                    'body' => 'Open Chat Conversations, use the search box to find a patient, click the row to open the thread, type your response, and press Send Reply. When the discussion is finished, click Close Conversation.',
                    'tip' => 'Check the unread badge and the latest message before replying.',
                ],
                5 => [
                    'title' => 'Start Teleconsult',
                    'body' => 'Open Teleconsult, click New Session if you need to start one, pick the appointment, let the date and time fill automatically, then join the session when it is ready.',
                    'tip' => 'Keep teleconsult inside the Portal route so you do not jump back to the main app.',
                ],
            ];

            $current = $steps[$tourStep] ?? $steps[1];
        @endphp

        <div class="fixed inset-0 z-50 flex items-center justify-center bg-base-300/70 px-4 py-8 backdrop-blur-sm">
            <div class="w-full max-w-2xl rounded-[2rem] border border-base-300 bg-base-100 p-6 shadow-2xl">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-[0.3em] text-base-content/40">
                            Walkthrough Step {{ $tourStep }} of 5
                        </div>
                        <h2 class="mt-2 text-2xl font-black text-base-content">{{ $current['title'] }}</h2>
                    </div>
                    <button class="btn btn-ghost btn-sm" wire:click="closeTour">
                        <x-mary-icon name="o-x-mark" class="h-4 w-4" />
                    </button>
                </div>

                <div class="mt-5 rounded-3xl bg-primary/10 p-5">
                    <p class="text-sm leading-6 text-base-content/80">{{ $current['body'] }}</p>
                    <div class="mt-4 rounded-2xl bg-base-100 p-4">
                        <div class="text-xs font-semibold uppercase tracking-[0.2em] text-base-content/40">Tip</div>
                        <p class="mt-1 text-sm text-base-content/70">{{ $current['tip'] }}</p>
                    </div>
                </div>

                <div class="mt-5 flex flex-wrap items-center justify-between gap-3">
                    <div class="text-sm text-base-content/60">
                        You can open the live module from the quick links on the left.
                    </div>

                    <div class="flex gap-2">
                        <button class="btn btn-ghost btn-sm" wire:click="previousTourStep" @disabled($tourStep === 1)>
                            Back
                        </button>
                        @if ($tourStep < 5)
                            <button class="btn btn-primary btn-sm" wire:click="nextTourStep">
                                Next
                            </button>
                        @else
                            <a href="{{ route('portal.teleconsult.lobby') }}" class="btn btn-primary btn-sm">
                                Open Teleconsult
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
