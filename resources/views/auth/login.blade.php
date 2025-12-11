<x-guest-layout>
    <div class="min-h-screen flex">
        <!-- LEFT SIDE - Branding -->
        <div
            class="hidden lg:flex lg:w-1/2 bg-gradient-to-br from-info via-info to-success text-white p-12 flex-col justify-between">
            <div class="flex flex-col items-center justify-center h-full">
                <div class="text-center">
                    <x-mary-icon name="o-beaker" class="w-32 h-32 mx-auto mb-8 opacity-90" />
                    <h1 class="text-5xl font-bold mb-4">MMMH&MC</h1>
                    <p class="text-xl opacity-90">Pharmacy Management System</p>
                </div>
            </div>

            <div class="text-sm opacity-60">
                © {{ date('Y') }} Mariano Marcos Memorial Hospital and Medical Center
            </div>
        </div>

        <!-- RIGHT SIDE - Login Form -->
        <div class="w-full lg:w-1/2 flex items-center justify-center p-8 bg-base-100">
            <div class="w-full max-w-md">
                <!-- Mobile Logo -->
                <div class="lg:hidden text-center mb-8">
                    <div class="inline-flex items-center gap-3 mb-4">
                        <x-mary-icon name="o-beaker" class="w-10 h-10 text-primary" />
                        <div class="text-left">
                            <h1 class="text-xl font-bold">MMMH&MC</h1>
                            <p class="text-sm text-base-content/60">Pharmacy System</p>
                        </div>
                    </div>
                </div>

                <div class="mb-8">
                    <h2 class="text-3xl font-bold mb-2">Welcome Back</h2>
                    <p class="text-base-content/60">Sign in to access the pharmacy management system</p>
                </div>

                <!-- Validation Errors -->
                @if ($errors->any())
                    <x-mary-alert icon="o-exclamation-triangle" class="alert-error mb-6">
                        <ul class="list-disc list-inside">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </x-mary-alert>
                @endif

                <!-- Status Message -->
                @session('status')
                    <x-mary-alert icon="o-check-circle" class="alert-success mb-6">
                        {{ $value }}
                    </x-mary-alert>
                @endsession

                <form method="POST" action="{{ route('login') }}" class="space-y-6">
                    @csrf

                    <!-- Email Input -->
                    <div>
                        <x-label for="email" value="Email Address" class="mb-2" />
                        <x-mary-input id="email" type="email" name="email" :value="old('email')" required autofocus
                            autocomplete="username" placeholder="your.email@hospital.gov.ph" icon="o-envelope"
                            class="input-lg" />
                    </div>

                    <!-- Password Input -->
                    <div>
                        <x-label for="password" value="Password" class="mb-2" />
                        <x-mary-input id="password" type="password" name="password" required
                            autocomplete="current-password" placeholder="Enter your password" icon="o-lock-closed"
                            class="input-lg" />
                    </div>

                    <!-- Remember Me & Forgot Password -->
                    <div class="flex items-center justify-between">
                        <label for="remember_me" class="flex items-center cursor-pointer">
                            <x-mary-checkbox id="remember_me" name="remember" />
                            <span class="ms-2 text-sm">Remember me</span>
                        </label>

                        @if (Route::has('password.request'))
                            <a href="{{ route('password.request') }}"
                                class="text-sm text-primary hover:text-primary/80 font-medium">
                                Forgot password?
                            </a>
                        @endif
                    </div>
                    <!-- Login Button -->
                    <x-mary-button type="submit" class="btn-primary w-full btn-lg" icon="o-arrow-right-on-rectangle">
                        Sign In
                    </x-mary-button>
                </form>

                <!-- Help Text -->
                <div class="mt-8 text-center">
                    <x-mary-alert icon="o-information-circle" class="alert-info">
                        <div class="text-sm">
                            <strong>Need help?</strong> Contact the IT Department or Pharmacy Administration for
                            login
                            assistance.
                        </div>
                    </x-mary-alert>
                </div>

                <!-- Security Notice -->
                <div class="mt-6 text-center text-xs text-base-content/50">
                    <p>This system is for authorized pharmacy personnel only.</p>
                    <p class="mt-1">All activities are logged and monitored.</p>
                </div>
            </div>
        </div>
    </div>
</x-guest-layout>
