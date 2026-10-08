@extends('layouts.auth', ['pageCss' => 'profile-edit'])
@section('title', 'Edit Profile · MotoBook')
@section('content')

    <button type="button" class="back-btn" onclick="window.location='{{ route('customer.profile') }}'">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
    </button>

    <h1 class="auth-title">Edit Profile</h1>
    <p class="auth-subtitle">Update your name and profile photo.</p>

    @if ($errors->any())
        <div class="error-banner">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('customer.profile.update') }}" enctype="multipart/form-data">
        @csrf
        @method('PATCH')

        <label class="edit-avatar-wrap" for="avatar">
            @if ($user->avatar)
                <img src="{{ asset('storage/'.$user->avatar) }}" class="edit-avatar" id="avatar-preview" alt="">
            @else
                <div class="edit-avatar" id="avatar-preview">👤</div>
            @endif
            <span class="edit-avatar-btn">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M15 4l5 5-11 11H4v-5z"/></svg>
            </span>
            <input type="file" id="avatar" name="avatar" accept="image/*" class="edit-avatar-input" onchange="previewAvatar(this)">
        </label>

        <div class="auth-field">
            <label for="name" class="auth-label">Full Name</label>
            <div class="input-group">
                <input type="text" id="name" name="name" class="auth-input auth-input-plain" value="{{ old('name', $user->name) }}" required autofocus>
            </div>
        </div>

        <button type="submit" class="btn-primary">Save Changes</button>
    </form>

    <script>
        function previewAvatar(input) {
            if (!input.files || !input.files[0]) return;
            const reader = new FileReader();
            const preview = document.getElementById('avatar-preview');
            reader.onload = (e) => {
                const img = document.createElement('img');
                img.src = e.target.result;
                img.className = 'edit-avatar';
                img.id = 'avatar-preview';
                preview.replaceWith(img);
            };
            reader.readAsDataURL(input.files[0]);
        }
    </script>

@endsection
