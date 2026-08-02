@extends('layouts.admin')

@section('title', 'Create Group | LOA Admin')
@section('content')
    <div class="page-header">
        <div>
            <h1>Create Group</h1>
            <p>Add a new platform group.</p>
        </div>
        <a class="button button-ghost" href="{{ route('admin.groups') }}" style="border-color:var(--border);color:var(--text-secondary);">Back to groups</a>
    </div>

    <div class="detail-card">
        <form method="post" action="{{ route('admin.groups.store') }}" class="form-grid">
            @csrf
            <div class="form-row">
                <label for="name">Group name</label>
                <input type="text" id="name" name="name" value="{{ old('name') }}" required>
                @error('name') <span class="error-text">{{ $message }}</span> @enderror
            </div>
            <div class="form-row">
                <label for="description">Description (optional)</label>
                <input type="text" id="description" name="description" value="{{ old('description') }}">
                @error('description') <span class="error-text">{{ $message }}</span> @enderror
            </div>
            <div>
                <button class="button" type="submit">Create group</button>
            </div>
        </form>
    </div>
@endsection
