<x-guest-layout>
    <main class="pharmacy-login" x-data="pharmacyAppearance" :data-login-theme="dark ? 'dark' : 'light'">
        <button type="button" class="login-theme-toggle" @click="toggleTheme()" :aria-label="dark ? 'Switch to light mode' : 'Switch to dark mode'" aria-label="Switch to dark mode" x-cloak>
            <x-mary-icon name="o-moon" class="w-5 h-5" x-show="!dark" />
            <x-mary-icon name="o-sun" class="w-5 h-5" x-show="dark" />
        </button>
        <aside class="login-brand" aria-label="MMMH&MC Pharmacy Management System">
            <div class="login-hospital">
                <img src="{{ asset('images/hospital-logo.png') }}" alt="MMMH&MC hospital seal" width="56" height="56">
                <span>Mariano Marcos Memorial Hospital<br>and Medical Center</span>
            </div>
            <div class="login-brand-content">
                <div class="login-product-mark"><x-mary-icon name="o-beaker" class="w-6 h-6" /><span>Pharmacy Management System</span></div>
                <h1>Your pharmacy.<br><span>Connected.</span></h1>
                <p>One workspace for pharmacy dispensing,<br class="login-wide-break"> inventory, and reporting.</p>
                <div class="login-capabilities" aria-label="Pharmacy capabilities">
                    <span><x-mary-icon name="o-cube" class="w-4 h-4" /> Inventory</span>
                    <span><x-mary-icon name="o-beaker" class="w-4 h-4" /> Dispensing</span>
                    <span><x-mary-icon name="o-chart-bar" class="w-4 h-4" /> Reporting</span>
                </div>
            </div>
            <div class="login-brand-footer">
                <div class="login-access-note"><x-mary-icon name="o-shield-check" class="w-5 h-5" /><span>For authorized pharmacy personnel</span></div>
                <p class="login-copyright">&copy; {{ date('Y') }} Mariano Marcos Memorial Hospital and Medical Center</p>
            </div>
        </aside>

        <section class="login-panel" aria-labelledby="login-heading">
            <div class="login-content">
                <div class="login-mobile-brand">
                    <img src="{{ asset('images/hospital-logo.png') }}" alt="" width="44" height="44">
                    <div><strong>MMMH&MC</strong><span>Pharmacy Management System</span></div>
                </div>

                <div class="login-card">
                    <div class="login-form-mark"><x-mary-icon name="o-arrow-right-on-rectangle" class="w-6 h-6" /></div>
                    <header class="login-heading">
                        <h2 id="login-heading">Welcome back</h2>
                        <p>Enter your credentials to access your pharmacy workspace.</p>
                    </header>

                    @if ($errors->any())
                        <div class="login-message login-message-error" role="alert" id="login-errors">
                            <strong>Unable to sign in</strong>
                            <ul class="list-disc pl-5 mt-1">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @session('status')
                        <div class="login-message login-message-success" role="status">{{ $value }}</div>
                    @endsession

                    <form method="POST" action="{{ route('login') }}" class="login-form" x-data="{ showPassword: false, submitting: false, capsLock: false }" @submit="submitting = true" @pageshow.window="submitting = false">
                        @csrf
                        <div class="login-field">
                            <label for="email">Email address</label>
                            <div class="login-input-wrap">
                                <x-mary-icon name="o-envelope" class="login-field-icon" />
                                <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" placeholder="your.email@hospital.gov.ph" @error('email') aria-invalid="true" aria-describedby="login-errors" @enderror>
                            </div>
                        </div>
                        <div class="login-field">
                            <label for="password">Password</label>
                            <div class="login-input-wrap">
                                <x-mary-icon name="o-lock-closed" class="login-field-icon" />
                                <input id="password" type="password" :type="showPassword ? 'text' : 'password'" name="password" required autocomplete="current-password" placeholder="Enter your password" class="login-password" @keydown="capsLock = $event.getModifierState('CapsLock')" @keyup="capsLock = $event.getModifierState('CapsLock')" @blur="capsLock = false" aria-describedby="password-caps-lock{{ $errors->has('password') ? ' login-errors' : '' }}" @error('password') aria-invalid="true" @enderror>
                                <button type="button" class="login-password-toggle" @click="showPassword = !showPassword" :aria-label="showPassword ? 'Hide password' : 'Show password'" :aria-pressed="showPassword" aria-label="Show password" aria-controls="password" x-cloak>
                                    <x-mary-icon name="o-eye" class="w-5 h-5" x-show="!showPassword" />
                                    <x-mary-icon name="o-eye-slash" class="w-5 h-5" x-show="showPassword" />
                                </button>
                            </div>
                        </div>
                        <p id="password-caps-lock" class="login-caps-lock" x-show="capsLock" x-cloak role="status">Caps Lock is on.</p>
                        <div class="login-options">
                            <label for="remember_me"><input id="remember_me" type="checkbox" name="remember" @checked(old('remember'))> <span>Remember me</span></label>
                            @if (Route::has('password.request'))
                                <a href="{{ route('password.request') }}">Forgot password?</a>
                            @endif
                        </div>
                        <button type="submit" class="login-submit" :disabled="submitting">
                            <span role="status" aria-live="polite" x-text="submitting ? 'Signing in…' : 'Sign in'">Sign in</span>
                            <x-mary-icon name="o-arrow-right" class="w-5 h-5" />
                        </button>
                    </form>

                    <div class="login-help">
                        <x-mary-icon name="o-question-mark-circle" class="w-5 h-5 shrink-0" />
                        <div><h3>Need help signing in?</h3><p>Contact the IT Department or Pharmacy Administration for login assistance.</p></div>
                    </div>
                </div>
                <footer class="login-security">
                    <x-mary-icon name="o-shield-check" class="w-4 h-4 shrink-0" />
                    <p>This system is for authorized pharmacy personnel only.<br>All activities are logged and monitored.</p>
                </footer>
            </div>
        </section>
    </main>
</x-guest-layout>
