<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Login - ZOLIX Shoe Care</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="login-white-body">
        <main class="login-white-page" x-data="{ showPassword: false }">
            <section class="login-white-visual" aria-label="ZOLIX Shoe Care">
                <img src="{{ asset('assets/image1.png') }}" alt="Kelola order ZOLIX Shoe Care">
            </section>

            <section class="login-white-panel">
                <form class="login-white-card" method="POST" action="{{ route('login.store') }}">
                    @csrf

                    <div class="login-white-head">
                        <h1>Login</h1>
                        <p>Masuk ke akun administrator Anda</p>
                    </div>

                    @if ($errors->any())
                        <div class="form-alert form-alert--light">
                            {{ $errors->first() }}
                        </div>
                    @endif

                    <label class="login-white-field">
                        <span>Email</span>
                        <div>
                            <i data-lucide="mail"></i>
                            <input type="email" name="email" value="{{ old('email') }}" placeholder="Masukkan email Anda" required autofocus>
                        </div>
                    </label>

                    <label class="login-white-field">
                        <span>Password</span>
                        <div>
                            <i data-lucide="lock-keyhole"></i>
                            <input :type="showPassword ? 'text' : 'password'" name="password" placeholder="Masukkan password Anda" required>
                            <button type="button" @click="showPassword = ! showPassword" :aria-label="showPassword ? 'Sembunyikan password' : 'Tampilkan password'">
                                <i data-lucide="eye-off" x-show="! showPassword"></i>
                                <i data-lucide="eye" x-show="showPassword"></i>
                            </button>
                        </div>
                    </label>

                    <div class="login-white-row">
                        <label>
                            <input type="checkbox" name="remember" value="1" @checked(old('remember'))>
                            <span>Ingat saya</span>
                        </label>
                        <span>Lupa password?</span>
                    </div>

                    <button class="login-white-submit" type="submit">Login</button>

                    <!-- <div class="login-white-divider">
                        <span></span>
                        <small>atau</small>
                        <span></span>
                    </div>

                    <button class="login-white-google" type="button">
                        <b>G</b>
                        <span>Login dengan Google</span>
                    </button>

                    <p class="login-white-register">
                        Belum punya akun? <span>Hubungi administrator</span>
                    </p> -->
                </form>

                <p class="login-white-copy">
                    &copy; {{ now()->year }} <span>ZOLIX</span> Shoe Care. All rights reserved.
                </p>
            </section>
        </main>
    </body>
</html>
