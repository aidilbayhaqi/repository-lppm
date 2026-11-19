@props(['value'])
@php($status=\App\Enums\RepositoryStatus::tryFrom($value))
<span class="badge badge-{{ $value }}">{{ $status?->label() ?? $value }}</span>
