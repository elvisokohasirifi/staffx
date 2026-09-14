@extends(backpack_view('layouts.auth'))

@section('content')
    <div class="page page-center">
        <div class="container container-tight py-4">
            <div class="text-center mb-4 display-6 auth-logo-container">
                {!! backpack_theme_config('project_logo') !!}
            </div>
            <div class="card card-md">
                <div class="card-body pt-0">
                    <h2 class="card-title text-center my-4">Create Your Organization</h2>
                    <p class="text-muted text-center">Set up your organization and its first admin account.</p>

                    <form method="POST" action="{{ route('tenant.register.store') }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label" for="organization_name">Organization Name</label>
                            <input autofocus type="text" class="form-control @error('organization_name') is-invalid @enderror" name="organization_name" id="organization_name" value="{{ old('organization_name') }}">
                            @error('organization_name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="name">Your Name</label>
                            <input type="text" class="form-control @error('name') is-invalid @enderror" name="name" id="name" value="{{ old('name') }}">
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="email">Email</label>
                            <input type="email" class="form-control @error('email') is-invalid @enderror" name="email" id="email" value="{{ old('email') }}">
                            @error('email')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="password">Password</label>
                            <input type="password" class="form-control @error('password') is-invalid @enderror" name="password" id="password">
                            @error('password')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label class="form-label" for="password_confirmation">Confirm Password</label>
                            <input type="password" class="form-control" name="password_confirmation" id="password_confirmation">
                        </div>

                        <button type="submit" class="btn btn-primary w-100">Create Organization</button>
                    </form>
                </div>
            </div>
            <div class="text-center text-muted mt-4">
                <a href="{{ route('backpack.auth.login') }}">Already have an account? Sign in</a>
            </div>
        </div>
    </div>
@endsection
