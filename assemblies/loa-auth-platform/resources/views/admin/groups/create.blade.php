@extends('layouts.admin')

@section('title', 'Create Group | LOA Admin')
@section('content')
    @include('admin.partials.breadcrumbs', ['items' => [
        ['label' => 'Groups', 'url' => route('admin.groups')],
        ['label' => 'Create Group'],
    ]])
    <div class="page-header">
        <div>
            <h1>Create Group</h1>
            <p>Add a new platform group.</p>
        </div>
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
            <div class="form-row">
                <label for="priority">Priority (1 = highest, default 10)</label>
                <input type="number" id="priority" name="priority" value="{{ old('priority', 10) }}" min="1" max="100" required>
                @error('priority') <span class="error-text">{{ $message }}</span> @enderror
            </div>
            <div>
                <button class="button" type="submit">Create group</button>
            </div>
        </form>
    </div>
@endsection
