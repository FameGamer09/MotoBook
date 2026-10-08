@extends('layouts.admin', ['pageCss' => 'admin-customers'])
@section('title', 'Customers · MotoBook Admin')
@section('page-title', 'Customers')
@section('content')

    <table class="ledger">
        <thead>
            <tr><th>Name</th><th>Email</th><th>Phone</th><th>Joined</th><th>Status</th><th></th></tr>
        </thead>
        <tbody>
            @foreach ($customers as $customer)
                <tr>
                    <td class="font-medium">{{ $customer->name }}</td>
                    <td class="text-text-dim">{{ $customer->email }}</td>
                    <td class="text-text-dim">{{ $customer->phone }}</td>
                    <td class="text-text-dim">{{ $customer->created_at->format('M j, Y') }}</td>
                    <td>
                        @if ($customer->status === 'banned')
                            <span class="status-label danger">Banned</span>
                        @else
                            <span class="status-label ok">Active</span>
                        @endif
                    </td>
                    <td>
                        @if ($customer->status === 'banned')
                            <form method="POST" action="{{ route('admin.customers.activate', $customer) }}">
                                @csrf @method('PATCH')
                                <button type="submit" class="table-link">Reactivate</button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('admin.customers.ban', $customer) }}" onsubmit="return confirm('Ban this customer?')">
                                @csrf @method('PATCH')
                                <button type="submit" class="table-link danger">Ban</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

@endsection
